<?php

namespace App\Services\V2\Mail;

use RuntimeException;

/*
 * W95 MIXXTUNE_PANEL_MAIL
 * A small IMAP client (RFC 3501 subset) - enough to read a Hostinger mailbox:
 * LOGIN, LIST, SELECT, UID SEARCH, UID FETCH, UID STORE, APPEND, LOGOUT.
 * Works over any stream (TLS socket in real use, a socket pair in tests).
 */
class ImapClient
{
    /** @var resource|null */
    private $stream = null;

    private int $tag = 0;

    public function __construct(private readonly int $timeout = 30)
    {
    }

    public static function connect(string $host, int $port, string $encryption = 'ssl', int $timeout = 30): self
    {
        $client = new self($timeout);
        $scheme = $encryption === 'ssl' ? 'ssl' : 'tcp';
        $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true, 'peer_name' => $host]]);
        $errno = 0;
        $errstr = '';
        $stream = @stream_socket_client("{$scheme}://{$host}:{$port}", $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $ctx);
        if (!$stream) {
            throw new RuntimeException("Cannot reach the mail server {$host}:{$port} ({$errstr}).");
        }
        $client->useStream($stream);
        $greeting = $client->readLine();
        if (!str_starts_with($greeting, '* OK') && !str_starts_with($greeting, '* PREAUTH')) {
            throw new RuntimeException('Mail server did not greet: '.trim($greeting));
        }
        if ($encryption === 'tls') {
            $client->command('STARTTLS');
            if (!stream_socket_enable_crypto($stream, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('STARTTLS failed.');
            }
        }

        return $client;
    }

    /** @param resource $stream */
    public function useStream($stream): void
    {
        $this->stream = $stream;
        stream_set_timeout($stream, $this->timeout);
    }

    public static function quote(string $value): string
    {
        return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
    }

    public function login(string $user, string $password): void
    {
        $r = $this->command('LOGIN '.self::quote($user).' '.self::quote($password), false);
        if ($r['status'] !== 'OK') {
            throw new RuntimeException('Login refused by the mail server - check the e-mail address and password.');
        }
    }

    /** @return array<int, array{name:string, flags:string}> */
    public function listFolders(): array
    {
        $out = [];
        foreach ($this->command('LIST "" "*"')['untagged'] as $line) {
            if (preg_match('/^\* LIST \(([^)]*)\) (?:"[^"]*"|NIL) (.+)$/i', $line['text'], $m)) {
                $name = trim($m[2]);
                if ($name === '' && $line['literals'] !== []) {
                    $name = $line['literals'][0];
                }
                $out[] = ['name' => self::unquote($name), 'flags' => $m[1]];
            }
        }

        return $out;
    }

    /** @return array{exists:int, uidvalidity:int, uidnext:int} */
    public function select(string $folder, bool $readOnly = false): array
    {
        $r = $this->command(($readOnly ? 'EXAMINE ' : 'SELECT ').self::quote($folder));
        $info = ['exists' => 0, 'uidvalidity' => 0, 'uidnext' => 0];
        foreach ($r['untagged'] as $line) {
            if (preg_match('/^\* (\d+) EXISTS/i', $line['text'], $m)) {
                $info['exists'] = (int) $m[1];
            } elseif (preg_match('/UIDVALIDITY (\d+)/i', $line['text'], $m)) {
                $info['uidvalidity'] = (int) $m[1];
            } elseif (preg_match('/UIDNEXT (\d+)/i', $line['text'], $m)) {
                $info['uidnext'] = (int) $m[1];
            }
        }

        return $info;
    }

    /** @return int[] UIDs above $afterUid */
    public function uidsAfter(int $afterUid): array
    {
        $uids = [];
        foreach ($this->command('UID SEARCH UID '.($afterUid + 1).':*')['untagged'] as $line) {
            if (preg_match('/^\* SEARCH ?(.*)$/i', $line['text'], $m)) {
                foreach (preg_split('/\s+/', trim($m[1])) as $u) {
                    if ($u !== '' && (int) $u > $afterUid) {
                        $uids[] = (int) $u;
                    }
                }
            }
        }
        sort($uids);

        return array_values(array_unique($uids));
    }

    /**
     * @return array<int, array{uid:int, flags:string[], size:int, raw:?string}>
     */
    public function fetch(array $uids, bool $withBody = true, bool $headerOnly = false): array
    {
        if ($uids === []) {
            return [];
        }
        $items = $headerOnly ? '(UID FLAGS RFC822.SIZE BODY.PEEK[HEADER])' : ($withBody ? '(UID FLAGS RFC822.SIZE BODY.PEEK[])' : '(UID FLAGS RFC822.SIZE)');
        $withBody = $withBody || $headerOnly;
        $out = [];
        foreach ($this->command('UID FETCH '.implode(',', $uids).' '.$items)['untagged'] as $line) {
            if (!preg_match('/^\* \d+ FETCH/i', $line['text'])) {
                continue;
            }
            preg_match('/UID (\d+)/i', $line['text'], $u);
            preg_match('/FLAGS \(([^)]*)\)/i', $line['text'], $f);
            preg_match('/RFC822\.SIZE (\d+)/i', $line['text'], $s);
            if (!isset($u[1])) {
                continue;
            }
            $out[(int) $u[1]] = [
                'uid' => (int) $u[1],
                'flags' => isset($f[1]) ? preg_split('/\s+/', trim($f[1]), -1, PREG_SPLIT_NO_EMPTY) : [],
                'size' => isset($s[1]) ? (int) $s[1] : 0,
                'raw' => $withBody ? ($line['literals'][0] ?? null) : null,
            ];
        }
        ksort($out);

        return array_values($out);
    }

    public function addFlags(int $uid, string $flags): void
    {
        $this->command("UID STORE {$uid} +FLAGS.SILENT ({$flags})");
    }

    /* W110: take a flag away again (star off = \\Flagged removed) */
    public function removeFlags(int $uid, string $flags): void
    {
        $this->command("UID STORE {$uid} -FLAGS.SILENT ({$flags})");
    }

    public function append(string $folder, string $raw, string $flags = '\\Seen'): void
    {
        $raw = preg_replace("/(?<!\r)\n/", "\r\n", $raw);
        $this->tag++;
        $tag = 'A'.str_pad((string) $this->tag, 4, '0', STR_PAD_LEFT);
        $this->write("{$tag} APPEND ".self::quote($folder)." ({$flags}) {".strlen($raw)."}\r\n");
        $cont = $this->readLine();
        if (!str_starts_with($cont, '+')) {
            throw new RuntimeException('Mail server refused to save the sent copy: '.trim($cont));
        }
        $this->write($raw."\r\n");
        $r = $this->readUntilTagged($tag);
        if ($r['status'] !== 'OK') {
            throw new RuntimeException('Mail server refused to save the sent copy: '.$r['text']);
        }
    }

    public function logout(): void
    {
        if ($this->stream) {
            try {
                $this->command('LOGOUT', false);
            } catch (\Throwable) {
            }
            @fclose($this->stream);
            $this->stream = null;
        }
    }

    /** @return array{status:string, text:string, untagged:array<int, array{text:string, literals:string[]}>} */
    public function command(string $command, bool $mustBeOk = true): array
    {
        $this->tag++;
        $tag = 'A'.str_pad((string) $this->tag, 4, '0', STR_PAD_LEFT);
        $this->write("{$tag} {$command}\r\n");
        $r = $this->readUntilTagged($tag);
        if ($mustBeOk && $r['status'] !== 'OK') {
            $shown = preg_replace('/^LOGIN .*/i', 'LOGIN ***', $command);
            throw new RuntimeException("Mail server answered {$r['status']} to {$shown}: {$r['text']}");
        }

        return $r;
    }

    private function readUntilTagged(string $tag): array
    {
        $untagged = [];
        while (true) {
            [$text, $literals] = $this->readResponseLine();
            if (str_starts_with($text, $tag.' ')) {
                $rest = substr($text, strlen($tag) + 1);
                [$status, $msg] = array_pad(explode(' ', $rest, 2), 2, '');

                return ['status' => strtoupper($status), 'text' => trim($msg), 'untagged' => $untagged];
            }
            if (str_starts_with($text, '*')) {
                $untagged[] = ['text' => $text, 'literals' => $literals];
            }
        }
    }

    /** one logical response line: literals {n} are read and kept apart */
    private function readResponseLine(): array
    {
        $text = '';
        $literals = [];
        while (true) {
            $line = $this->readLine();
            if (preg_match('/\{(\d+)\}\r\n$/', $line, $m)) {
                $text .= substr($line, 0, -strlen($m[0])).'{'.count($literals).'}';
                $literals[] = $this->readBytes((int) $m[1]);
                continue;
            }
            $text .= rtrim($line, "\r\n");

            return [$text, $literals];
        }
    }

    private function readLine(): string
    {
        $line = fgets($this->stream);
        if ($line === false) {
            $meta = stream_get_meta_data($this->stream);
            throw new RuntimeException($meta['timed_out'] ?? false ? 'Mail server did not answer in time.' : 'Connection to the mail server closed.');
        }

        return $line;
    }

    private function readBytes(int $n): string
    {
        $buf = '';
        while (strlen($buf) < $n) {
            $chunk = fread($this->stream, min(65536, $n - strlen($buf)));
            if ($chunk === false || $chunk === '') {
                $meta = stream_get_meta_data($this->stream);
                if ($meta['eof'] || $meta['timed_out']) {
                    throw new RuntimeException('Connection to the mail server closed while reading a message.');
                }
                continue;
            }
            $buf .= $chunk;
        }

        return $buf;
    }

    private function write(string $data): void
    {
        $len = strlen($data);
        $done = 0;
        while ($done < $len) {
            $w = fwrite($this->stream, substr($data, $done));
            if ($w === false || $w === 0) {
                throw new RuntimeException('Cannot write to the mail server.');
            }
            $done += $w;
        }
    }

    private static function unquote(string $name): string
    {
        if (strlen($name) >= 2 && $name[0] === '"' && substr($name, -1) === '"') {
            return stripcslashes(substr($name, 1, -1));
        }

        return $name;
    }
}
