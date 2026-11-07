<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

/** Sends one plain test email right away (not queued) and reports the mail settings in use and any SMTP error. */
class TestMail extends Command
{
    protected $signature = 'zairon:mail-test {to : Address to send the test email to}';

    protected $description = 'Send a test email immediately to check the mail settings';

    public function handle(): int
    {
        $mailer = config('mail.default');
        $smtp = config('mail.mailers.smtp');
        $this->line("Mailer: <info>{$mailer}</info>".($mailer === 'smtp' ? "  Host: {$smtp['host']}:{$smtp['port']}  Scheme: ".($smtp['scheme'] ?: 'auto (STARTTLS)')."  User: {$smtp['username']}" : ''));
        $this->line('From:   '.config('mail.from.address'));

        if ($mailer === 'log') {
            $this->warn('MAIL_MAILER is "log": emails are written to storage/logs/laravel.log, not sent. Set MAIL_MAILER=smtp in .env.');
        }

        try {
            Mail::raw('This is a test email from the ZAIRON Systems website. If you can read this, email sending works.', function ($message) {
                $message->to($this->argument('to'))->subject('ZAIRON Systems test email');
            });
        } catch (Throwable $e) {
            $this->error('Sending failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info($mailer === 'log' ? 'Written to the log.' : 'Sent. Check the inbox (and spam folder).');

        return self::SUCCESS;
    }
}
