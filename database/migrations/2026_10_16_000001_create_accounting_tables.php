<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Double-entry accounting: chart of accounts, journal, vendors and expenses.
 * Journal amounts are in the base currency, in integer hundredths ("cents").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 120);
            $table->string('type', 12)->index();      // asset | liability | equity | income | expense
            $table->string('subtype', 20)->nullable(); // bank | cash | receivable | payable | tax | retained | revenue | fx
            $table->string('description', 255)->nullable();
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->date('date')->index();
            $table->string('description', 255);
            $table->nullableMorphs('source');
            $table->string('source_key', 30)->nullable(); // distinguishes several entries per source (e.g. bill + bill payment)
            $table->boolean('is_manual')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->bigInteger('debit_cents')->default(0);
            $table->bigInteger('credit_cents')->default(0);
            $table->string('memo', 255)->nullable();
            $table->index(['account_id', 'journal_entry_id']);
        });

        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            $table->string('email', 190)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('tax_number', 60)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->foreignId('vendor_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('account_id')->constrained()->restrictOnDelete(); // expense category
            $table->date('date');
            $table->date('due_date')->nullable();
            $table->string('description', 255);
            $table->string('reference', 120)->nullable();
            $table->char('currency', 3);
            $table->decimal('exchange_rate', 18, 8)->nullable(); // 1 unit of `currency` in the base currency; null = base currency
            $table->bigInteger('amount_cents');
            $table->date('paid_on')->nullable();
            $table->foreignId('paid_from_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->string('receipt_path')->nullable();
            $table->string('receipt_name', 180)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['date', 'paid_on']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('exchange_rate', 18, 8)->nullable()->after('currency');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('exchange_rate', 18, 8)->nullable()->after('amount_cents');
            $table->foreignId('account_id')->nullable()->after('exchange_rate')->constrained('accounts')->nullOnDelete();
        });

        $now = now();
        DB::table('accounts')->insert(array_map(fn ($a) => [
            'code' => $a[0], 'name' => $a[1], 'type' => $a[2], 'subtype' => $a[3], 'is_system' => $a[4],
            'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
        ], config('accounting.chart')));

        // Give the starter Accountant / Manager roles the new permissions (only if they exist untouched).
        foreach (['accountant' => ['accounting.view', 'accounting.manage', 'accounting.close'], 'manager' => ['accounting.view']] as $slug => $add) {
            $role = DB::table('roles')->where('slug', $slug)->first();
            if ($role) {
                $current = json_decode($role->permissions ?? '[]', true) ?: [];
                DB::table('roles')->where('id', $role->id)->update(['permissions' => json_encode(array_values(array_unique([...$current, ...$add])))]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('account_id');
            $table->dropColumn('exchange_rate');
        });
        Schema::table('invoices', fn (Blueprint $table) => $table->dropColumn('exchange_rate'));
        foreach (['expenses', 'vendors', 'journal_lines', 'journal_entries', 'accounts'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
