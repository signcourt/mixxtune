<?php

namespace App\Services\V2\Mail;

/*
 * W95 MIXXTUNE_PANEL_MAIL
 * Reads a raw e-mail (RFC 5322 / MIME): headers, the text and html body
 * (any charset -> UTF-8), and the attachments (base64 / quoted-printable).
 */
class MimeParser
{
    /**
     * @return array{subject:string, from:array{name:string,email:string}, to:array, cc:array, reply_to:array,
     *               date:?string, message_id:?string, in_reply_to:?string, references:?string,
     *               text:string, html:string, attachments:array<int, array{filename:string, mime:string, content:string, cid:?string, inline:bool}>}
     */
    public function parse(string $raw): array
    {
        [$headers, $body] = $this->split($raw);
        $h = $this->headers($headers);
        $out = ['text' => '', 'html' => '', 'attachments' => []];
        $this->walk($h, $body, $out);

        $from = $this->addresses($h['from'][0] ?? '');
        $date = null;
        if (!empty($h['date'][0])) {
            $ts = strtotime(preg_replace('/\s*\([^)]*\)\s*$/', '', $h['date'][0]));
            $date = $ts ? gmdate('Y-m-d H:i:s', $ts) : null;
        }

        return [
            'subject' => $this->decodeHeader($h['subject'][0] ?? ''),
            'from' => $from[0] ?? ['name' => '', 'email' => ''],
            'to' => $this->addresses(implode(', ', $h['to'] ?? [])),
            'cc' => $this->addresses(implode(', ', $h['cc'] ?? [])),
            'reply_to' => $this->addresses(implode(', ', $h['reply-to'] ?? [])),
            'date' => $date,
            'message_id' => isset($h['message-id'][0]) ? trim($h['message-id'][0]) : null,
            'in_reply_to' => isset($h['in-reply-to'][0]) ? trim($h['in-reply-to'][0]) : null,
            'references' => isset($h['references'][0]) ? trim(preg_replace('/\s+/', ' ', $h['references'][0])) : null,
            'text' => $out['text'],
            'html' => $out['html'],
            'attachments' => $out['attachments'],
        ];
    }

    private function split(string $raw): array
    {
        $raw = str_replace("\r\n", "\n", $raw);
        $pos = strpos($raw, "\n\n");
        if ($pos === false) {
            return [$raw, ''];
        }

        return [substr($raw, 0, $pos), substr($raw, $pos + 2)];
    }

    /** @return array<string, string[]> lower-case name => values (unfolded) */
    private function headers(string $block): array
    {
        $out = [];
        $block = preg_replace("/\n[ \t]+/", ' ', $block);
        foreach (explode("\n", $block) as $line) {
            $p = strpos($line, ':');
            if ($p === false) {
                continue;
            }
            $out[strtolower(trim(substr($line, 0, $p)))][] = trim(substr($line, $p + 1));
        }

        return $out;
    }

    /** value; params (lower-case keys), RFC 2231 name*= decoded */
    private function params(string $header): array
    {
        $parts = $this->splitOutsideQuotes($header, ';');
        $value = strtolower(trim(array_shift($parts) ?? ''));
        $params = [];
        $continued = [];
        foreach ($parts as $part) {
            $eq = strpos($part, '=');
            if ($eq === false) {
                continue;
            }
            $k = strtolower(trim(substr($part, 0, $eq)));
            $v = trim(substr($part, $eq + 1));
            if (strlen($v) >= 2 && $v[0] === '"' && substr($v, -1) === '"') {
                $v = stripcslashes(substr($v, 1, -1));
            }
            if (preg_match('/^([a-z0-9_-]+)\*(\d+)?(\*)?$/', $k, $m)) {
                $continued[$m[1]][(int) ($m[2] ?? 0)] = [$v, isset($m[3]) || (!isset($m[2]) && str_ends_with($k, '*'))];
                continue;
            }
            $params[$k] = $this->decodeHeader($v);
        }
        foreach ($continued as $name => $pieces) {
            ksort($pieces);
            $joined = '';
            $charset = 'UTF-8';
            foreach ($pieces as $i => [$v, $encoded]) {
                if ($encoded) {
                    if ($i === array_key_first($pieces) && preg_match("/^([^']*)'[^']*'(.*)$/", $v, $m)) {
                        $charset = $m[1] ?: 'UTF-8';
                        $v = $m[2];
                    }
                    $v = rawurldecode($v);
                }
                $joined .= $v;
            }
            $params[$name] = $this->toUtf8($joined, $charset);
        }

        return [$value, $params];
    }

    private function walk(array $h, string $body, array &$out, int $depth = 0): void
    {
        if ($depth > 12) {
            return;
        }
        [$type, $tp] = $this->params($h['content-type'][0] ?? 'text/plain; charset=us-ascii');
        $type = $type ?: 'text/plain';
        [$disp, $dp] = $this->params($h['content-disposition'][0] ?? '');
        $enc = strtolower(trim($h['content-transfer-encoding'][0] ?? '7bit'));

        if (str_starts_with($type, 'multipart/') && !empty($tp['boundary'])) {
            foreach ($this->parts($body, $tp['boundary']) as $part) {
                [$ph, $pb] = $this->split($part);
                $this->walk($this->headers($ph), $pb, $out, $depth + 1);
            }

            return;
        }

        $data = $this->decodeBody($body, $enc);
        $filename = $dp['filename'] ?? ($tp['name'] ?? null);
        $isAttachment = $disp === 'attachment' || ($filename !== null && !in_array($type, ['text/plain', 'text/html'], true)) || ($filename !== null && $disp === 'attachment');

        if ($type === 'message/rfc822' && !$isAttachment) {
            $isAttachment = true;
            $filename = $filename ?: 'attached-message.eml';
        }

        if (!$isAttachment && $type === 'text/plain' && $out['text'] === '') {
            $out['text'] = $this->toUtf8($data, $tp['charset'] ?? 'us-ascii');

            return;
        }
        if (!$isAttachment && $type === 'text/html' && $out['html'] === '') {
            $out['html'] = $this->toUtf8($data, $tp['charset'] ?? 'us-ascii');

            return;
        }
        if ($isAttachment || !str_starts_with($type, 'text/')) {
            $cid = isset($h['content-id'][0]) ? trim($h['content-id'][0], " <>") : null;
            $out['attachments'][] = [
                'filename' => $this->safeName($filename ?: ('part-'.(count($out['attachments']) + 1).$this->ext($type))),
                'mime' => $type,
                'content' => $data,
                'cid' => $cid,
                'inline' => $disp === 'inline' || ($disp === '' && $cid !== null),
            ];
        }
    }

    /** @return string[] */
    private function parts(string $body, string $boundary): array
    {
        $parts = [];
        $chunks = preg_split('/^--'.preg_quote($boundary, '/').'(--)?[ \t]*$/m', $body);
        array_shift($chunks); /* preamble */
        foreach ($chunks as $i => $chunk) {
            if ($i === count($chunks) - 1 && trim($chunk) === '') {
                break; /* epilogue */
            }
            $parts[] = preg_replace('/^\n/', '', preg_replace('/\n$/', '', $chunk));
        }

        return $parts;
    }

    private function decodeBody(string $body, string $enc): string
    {
        return match ($enc) {
            'base64' => (string) base64_decode(preg_replace('/[^A-Za-z0-9+\/=]/', '', $body)),
            'quoted-printable' => quoted_printable_decode(str_replace("\n", "\r\n", $body)),
            default => $body,
        };
    }

    public function toUtf8(string $s, ?string $charset): string
    {
        $charset = strtoupper(trim((string) $charset, " \"'")) ?: 'US-ASCII';
        if (in_array($charset, ['UTF-8', 'UTF8', 'US-ASCII', 'ASCII'], true)) {
            return mb_check_encoding($s, 'UTF-8') ? $s : mb_convert_encoding($s, 'UTF-8', 'ISO-8859-1');
        }
        $aliases = ['WINDOWS-1252' => 'Windows-1252', 'CP1252' => 'Windows-1252', 'LATIN1' => 'ISO-8859-1', 'KS_C_5601-1987' => 'UHC', 'GB2312' => 'GB18030', 'GBK' => 'GB18030'];
        $cs = $aliases[$charset] ?? $charset;
        if (in_array(strtolower($cs), array_map('strtolower', mb_list_encodings()), true)) {
            return mb_convert_encoding($s, 'UTF-8', $cs);
        }
        $c = @iconv($cs, 'UTF-8//IGNORE', $s);

        return $c !== false ? $c : mb_convert_encoding($s, 'UTF-8', 'ISO-8859-1');
    }

    /** =?charset?B|Q?...?= words -> UTF-8 */
    public function decodeHeader(string $v): string
    {
        $v = preg_replace('/(=\?[^?]+\?[BbQq]\?[^?]*\?=)\s+(?==\?)/', '$1', $v);

        return trim(preg_replace_callback('/=\?([^?]+)\?([BbQq])\?([^?]*)\?=/', function ($m) {
            $charset = preg_replace('/\*.*$/', '', $m[1]);
            $text = strtoupper($m[2]) === 'B' ? base64_decode($m[3]) : quoted_printable_decode(str_replace('_', ' ', $m[3]));

            return $this->toUtf8((string) $text, $charset);
        }, $v));
    }

    /** "A <a@x>, b@y" -> [['name'=>'A','email'=>'a@x'], ...] */
    public function addresses(string $list): array
    {
        $out = [];
        foreach ($this->splitOutsideQuotes($list, ',') as $item) {
            $item = trim($item);
            if ($item === '') {
                continue;
            }
            if (preg_match('/^(.*)<([^>]+)>\s*$/s', $item, $m)) {
                $name = trim($m[1], " \t\"'");
                $email = trim($m[2]);
            } else {
                $name = '';
                $email = trim($item, " <>\"'");
            }
            if (!str_contains($email, '@')) {
                continue;
            }
            $out[] = ['name' => $this->decodeHeader(stripcslashes($name)), 'email' => strtolower($email)];
        }

        return $out;
    }

    private function splitOutsideQuotes(string $s, string $sep): array
    {
        $out = [];
        $cur = '';
        $q = false;
        $angle = 0;
        $len = strlen($s);
        for ($i = 0; $i < $len; $i++) {
            $c = $s[$i];
            if ($c === '\\' && $i + 1 < $len) {
                $cur .= $c.$s[++$i];
                continue;
            }
            if ($c === '"') {
                $q = !$q;
            } elseif (!$q && $c === '<') {
                $angle++;
            } elseif (!$q && $c === '>') {
                $angle = max(0, $angle - 1);
            }
            if ($c === $sep && !$q && $angle === 0) {
                $out[] = $cur;
                $cur = '';
                continue;
            }
            $cur .= $c;
        }
        $out[] = $cur;

        return $out;
    }

    private function safeName(string $name): string
    {
        $name = preg_replace('/[\x00-\x1F\/\\\\:*?"<>|]+/u', '_', $name);
        $name = trim($name, " .");

        return mb_substr($name !== '' ? $name : 'attachment', 0, 180);
    }

    private function ext(string $type): string
    {
        return ['image/png' => '.png', 'image/jpeg' => '.jpg', 'image/gif' => '.gif', 'application/pdf' => '.pdf', 'text/calendar' => '.ics'][$type] ?? '.bin';
    }
}
