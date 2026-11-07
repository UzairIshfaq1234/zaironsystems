<?php

namespace App\Console\Commands;

use App\Mail\DocumentMail;
use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Notifications\PortalAlert;
use App\Support\Billing;
use App\Support\DocumentPdf;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/** Emails overdue invoices, at most once every 7 days each. Scheduled daily (routes/console.php). */
class SendInvoiceReminders extends Command
{
    protected $signature = 'zairon:invoice-reminders';

    protected $description = 'Send payment reminders for overdue invoices';

    public function handle(): int
    {
        if (! Billing::settings()['reminders']) {
            $this->components->info('Automatic reminders are turned off in Billing settings.');

            return self::SUCCESS;
        }

        $sent = 0;
        Invoice::overdue()->with('client')
            ->where(fn ($q) => $q->whereNull('last_reminder_at')->orWhere('last_reminder_at', '<=', now()->subDays(7)))
            ->each(function (Invoice $invoice) use (&$sent) {
                Mail::to($invoice->client->email, $invoice->client->name)->queue(new DocumentMail($invoice, 'reminder', pdfPath: DocumentPdf::storeSentCopy($invoice)));
                $invoice->forceFill(['last_reminder_at' => now()])->save();
                ActivityLog::record('invoice.reminded', "Automatic payment reminder sent for {$invoice->number}", $invoice);
                PortalAlert::toTeam('billing.view', new PortalAlert(
                    'overdue', "{$invoice->number} is overdue", "{$invoice->client->displayName()} · {$invoice->money($invoice->balanceCents())} due · reminder emailed", "/billing/invoices/{$invoice->id}",
                ));
                $sent++;
            });

        $this->components->info("Sent {$sent} reminder(s).");

        return self::SUCCESS;
    }
}
