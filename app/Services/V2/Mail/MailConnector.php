<?php

namespace App\Services\V2\Mail;

use App\Models\Mail\MailAccount;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Transport\TransportInterface;

/* W95 MIXXTUNE_PANEL_MAIL: opens the mailbox (IMAP) and the sending line (SMTP) of one account */
class MailConnector
{
    public function imap(MailAccount $account): ImapClient
    {
        $client = ImapClient::connect($account->imap_host, (int) $account->imap_port, $account->imap_encryption ?: 'ssl', 30);
        $client->login($account->username, (string) $account->password);

        return $client;
    }

    public function transport(MailAccount $account): TransportInterface
    {
        $scheme = ($account->smtp_encryption ?: 'ssl') === 'ssl' ? 'smtps' : 'smtp';
        $dsn = sprintf('%s://%s:%s@%s:%d', $scheme, rawurlencode($account->username), rawurlencode((string) $account->password), $account->smtp_host, (int) $account->smtp_port);

        return Transport::fromDsn($dsn);
    }
}
