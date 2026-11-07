<?php

namespace App\Console\Commands;

use App\Models\Expense;
use App\Models\Invoice;
use App\Support\Ledger;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use Throwable;

/** Re-posts every invoice and expense to the journal. Safe to repeat: unchanged entries are left alone. */
class SyncLedger extends Command
{
    protected $signature = 'zairon:ledger-sync';

    protected $description = 'Post all invoices, payments and expenses to the accounting journal';

    public function handle(): int
    {
        $failed = 0;
        $run = function ($model, callable $sync) use (&$failed) {
            try {
                $sync($model);
            } catch (Throwable $e) {
                $failed++;
                $this->warn("{$model->number}: ".($e instanceof ValidationException ? collect($e->errors())->flatten()->first() : $e->getMessage()));
            }
        };

        Invoice::orderBy('id')->each(fn (Invoice $i) => $run($i, Ledger::syncInvoice(...)));
        Expense::orderBy('id')->each(fn (Expense $e) => $run($e, Ledger::syncExpense(...)));

        $failed ? $this->warn("{$failed} document(s) could not be posted; fix them and run this again.") : $this->info('The journal is up to date.');

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
