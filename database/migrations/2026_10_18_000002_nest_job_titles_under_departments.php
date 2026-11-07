<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Job titles now belong to a department. Titles that had no department go under "General". */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_titles', function (Blueprint $table) {
            $table->dropUnique(['name']);
            $table->foreignId('department_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->unique(['department_id', 'name']);
        });

        if (DB::table('job_titles')->whereNull('department_id')->exists()) {
            $now = now();
            $general = DB::table('departments')->where('name', 'General')->value('id')
                ?? DB::table('departments')->insertGetId(['name' => 'General', 'created_at' => $now, 'updated_at' => $now]);
            DB::table('job_titles')->whereNull('department_id')->update(['department_id' => $general]);
        }

        // Keep each employee's department consistent with their job title.
        DB::table('employees')->whereNotNull('job_title_id')->whereNull('department_id')->orderBy('id')->each(function ($row) {
            $departmentId = DB::table('job_titles')->where('id', $row->job_title_id)->value('department_id');
            if ($departmentId) {
                DB::table('employees')->where('id', $row->id)->update(['department_id' => $departmentId]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('job_titles', function (Blueprint $table) {
            $table->dropUnique(['department_id', 'name']);
            $table->dropConstrainedForeignId('department_id');
            $table->unique('name');
        });
    }
};
