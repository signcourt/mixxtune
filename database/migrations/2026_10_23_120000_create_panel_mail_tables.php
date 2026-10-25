<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * W95 MIXXTUNE_PANEL_MAIL
 * Domain mailboxes (Hostinger Email) used inside the panel: the mailbox and
 * its login (password encrypted with the app key), who may use it, the
 * folders read so far, the messages and their attachments.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('mail_accounts')) {
            Schema::create('mail_accounts', function (Blueprint $table) {
                $table->id();
                $table->string('address', 190)->unique();
                $table->string('display_name', 190)->nullable();
                $table->string('username', 190);
                $table->text('password');
                $table->string('imap_host', 190)->default('imap.hostinger.com');
                $table->unsignedSmallInteger('imap_port')->default(993);
                $table->string('imap_encryption', 10)->default('ssl');
                $table->string('smtp_host', 190)->default('smtp.hostinger.com');
                $table->unsignedSmallInteger('smtp_port')->default(465);
                $table->string('smtp_encryption', 10)->default('ssl');
                $table->string('sent_folder', 190)->nullable();
                $table->text('signature')->nullable();
                $table->string('status', 20)->default('active'); /* active | disabled */
                $table->timestamp('last_synced_at')->nullable();
                $table->text('last_error')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('mail_account_user')) {
            Schema::create('mail_account_user', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('mail_account_id');
                $table->unsignedBigInteger('user_id');
                $table->timestamps();
                $table->unique(['mail_account_id', 'user_id']);
                $table->index('user_id');
            });
        }

        if (!Schema::hasTable('mail_folders')) {
            Schema::create('mail_folders', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('mail_account_id');
                $table->string('name', 190);
                $table->string('kind', 20); /* inbox | sent */
                $table->unsignedBigInteger('uidvalidity')->default(0);
                $table->unsignedBigInteger('last_uid')->default(0);
                $table->timestamps();
                $table->unique(['mail_account_id', 'kind']);
            });
        }

        if (!Schema::hasTable('mail_messages')) {
            Schema::create('mail_messages', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('mail_account_id');
                $table->string('folder', 20); /* inbox | sent */
                $table->unsignedBigInteger('uid')->nullable();
                $table->string('message_id', 500)->nullable();
                $table->string('in_reply_to', 500)->nullable();
                $table->text('references_header')->nullable();
                $table->string('subject', 998)->nullable();
                $table->string('from_name', 190)->nullable();
                $table->string('from_email', 190)->nullable();
                $table->json('to')->nullable();
                $table->json('cc')->nullable();
                $table->json('reply_to')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->string('snippet', 300)->nullable();
                $table->longText('body_text')->nullable();
                $table->longText('body_html')->nullable();
                $table->boolean('has_attachments')->default(false);
                $table->boolean('seen')->default(false);
                $table->unsignedBigInteger('size')->default(0);
                $table->unsignedBigInteger('sent_by')->nullable();
                $table->timestamps();
                $table->unique(['mail_account_id', 'folder', 'uid']);
                $table->index(['mail_account_id', 'folder', 'sent_at']);
            });
        }

        if (!Schema::hasTable('mail_attachments')) {
            Schema::create('mail_attachments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('mail_message_id')->index();
                $table->string('filename', 190);
                $table->string('mime', 120)->nullable();
                $table->unsignedBigInteger('size')->default(0);
                $table->string('content_id', 190)->nullable();
                $table->boolean('inline')->default(false);
                $table->string('path', 500);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_attachments');
        Schema::dropIfExists('mail_messages');
        Schema::dropIfExists('mail_folders');
        Schema::dropIfExists('mail_account_user');
        Schema::dropIfExists('mail_accounts');
    }
};
