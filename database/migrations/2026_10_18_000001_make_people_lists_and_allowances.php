<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Departments, job titles and allowance types become managed lists; employees point at them.
 * Each employee can hold several allowances. Existing text values are carried over.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->timestamps();
        });

        Schema::create('job_titles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->timestamps();
        });

        Schema::create('allowance_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('employee_allowances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('allowance_type_id')->constrained()->restrictOnDelete();
            $table->bigInteger('amount_cents')->default(0);
            $table->timestamps();
            $table->unique(['employee_id', 'allowance_type_id']);
        });

        Schema::create('salary_slip_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salary_slip_id')->constrained()->cascadeOnDelete();
            $table->string('label', 120);
            $table->string('kind', 12); // earning | deduction
            $table->bigInteger('amount_cents');
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('joined_on')->constrained()->nullOnDelete();
            $table->foreignId('job_title_id')->nullable()->after('department_id')->constrained()->nullOnDelete();
        });

        // Carry existing text values into the lists.
        $now = now();
        foreach (DB::table('employees')->whereNotNull('department')->where('department', '!=', '')->distinct()->pluck('department') as $name) {
            $id = DB::table('departments')->insertGetId(['name' => $name, 'created_at' => $now, 'updated_at' => $now]);
            DB::table('employees')->where('department', $name)->update(['department_id' => $id]);
        }
        foreach (DB::table('employees')->whereNotNull('job_title')->where('job_title', '!=', '')->distinct()->pluck('job_title') as $name) {
            $id = DB::table('job_titles')->insertGetId(['name' => $name, 'created_at' => $now, 'updated_at' => $now]);
            DB::table('employees')->where('job_title', $name)->update(['job_title_id' => $id]);
        }
        $withAllowance = DB::table('employees')->where('allowance_cents', '>', 0)->get(['id', 'allowance_cents']);
        if ($withAllowance->isNotEmpty()) {
            $typeId = DB::table('allowance_types')->insertGetId(['name' => 'Allowance', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
            foreach ($withAllowance as $row) {
                DB::table('employee_allowances')->insert(['employee_id' => $row->id, 'allowance_type_id' => $typeId, 'amount_cents' => $row->allowance_cents, 'created_at' => $now, 'updated_at' => $now]);
            }
        }

        // Starter allowances; rename or add more in People → Lists.
        foreach (['House rent', 'Medical', 'Transport', 'Fuel'] as $name) {
            DB::table('allowance_types')->insertOrIgnore(['name' => $name, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['department', 'job_title', 'allowance_cents']);
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('department', 80)->nullable();
            $table->string('job_title', 120)->nullable();
            $table->bigInteger('allowance_cents')->default(0);
        });
        Schema::table('employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
            $table->dropConstrainedForeignId('job_title_id');
        });
        foreach (['salary_slip_items', 'employee_allowances', 'allowance_types', 'job_titles', 'departments'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
