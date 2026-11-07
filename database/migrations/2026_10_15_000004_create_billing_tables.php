<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Settings, clients, quotes, invoices, their line items and payments. Money is stored in integer cents. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            Schema::create('settings', function (Blueprint $table) {
                $table->string('key', 80)->primary();
                $table->json('value');
                $table->timestamps();
            });
        }

        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('company', 160)->nullable();
            $table->string('email', 190);
            $table->string('phone', 40)->nullable();
            $table->text('address')->nullable();
            $table->string('tax_number', 60)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('enquiry_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        $document = function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('draft')->index();
            $table->date('issue_date');
            $table->char('currency', 3);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->bigInteger('discount_cents')->default(0);
            $table->bigInteger('subtotal_cents')->default(0);
            $table->bigInteger('tax_cents')->default(0);
            $table->bigInteger('total_cents')->default(0);
            $table->text('notes')->nullable();
            $table->text('terms')->nullable();
            $table->string('public_token', 64)->unique();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        };

        Schema::create('quotes', function (Blueprint $table) use ($document) {
            $document($table);
            $table->date('valid_until')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->unsignedBigInteger('invoice_id')->nullable();
        });

        Schema::create('invoices', function (Blueprint $table) use ($document) {
            $document($table);
            $table->date('due_date');
            $table->foreignId('quote_id')->nullable()->constrained()->nullOnDelete();
            $table->bigInteger('paid_cents')->default(0);
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('last_reminder_at')->nullable();
            $table->index(['status', 'due_date']);
        });

        Schema::create('line_items', function (Blueprint $table) {
            $table->id();
            $table->morphs('document');
            $table->string('description', 500);
            $table->decimal('quantity', 10, 2);
            $table->bigInteger('unit_cents');
            $table->bigInteger('amount_cents');
            $table->unsignedSmallInteger('sort')->default(0);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('amount_cents');
            $table->date('paid_on');
            $table->string('method', 40);
            $table->string('reference', 120)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['payments', 'line_items', 'invoices', 'quotes', 'clients', 'settings'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
