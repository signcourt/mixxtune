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

    /* W100: a Super Admin's own private mailbox - first for him, hidden from the other Super Admin */
    public function test_super_admin_has_his_own_private_mailbox(): void
    {
        $boss = $this->user('super_admin');
        $other = $this->user('super_admin');
        $manager = $this->user('admin');
        $team = MailAccount::query()->create(['address' => 'manager1@mixxtune.com', 'username' => 'manager1@mixxtune.com', 'password' => 'x', 'status' => 'active']);
        $team->users()->sync([$manager->id]);

        /* private needs people, and his own login check passes */
        $this->actingAs($boss)->post('/admin/mail-accounts', ['address' => 'boss@mixxtune.com', 'password' => 'p', 'is_private' => true])->assertSessionHasErrors('user_ids');
        $this->actingAs($boss)->post('/admin/mail-accounts', ['address' => 'boss@mixxtune.com', 'password' => 'p', 'is_private' => true, 'user_ids' => [$boss->id]])->assertSessionHasNoErrors();
        $own = MailAccount::query()->where('address', 'boss@mixxtune.com')->firstOrFail();
        $this->assertTrue($own->is_private);
        DB::table('mail_messages')->insert([
            ['mail_account_id' => $own->id, 'folder' => 'inbox', 'uid' => 1, 'subject' => 'For the boss', 'seen' => false, 'has_attachments' => false, 'created_at' => now(), 'updated_at' => now()],
            ['mail_account_id' => $team->id, 'folder' => 'inbox', 'uid' => 1, 'subject' => 'For the team', 'seen' => false, 'has_attachments' => false, 'created_at' => now(), 'updated_at' => now()],
            ['mail_account_id' => $team->id, 'folder' => 'inbox', 'uid' => 2, 'subject' => 'For the team 2', 'seen' => false, 'has_attachments' => false, 'created_at' => now(), 'updated_at' => now()],
        ]);

        /* the boss: own mailbox first and opened by default, team mailbox below; badge = own unread */
        $this->actingAs($boss)->get('/mail')->assertInertia(fn (Assert $p) => $p
            ->where('current', $own->id)
            ->where('accounts.0.address', 'boss@mixxtune.com')->where('accounts.0.mine', true)->where('accounts.0.is_private', true)
            ->where('accounts.1.address', 'manager1@mixxtune.com')->where('accounts.1.mine', false)
            ->where('auth.mail.accounts', 2)->where('auth.mail.mine', 1)->where('auth.mail.unread', 1));
        $this->actingAs($boss)->get('/mail?account='.$team->id)->assertInertia(fn (Assert $p) => $p->where('current', $team->id));

        /* the other Super Admin: only the team mailbox, cannot open, change or remove the private one */
        $this->actingAs($other)->get('/mail')->assertInertia(fn (Assert $p) => $p->has('accounts', 1)->where('accounts.0.address', 'manager1@mixxtune.com')->where('auth.mail.unread', 2));
        $this->actingAs($other)->get('/mail?account='.$own->id)->assertInertia(fn (Assert $p) => $p->where('current', $team->id));
        $this->actingAs($other)->post("/mail/accounts/{$own->id}/send", ['to' => 'x@example.com', 'subject' => 's', 'body' => 'b'])->assertForbidden();
        $this->actingAs($other)->post("/admin/mail-accounts/{$own->id}", ['user_ids' => [$other->id]])->assertForbidden();
        $this->actingAs($other)->post("/admin/mail-accounts/{$own->id}/delete", ['confirm' => 'boss@mixxtune.com'])->assertForbidden();
        $this->actingAs($other)->get('/admin/mail-accounts')->assertInertia(fn (Assert $p) => $p->where('accounts.0.address', 'boss@mixxtune.com')->where('accounts.0.can_manage', false));

        /* the boss cannot lock himself out of his own private mailbox */
        $this->actingAs($boss)->post("/admin/mail-accounts/{$own->id}", ['user_ids' => [$other->id]])->assertSessionHasErrors('user_ids');
        /* making it shared again lets the other Super Admin see it */
        $this->actingAs($boss)->post("/admin/mail-accounts/{$own->id}", ['is_private' => false])->assertSessionHasNoErrors();
        $this->actingAs($other)->get('/mail')->assertInertia(fn (Assert $p) => $p->has('accounts', 2));
    }

    /* W101: aliases kept per mailbox, From picks one of them, Bcc is sent but stays hidden */
    public function test_send_from_an_alias_with_bcc(): void
    {
        $super = $this->user('super_admin');
        $mine = $this->user('admin');
        $a = MailAccount::query()->create(['address' => 'manager1@mixxtune.com', 'display_name' => 'Mixx Tune', 'username' => 'manager1@mixxtune.com', 'password' => 'x', 'status' => 'active', 'sent_folder' => 'INBOX.Sent']);
        $a->users()->sync([$mine->id]);

        $this->actingAs($super)->post("/admin/mail-accounts/{$a->id}", ['aliases' => [
            ['address' => 'Support@MixxTune.com', 'name' => 'Mixx Tune Support'],
            ['address' => 'manager1@mixxtune.com', 'name' => 'same as the mailbox - dropped'],
            ['address' => 'not-an-email', 'name' => ''],
        ]])->assertSessionHasErrors('aliases.2.address');
        $this->actingAs($super)->post("/admin/mail-accounts/{$a->id}", ['aliases' => [
            ['address' => 'Support@MixxTune.com', 'name' => 'Mixx Tune Support'],
            ['address' => 'manager1@mixxtune.com', 'name' => 'dropped'],
        ]])->assertSessionHasNoErrors();
        $this->assertSame([['address' => 'support@mixxtune.com', 'name' => 'Mixx Tune Support']], $a->fresh()->aliases);

        $this->actingAs($mine)->get('/mail')->assertInertia(fn (Assert $p) => $p
            ->where('accounts.0.from.0.address', 'manager1@mixxtune.com')
            ->where('accounts.0.from.1.address', 'support@mixxtune.com')
            ->where('accounts.0.from.1.name', 'Mixx Tune Support'));

        /* an address that is not this mailbox's is refused */
        $this->actingAs($mine)->post("/mail/accounts/{$a->id}/send", ['from' => 'ceo@other.com', 'to' => 'x@example.com', 'subject' => 's', 'body' => 'b'])->assertSessionHasErrors('from');
        $this->actingAs($mine)->post("/mail/accounts/{$a->id}/send", ['to' => 'x@example.com', 'bcc' => 'bad', 'subject' => 's', 'body' => 'b'])->assertSessionHasErrors('bcc_list.0');
        $this->assertSame([], FakeTransport::$sent);

        $this->actingAs($mine)->post("/mail/accounts/{$a->id}/send", ['from' => 'support@mixxtune.com', 'to' => 'saras@example.com', 'bcc' => 'boss@example.com, owner@example.com', 'subject' => 'Hello', 'body' => 'Hi'])->assertSessionHasNoErrors();
        $raw = FakeTransport::$sent[0];
        $this->assertStringContainsString('From: Mixx Tune Support <support@mixxtune.com>', $raw);
        $this->assertStringContainsString('@mixxtune.com>', $raw);
        $this->assertStringNotContainsString('boss@example.com', $raw, 'Bcc must not be visible in the sent mail');
        $sent = MailMessage::query()->where('folder', 'sent')->firstOrFail();
        $this->assertSame('support@mixxtune.com', $sent->from_email);
        $this->assertSame(['boss@example.com', 'owner@example.com'], collect($sent->bcc)->pluck('email')->all());
        $this->actingAs($mine)->get("/mail?account={$a->id}&folder=sent&m={$sent->id}")->assertInertia(fn (Assert $p) => $p->where('open.bcc', 'boss@example.com, owner@example.com'));
    }
    /* W102: a designed signature pasted as HTML source code goes out as it is, without anything unsafe */
    public function test_html_signature_is_cleaned_and_sent_under_the_mail(): void
    {
        $super = $this->user('super_admin');
        $mine = $this->user('admin');
        $a = MailAccount::query()->create(['address' => 'contact@mixxtune.com', 'display_name' => 'Mixx Tune', 'username' => 'contact@mixxtune.com', 'password' => 'x', 'status' => 'active', 'sent_folder' => 'INBOX.Sent']);
        $a->users()->sync([$mine->id]);

        $code = '<table style="width:500px"><tr><td style="color:#4f4bdc;font-size:20px"><b>Your Name Here</b></td></tr>'
            .'<tr><td>Contact@mixxtune.com<br>+1 (123) 456-7890</td></tr>'
            .'<tr><td style="background:#4f4bdc" onclick="steal()"><img src="https://mixxtune.com/logo.png" alt="mixx tune" onerror="x()"> '
            .'<a href="https://www.mixxtune.com">www.mixxtune.com</a> <a href="javascript:alert(1)">bad</a></td></tr></table><script>alert(1)</script>';
        $this->actingAs($super)->post("/admin/mail-accounts/{$a->id}", ['signature' => 'old text', 'signature_html' => $code, 'signature_bg' => 'pasted'])->assertSessionHasNoErrors();
        $saved = (string) $a->fresh()->signature_html;
        $this->assertStringContainsString('Your Name Here', $saved);
        $this->assertStringContainsString('https://mixxtune.com/logo.png', $saved);
        $this->assertStringContainsString('background:#4f4bdc', $saved);
        foreach (['<script', 'onclick', 'onerror', 'javascript:'] as $bad) {
            $this->assertStringNotContainsString($bad, $saved);
        }

        $this->actingAs($mine)->get('/mail')->assertInertia(fn (Assert $p) => $p->where('accounts.0.signature_html', $saved));
        $this->actingAs($mine)->post("/mail/accounts/{$a->id}/send", ['to' => 'saras@example.com', 'subject' => 'Hello', 'body' => 'Hi **there**'])->assertSessionHasNoErrors();
        $raw = quoted_printable_decode(FakeTransport::$sent[0]);
        $this->assertStringContainsString('https://mixxtune.com/logo.png', $raw);
        $this->assertStringContainsString('<b>there</b>', $raw);
        $this->assertStringNotContainsString('old text', $raw, 'the designed signature replaces the text one');
        $sent = MailMessage::query()->where('folder', 'sent')->firstOrFail();
        $this->assertStringContainsString("-- \nYour Name Here\nContact@mixxtune.com\n+1 (123) 456-7890", $sent->body_text);
        $this->assertStringContainsString('Your Name Here', (string) $sent->body_html);

        /* back to simple text */
        $this->actingAs($super)->post("/admin/mail-accounts/{$a->id}", ['signature' => 'Regards', 'signature_html' => ''])->assertSessionHasNoErrors();
        $this->assertNull($a->fresh()->signature_html);
    }
    /* W103: the rich editor - formatting, a picture inside the text, signature off for one mail */
    public function test_rich_mail_with_picture_and_signature_switched_off(): void
    {
        $mine = $this->user('admin');
        $a = MailAccount::query()->create(['address' => 'contact@mixxtune.com', 'display_name' => 'Mixx Tune', 'username' => 'contact@mixxtune.com', 'password' => 'x', 'status' => 'active', 'sent_folder' => 'INBOX.Sent', 'signature' => 'Team Mixx Tune']);
        $a->users()->sync([$mine->id]);
        $png = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

        /* nothing written at all */
        $this->actingAs($mine)->post("/mail/accounts/{$a->id}/send", ['to' => 'x@example.com', 'subject' => 's', 'body' => '', 'html' => '<br>'])->assertSessionHasErrors('body');

        $html = '<font face="Georgia, serif" size="5">Namaste</font> <b>Saras</b> <u>ji</u>,<br><span style="color: rgb(255, 0, 0);">Red</span>'
            .'<ol><li>one</li><li>two</li></ol><div style="text-align: center;">centre</div>'
            .'<a href="https://www.mixxtune.com" onclick="x()">site</a><img src="data:image/png;base64,'.$png.'" onerror="x()"><script>alert(1)</script>';
        $this->actingAs($mine)->post("/mail/accounts/{$a->id}/send", [
            'to' => 'saras@example.com', 'subject' => 'Rich', 'body' => "Namaste Saras ji,\nRed\none\ntwo\ncentre\nsite", 'html' => $html, 'signature' => '0',
        ])->assertSessionHasNoErrors();
        $raw = FakeTransport::$sent[0];
        $dec = quoted_printable_decode($raw);
        $this->assertStringContainsString('multipart/related', $raw);
        $this->assertMatchesRegularExpression('/Content-ID: <pic1\.[a-z0-9]+@mixxtune\.com>/', $raw);
        $this->assertStringContainsString('src="cid:pic1.', $dec);
        $this->assertStringContainsString('<font face="Georgia, serif" size="5">Namaste</font>', $dec);
        $this->assertStringContainsString('<li>two</li>', $dec);
        $this->assertStringContainsString('text-align: center;', $dec);
        foreach (['<script', 'onclick', 'onerror', 'data:image', 'Team Mixx Tune'] as $bad) {
            $this->assertStringNotContainsString($bad, $dec);
        }
        $sent = MailMessage::query()->where('folder', 'sent')->firstOrFail();
        $this->assertStringContainsString('data:image/png;base64,', (string) $sent->body_html, 'the panel copy keeps the picture');
        $this->assertSame("Namaste Saras ji,\nRed\none\ntwo\ncentre\nsite", $sent->body_text);

        /* signature on (default) */
        $this->actingAs($mine)->post("/mail/accounts/{$a->id}/send", ['to' => 'saras@example.com', 'subject' => 'Two', 'body' => 'Hi', 'html' => 'Hi <i>there</i>'])->assertSessionHasNoErrors();
        $dec2 = quoted_printable_decode(FakeTransport::$sent[1]);
        $this->assertStringContainsString('Hi <i>there</i>', $dec2);
        $this->assertStringContainsString('Team Mixx Tune', $dec2);
    }
    /* W104: the designed signature can be made smaller; Reply / Forward keep the mail's own formatting */
    public function test_signature_size_and_formatted_forward(): void
    {
        $super = $this->user('super_admin');
        $mine = $this->user('admin');
        $a = MailAccount::query()->create(['address' => 'contact@mixxtune.com', 'display_name' => 'Mixx Tune', 'username' => 'contact@mixxtune.com', 'password' => 'x', 'status' => 'active', 'sent_folder' => 'INBOX.Sent']);
        $a->users()->sync([$mine->id]);
        $code = '<table cellpadding="10"><tr><td style="font-size:20px;padding:12px 8px"><img src="https://mixxtune.com/logo.png" width="200" height="100" style="width:200px;border-radius:9px"></td><td style="font-size: 14pt; width: 50%">Kapil Koli</td></tr></table>';

        $this->actingAs($super)->post("/admin/mail-accounts/{$a->id}", ['signature_html' => $code, 'signature_scale' => 20])->assertSessionHasErrors('signature_scale');
        $this->actingAs($super)->post("/admin/mail-accounts/{$a->id}", ['signature_html' => $code, 'signature_scale' => 50, 'signature_bg' => 'pasted'])->assertSessionHasNoErrors();
        $this->assertSame(50, $a->fresh()->signature_scale);
        $this->assertStringContainsString('width="200"', (string) $a->fresh()->signature_html, 'the pasted code is kept as it is');

        $this->actingAs($mine)->post("/mail/accounts/{$a->id}/send", ['to' => 'saras@example.com', 'subject' => 'Small', 'body' => 'Hi', 'html' => 'Hi'])->assertSessionHasNoErrors();
        $dec = quoted_printable_decode(FakeTransport::$sent[0]);
        foreach (['font-size:10px', 'padding:6px 4px', 'width="100"', 'height="50"', 'width:100px', 'border-radius:4.5px', 'font-size: 7pt', 'width: 50%', 'cellpadding="5"'] as $want) {
            $this->assertStringContainsString($want, $dec);
        }
        $this->actingAs($mine)->get('/mail')->assertInertia(fn (Assert $p) => $p->where('accounts.0.signature_html', fn ($h) => str_contains((string) $h, 'width="100"')));

        /* a formatted mail in the inbox: Reply / Forward get its cleaned HTML */
        FakeImap::$folders['INBOX'] = [5 => $this->mail('Formatted', 'Saras <saras@example.com>', "Content-Type: text/html; charset=utf-8\r\n\r\n<p><b>Bold news</b></p><script>bad()</script><img src=\"cid:x1\">")];
        $this->artisan('mail:sync')->assertExitCode(0);
        $m = MailMessage::query()->where('folder', 'inbox')->firstOrFail();
        $this->actingAs($mine)->get("/mail?account={$a->id}&m={$m->id}")->assertInertia(fn (Assert $p) => $p
            ->where('open.html', fn ($h) => str_contains((string) $h, '<b>Bold news</b>') && !str_contains((string) $h, 'script') && !str_contains((string) $h, 'cid:')));
    }
    /* W105: "See-through background" removes only the white backgrounds of the signature */
    public function test_signature_see_through_background(): void
    {
        $super = $this->user('super_admin');
        $mine = $this->user('admin');
        $a = MailAccount::query()->create(['address' => 'contact@mixxtune.com', 'display_name' => 'Mixx Tune', 'username' => 'contact@mixxtune.com', 'password' => 'x', 'status' => 'active', 'sent_folder' => 'INBOX.Sent']);
        $a->users()->sync([$mine->id]);
        $code = '<table bgcolor="#FFFFFF" style="background-color:#ffffff;width:400px"><tr><td style="background:white;color:#111">Kapil</td>'
            .'<td bgcolor="#4f4bdc" style="background: rgb(255, 255, 255) url(https://mixxtune.com/bg.png) no-repeat">x</td>'
            .'<td style="background-color:#4f4bdc;color:#fff">mixx tune</td><td style="background:#fff000">y</td></tr></table>';

        $this->actingAs($super)->post("/admin/mail-accounts/{$a->id}", ['signature_html' => $code, 'signature_bg' => 'pasted'])->assertSessionHasNoErrors();
        $this->assertFalse($a->fresh()->signature_transparent);
        $this->actingAs($mine)->post("/mail/accounts/{$a->id}/send", ['to' => 'x@example.com', 'subject' => 'One', 'body' => 'Hi'])->assertSessionHasNoErrors();
        $this->assertStringContainsString('background-color:#ffffff', quoted_printable_decode(FakeTransport::$sent[0]), 'off: nothing changes');

        $this->actingAs($super)->post("/admin/mail-accounts/{$a->id}", ['signature_transparent' => '1'])->assertSessionHasNoErrors();
        $this->assertTrue($a->fresh()->signature_transparent);
        $this->actingAs($mine)->post("/mail/accounts/{$a->id}/send", ['to' => 'x@example.com', 'subject' => 'Two', 'body' => 'Hi'])->assertSessionHasNoErrors();
        $dec = quoted_printable_decode(FakeTransport::$sent[1]);
        foreach (['bgcolor="#FFFFFF"', 'background-color:#ffffff', 'background:white', 'rgb(255, 255, 255)'] as $gone) {
            $this->assertStringNotContainsString($gone, $dec);
        }
        foreach (['width:400px', 'color:#111', 'bgcolor="#4f4bdc"', 'background:transparent url(https://mixxtune.com/bg.png) no-repeat', 'background-color:#4f4bdc', 'color:#fff', 'background:#fff000'] as $kept) {
            $this->assertStringContainsString($kept, $dec);
        }
        $this->assertStringContainsString('#FFFFFF', (string) $a->fresh()->signature_html, 'the pasted code itself is kept');
    }
    /* W106: the white background only as big as the signature, with colour / space / corners */
    public function test_signature_white_box_fits_the_content(): void
    {
        $super = $this->user('super_admin');
        $mine = $this->user('admin');
        $a = MailAccount::query()->create(['address' => 'contact@mixxtune.com', 'display_name' => 'Mixx Tune', 'username' => 'contact@mixxtune.com', 'password' => 'x', 'status' => 'active', 'sent_folder' => 'INBOX.Sent']);
        $a->users()->sync([$mine->id]);
        $this->assertSame('box', $a->fresh()->signature_bg, 'every mailbox starts with the fitted white box');
        $code = '<table width="100%" style="width:600px;background:#fff"><tr><td width="100%" style="background-color:#ffffff;color:#111"><img src="https://mixxtune.com/logo.png" width="120" height="96"> Kapil Koli</td>'
            .'<td style="background-color:#4f4bdc;width:40%">box</td></tr></table>';

        $this->actingAs($super)->post("/admin/mail-accounts/{$a->id}", ['signature_html' => $code, 'signature_bg_color' => 'red'])->assertSessionHasErrors('signature_bg_color');
        $this->actingAs($super)->post("/admin/mail-accounts/{$a->id}", ['signature_html' => $code, 'signature_bg_padding' => 50])->assertSessionHasErrors('signature_bg_padding');
        $this->actingAs($super)->post("/admin/mail-accounts/{$a->id}", ['signature_html' => $code, 'signature_bg' => 'other'])->assertSessionHasErrors('signature_bg');
        $this->actingAs($super)->post("/admin/mail-accounts/{$a->id}", ['signature_html' => $code])->assertSessionHasNoErrors();

        $this->actingAs($mine)->post("/mail/accounts/{$a->id}/send", ['to' => 'x@example.com', 'subject' => 'Box', 'body' => 'Hi'])->assertSessionHasNoErrors();
        $dec = quoted_printable_decode(FakeTransport::$sent[0]);
        $this->assertStringContainsString('<table role="presentation" cellpadding="0" cellspacing="0" border="0" bgcolor="#ffffff" style="background-color:#ffffff;border-radius:8px;border-collapse:separate"><tr><td style="padding:12px">', $dec);
        foreach (['width="100%"', 'width:600px', 'background:#fff;', 'width:40%'] as $gone) {
            $this->assertStringNotContainsString($gone, $dec);
        }
        foreach (['width="120"', 'background-color:#4f4bdc', 'color:#111', 'Kapil Koli'] as $kept) {
            $this->assertStringContainsString($kept, $dec);
        }

        $this->actingAs($super)->post("/admin/mail-accounts/{$a->id}", ['signature_bg_color' => '#F5F5FF', 'signature_bg_padding' => 20, 'signature_bg_radius' => 0, 'signature_scale' => 50])->assertSessionHasNoErrors();
        $this->actingAs($mine)->post("/mail/accounts/{$a->id}/send", ['to' => 'x@example.com', 'subject' => 'Box 2', 'body' => 'Hi'])->assertSessionHasNoErrors();
        $dec2 = quoted_printable_decode(FakeTransport::$sent[1]);
        $this->assertStringContainsString('bgcolor="#f5f5ff" style="background-color:#f5f5ff;border-radius:0px;border-collapse:separate"><tr><td style="padding:10px">', $dec2, 'space around follows the size too');
        $this->assertStringContainsString('width="60"', $dec2);

        $this->actingAs($super)->post("/admin/mail-accounts/{$a->id}", ['signature_bg' => 'none'])->assertSessionHasNoErrors();
        $this->actingAs($mine)->post("/mail/accounts/{$a->id}/send", ['to' => 'x@example.com', 'subject' => 'None', 'body' => 'Hi'])->assertSessionHasNoErrors();
        $dec3 = quoted_printable_decode(FakeTransport::$sent[2]);
        $this->assertStringNotContainsString('role="presentation"', $dec3);
        $this->assertStringNotContainsString('background-color:#ffffff', $dec3);
    }
}
