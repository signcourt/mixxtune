<?php

namespace Tests\Feature\Mail;

use App\Models\Mail\MailAccount;
use App\Models\Mail\MailMessage;
use App\Models\User;
use App\Services\V2\Mail\ImapClient;
use App\Services\V2\Mail\MailConnector;
use App\Services\V2\Mail\MailSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;
use Tests\Concerns\CreatesProductionOnlyTables;
use Tests\TestCase;

/* a mailbox in memory: what IMAP would give and what SMTP / APPEND received */
class FakeImap extends ImapClient
{
    public static array $folders = [];
    public static array $appended = [];
    public static array $flagged = [];
    private string $sel = 'INBOX';

    public function listFolders(): array
    {
        return [['name' => 'INBOX', 'flags' => '\\HasNoChildren'], ['name' => 'INBOX.Sent', 'flags' => '\\HasNoChildren \\Sent']];
    }

    public function select(string $folder, bool $readOnly = false): array
    {
        $this->sel = $folder;

        return ['exists' => count(self::$folders[$folder] ?? []), 'uidvalidity' => 9, 'uidnext' => 99];
    }

    public function uidsAfter(int $afterUid): array
    {
        return array_values(array_filter(array_keys(self::$folders[$this->sel] ?? []), fn ($u) => $u > $afterUid));
    }

    public function fetch(array $uids, bool $withBody = true, bool $headerOnly = false): array
    {
        return array_map(fn ($u) => ['uid' => $u, 'flags' => [], 'size' => strlen(self::$folders[$this->sel][$u]), 'raw' => $withBody || $headerOnly ? self::$folders[$this->sel][$u] : null], $uids);
    }

    public function addFlags(int $uid, string $flags): void
    {
        self::$flagged[] = $uid;
    }

    public function append(string $folder, string $raw, string $flags = '\\Seen'): void
    {
        self::$appended[] = [$folder, $raw];
    }

    public function logout(): void
    {
    }
}

class FakeTransport implements TransportInterface
{
    public static array $sent = [];

    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        self::$sent[] = $message->toString();

        return null;
    }

    public function __toString(): string
    {
        return 'fake://';
    }
}

class FakeConnector extends MailConnector
{
    public static bool $loginWorks = true;

    public function imap(MailAccount $account): ImapClient
    {
        if (!self::$loginWorks) {
            throw new \RuntimeException('Login refused by the mail server - check the e-mail address and password.');
        }

        return new FakeImap();
    }

    public function transport(MailAccount $account): TransportInterface
    {
        return new FakeTransport();
    }
}

/* W95 MIXXTUNE_PANEL_MAIL (generated test data only) */
class PanelMailTest extends TestCase
{
    use RefreshDatabase;
    use CreatesProductionOnlyTables;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');
        $this->createProductionOnlyTables();
        FakeImap::$folders = ['INBOX' => [], 'INBOX.Sent' => []];
        FakeImap::$appended = [];
        FakeImap::$flagged = [];
        FakeTransport::$sent = [];
        FakeConnector::$loginWorks = true;
        $this->app->instance(MailConnector::class, new FakeConnector());
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role, 'account_status' => 'active', 'email_verified_at' => now()]);
    }

    private function mail(string $subject, string $from = 'Saras <saras@example.com>', string $extra = ''): string
    {
        return "From: {$from}\r\nTo: manager1@mixxtune.com\r\nSubject: {$subject}\r\nDate: Thu, 22 Oct 2026 10:00:00 +0530\r\nMessage-ID: <".md5($subject)."@example.com>\r\nMIME-Version: 1.0\r\n{$extra}";
    }

    public function test_super_admin_adds_a_mailbox_only_when_the_login_works(): void
    {
        $super = $this->user('super_admin');
        $manager = $this->user('admin');

        FakeConnector::$loginWorks = false;
        $this->actingAs($super)->post('/admin/mail-accounts', ['address' => 'Manager1@mixxtune.com', 'password' => 'p@ss"1'])->assertSessionHasErrors('password');
        $this->assertSame(0, MailAccount::query()->count());

        FakeConnector::$loginWorks = true;
        $this->actingAs($super)->post('/admin/mail-accounts', ['address' => 'Manager1@mixxtune.com', 'password' => 'p@ss"1', 'display_name' => 'Mixx Tune', 'user_ids' => [$manager->id]])->assertSessionHasNoErrors();
        $a = MailAccount::query()->firstOrFail();
        $this->assertSame(['manager1@mixxtune.com', 'INBOX.Sent', 'p@ss"1'], [$a->address, $a->sent_folder, $a->password]);
        $this->assertNotSame('p@ss"1', DB::table('mail_accounts')->value('password'), 'password must be stored encrypted');
        $this->assertSame([$manager->id], $a->users()->pluck('users.id')->all());

        /* the same address twice is refused; managers cannot manage mailboxes */
        $this->actingAs($super)->post('/admin/mail-accounts', ['address' => 'manager1@mixxtune.com', 'password' => 'x'])->assertSessionHasErrors('address');
        $this->actingAs($manager)->get('/admin/mail-accounts')->assertForbidden();
        $this->actingAs($manager)->post('/admin/mail-accounts', ['address' => 'x@mixxtune.com', 'password' => 'x'])->assertForbidden();

        /* remove needs the address typed */
        $this->actingAs($super)->post("/admin/mail-accounts/{$a->id}/delete", ['confirm' => 'wrong'])->assertSessionHasErrors('confirm');
        $this->actingAs($super)->post("/admin/mail-accounts/{$a->id}/delete", ['confirm' => 'manager1@mixxtune.com'])->assertSessionHasNoErrors();
        $this->assertSame(0, MailAccount::query()->count());
    }

    public function test_mail_is_read_once_with_attachments_and_only_the_right_people_see_it(): void
    {
        $super = $this->user('super_admin');
        $mine = $this->user('admin');
        $other = $this->user('admin');
        $label = $this->user('label');
        $a = MailAccount::query()->create(['address' => 'manager1@mixxtune.com', 'username' => 'manager1@mixxtune.com', 'password' => 'x', 'status' => 'active']);
        $a->users()->sync([$mine->id]);

        FakeImap::$folders['INBOX'] = [
            1 => $this->mail('Payment query', 'Saras <saras@example.com>', "Content-Type: text/plain; charset=utf-8\r\n\r\nJuly payment pending."),
            2 => $this->mail('With file', 'Maa <maa@example.com>', "Content-Type: multipart/mixed; boundary=\"b1\"\r\n\r\n--b1\r\nContent-Type: text/html; charset=utf-8\r\n\r\n<p onclick=\"x()\">Hi <script>alert(1)</script><b>team</b></p>\r\n--b1\r\nContent-Type: application/pdf; name=\"report.pdf\"\r\nContent-Disposition: attachment; filename=\"report.pdf\"\r\nContent-Transfer-Encoding: base64\r\n\r\nJVBERi0xLjQ=\r\n--b1--\r\n"),
        ];
        $sync = app(MailSync::class);
        $this->assertSame(['inbox' => 2, 'sent' => 0], $sync->sync($a->fresh()));
        $this->assertSame(['inbox' => 0, 'sent' => 0], $sync->sync($a->fresh()));
        $this->assertSame(2, MailMessage::query()->count());
        $file = MailMessage::query()->where('subject', 'With file')->firstOrFail();
        $att = $file->attachments()->firstOrFail();
        Storage::disk('local')->assertExists($att->path);

        /* the manager it is given to */
        $this->actingAs($mine)->get('/admin/dashboard')->assertInertia(fn (Assert $p) => $p->where('auth.mail.accounts', 1)->where('auth.mail.unread', 2));
        $this->actingAs($mine)->get("/mail?account={$a->id}&m={$file->id}")->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('V2/Mail/Index')->where('messages.total', 2)->where('open.subject', 'With file')->where('open.has_html', true)->where('open.attachments.0.filename', 'report.pdf'));
        $this->assertTrue($file->fresh()->seen);
        $html = $this->actingAs($mine)->get("/mail/messages/{$file->id}/html")->assertOk();
        $this->assertStringContainsString('<b>team</b>', $html->getContent());
        $this->assertStringNotContainsString('<script', $html->getContent());
        $this->assertStringNotContainsString('onclick', $html->getContent());
        $this->assertStringContainsString("default-src 'none'", $html->headers->get('Content-Security-Policy'));
        $this->actingAs($mine)->get("/mail/attachments/{$att->id}")->assertOk()->assertDownload('report.pdf');
        $this->actingAs($mine)->get('/mail?q=payment')->assertInertia(fn (Assert $p) => $p->where('messages.total', 1));

        /* Super Admin sees every mailbox */
        $this->actingAs($super)->get('/mail')->assertInertia(fn (Assert $p) => $p->where('accounts.0.address', 'manager1@mixxtune.com'));

        /* another manager and a label: nothing */
        $this->actingAs($other)->get('/mail')->assertInertia(fn (Assert $p) => $p->where('accounts', []));
        $this->actingAs($other)->get("/mail/messages/{$file->id}/html")->assertForbidden();
        $this->actingAs($other)->get("/mail/attachments/{$att->id}")->assertForbidden();
        $this->actingAs($label)->get("/mail/messages/{$file->id}/html")->assertForbidden();
        $this->actingAs($other)->post("/mail/accounts/{$a->id}/send", ['to' => 'x@example.com', 'subject' => 's', 'body' => 'b'])->assertForbidden();
    }

    public function test_reply_is_sent_saved_in_sent_and_not_doubled_when_read_back(): void
    {
        $mine = $this->user('admin');
        $a = MailAccount::query()->create(['address' => 'manager1@mixxtune.com', 'display_name' => 'Mixx Tune', 'username' => 'manager1@mixxtune.com', 'password' => 'x', 'status' => 'active', 'sent_folder' => 'INBOX.Sent', 'signature' => 'Regards, Mixx Tune']);
        $a->users()->sync([$mine->id]);
        FakeImap::$folders['INBOX'] = [7 => $this->mail('Payment query', 'Saras <saras@example.com>', "Content-Type: text/plain\r\n\r\nWhen?")];
        app(MailSync::class)->sync($a);
        $orig = MailMessage::query()->firstOrFail();

        /* bad address */
        $this->actingAs($mine)->post("/mail/accounts/{$a->id}/send", ['to' => 'not-an-address', 'subject' => 'Re: Payment query', 'body' => 'Soon'])->assertSessionHasErrors('to_list.0');
        $this->assertSame([], FakeTransport::$sent);

        $this->actingAs($mine)->post("/mail/accounts/{$a->id}/send", [
            'to' => 'saras@example.com', 'cc' => 'boss@example.com; ', 'subject' => 'Re: Payment query', 'body' => "Payment on Monday.\n> When?",
            'reply_to_id' => $orig->id, 'files' => [UploadedFile::fake()->create('invoice.pdf', 30, 'application/pdf')],
        ])->assertSessionHasNoErrors()->assertRedirectContains('folder=sent');

        $this->assertCount(1, FakeTransport::$sent);
        $raw = FakeTransport::$sent[0];
        $this->assertStringContainsString('From: Mixx Tune <manager1@mixxtune.com>', $raw);
        $this->assertStringContainsString('Cc: boss@example.com', $raw);
        $this->assertStringContainsString('In-Reply-To: '.$orig->message_id, $raw);
        $this->assertStringContainsString('Regards, Mixx Tune', $raw);
        $this->assertStringContainsString('invoice.pdf', $raw);
        $this->assertCount(1, FakeImap::$appended);
        $this->assertSame('INBOX.Sent', FakeImap::$appended[0][0]);

        $sent = MailMessage::query()->where('folder', 'sent')->firstOrFail();
        $this->assertNull($sent->uid);
        $this->assertSame(1, $sent->attachments()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'mail.sent']);

        /* the copy in the Sent folder comes back from the server: linked, not doubled */
        FakeImap::$folders['INBOX.Sent'] = [3 => FakeImap::$appended[0][1]];
        app(MailSync::class)->sync($a->fresh());
        $this->assertSame(1, MailMessage::query()->where('folder', 'sent')->count());
        $this->assertSame(3, (int) $sent->fresh()->uid);
    }

    public function test_imap_client_reads_literals_and_quotes_passwords(): void
    {
        [$client, $server] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        $raw = "Subject: Hi\r\n\r\nLine one\r\n";
        fwrite($server, "A0001 OK LOGIN done\r\n");
        fwrite($server, "* 1 EXISTS\r\n* OK [UIDVALIDITY 5] ok\r\nA0002 OK done\r\n");
        fwrite($server, "* SEARCH 4 9\r\nA0003 OK done\r\n");
        fwrite($server, '* 1 FETCH (UID 9 FLAGS (\\Seen) RFC822.SIZE '.strlen($raw).' BODY[] {'.strlen($raw)."}\r\n".$raw.")\r\nA0004 OK done\r\n");
        $imap = new ImapClient(5);
        $imap->useStream($client);
        $imap->login('a@b.c', 'pa"ss\\w');
        $this->assertSame(['exists' => 1, 'uidvalidity' => 5, 'uidnext' => 0], $imap->select('INBOX'));
        $this->assertSame([9], $imap->uidsAfter(4));
        $rows = $imap->fetch([9]);
        $this->assertSame([9, ['\\Seen'], $raw], [$rows[0]['uid'], $rows[0]['flags'], $rows[0]['raw']]);
        $sent = fread($server, 4096);
        $this->assertStringContainsString('A0001 LOGIN "a@b.c" "pa\\"ss\\\\w"', $sent);
        $this->assertStringContainsString('A0003 UID SEARCH UID 5:*', $sent);
    }

    /* W96: forward carries the attachments of the original; the bell rings for new mail (not on the first read) */
    public function test_forward_keeps_attachments_and_new_mail_rings_the_bell(): void
    {
        $mine = $this->user('admin');
        $a = MailAccount::query()->create(['address' => 'manager1@mixxtune.com', 'username' => 'manager1@mixxtune.com', 'password' => 'x', 'status' => 'active', 'sent_folder' => 'INBOX.Sent']);
        $a->users()->sync([$mine->id]);
        FakeImap::$folders['INBOX'] = [1 => $this->mail('With file', 'Maa <maa@example.com>', "Content-Type: multipart/mixed; boundary=\"b1\"\r\n\r\n--b1\r\nContent-Type: text/plain\r\n\r\nSee file\r\n--b1\r\nContent-Type: application/pdf; name=\"report.pdf\"\r\nContent-Disposition: attachment; filename=\"report.pdf\"\r\nContent-Transfer-Encoding: base64\r\n\r\nJVBERi0xLjQ=\r\n--b1--\r\n")];
        $this->artisan('mail:sync')->assertSuccessful();
        $this->assertSame(0, DB::table('panel_notifications')->count(), 'first read of a mailbox: no bell');

        $orig = MailMessage::query()->firstOrFail();
        $att = $orig->attachments()->firstOrFail();
        $other = MailAccount::query()->create(['address' => 'other@mixxtune.com', 'username' => 'other@mixxtune.com', 'password' => 'x', 'status' => 'active']);
        $this->actingAs($mine)->post("/mail/accounts/{$other->id}/send", ['to' => 'x@example.com', 'subject' => 'Fwd', 'body' => 'b', 'forward_of' => $orig->id, 'keep_attachments' => [$att->id]])->assertForbidden();

        $this->actingAs($mine)->post("/mail/accounts/{$a->id}/send", [
            'to' => 'boss@example.com', 'subject' => 'Fwd: With file', 'body' => "**Please check** _today_\n- one\n- two\nhttps://www.mixxtune.com/x",
            'forward_of' => $orig->id, 'keep_attachments' => [$att->id, 999999],
        ])->assertSessionHasNoErrors();
        $raw = FakeTransport::$sent[0];
        $this->assertStringContainsString('report.pdf', $raw);
        $this->assertStringContainsString('JVBERi0xLjQ=', $raw);
        $sent = MailMessage::query()->where('folder', 'sent')->firstOrFail();
        $this->assertSame(['report.pdf'], $sent->attachments()->pluck('filename')->all());
        Storage::disk('local')->assertExists($sent->attachments()->first()->path);
        Storage::disk('local')->assertExists($att->path);

        $html = \App\Services\V2\Mail\MailSender::html("**Please check** _today_ <script>\n- one\n- two\n> old\nhttps://www.mixxtune.com/x.");
        $this->assertStringContainsString('<b>Please check</b> <i>today</i> &lt;script&gt;', $html);
        $this->assertStringContainsString('<li>one</li><li>two</li></ul>', $html);
        $this->assertStringContainsString('<a href="https://www.mixxtune.com/x">https://www.mixxtune.com/x</a>.', $html);
        $this->assertStringContainsString('border-left:3px solid #ccc', $html);

        /* a new mail arrives: the manager gets one bell */
        FakeImap::$folders['INBOX'][2] = $this->mail('Payment query', 'Saras <saras@example.com>', "Content-Type: text/plain\r\n\r\nWhen?");
        $this->artisan('mail:sync')->assertSuccessful();
        $bell = DB::table('panel_notifications')->where('type', 'mail.new')->get();
        $this->assertCount(1, $bell);
        $this->assertSame($mine->id, (int) $bell[0]->user_id);
        $this->assertSame('New mail in manager1@mixxtune.com', $bell[0]->title);
        $this->assertStringContainsString('Saras: Payment query', $bell[0]->message);
        $this->assertStringStartsWith("/mail?account={$a->id}&m=", $bell[0]->action_url);
    }
}
