<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Where the PDF emailed most recently is kept (private disk), so the exact copy the client got can be downloaded. */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['quotes', 'invoices'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->string('pdf_path')->nullable()->after('public_token');
            });
        }
    }

    public function down(): void
    {
        foreach (['quotes', 'invoices'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn('pdf_path');
            });
        }
    }
};
