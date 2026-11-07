<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Employees, their attendance, leave, performance reviews and monthly payroll. Money is in integer cents. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 120);
            $table->string('email', 190)->unique();
            $table->string('phone', 40)->nullable();
            $table->string('job_title', 120)->nullable();
            $table->string('department', 80)->nullable();
            $table->date('joined_on');
            $table->string('status', 12)->default('active')->index(); // active | left
            $table->bigInteger('basic_salary_cents')->default(0);
            $table->bigInteger('allowance_cents')->default(0);
            $table->time('shift_start')->default('09:00:00');
            $table->time('shift_end')->default('18:00:00');
            $table->string('bank_name', 120)->nullable();
            $table->string('bank_account', 60)->nullable();
            $table->string('password')->nullable();
            $table->rememberToken();
            $table->timestamp('portal_invited_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Portal password set/reset links for employees (kept apart from admin tokens).
        Schema::create('employee_password_tokens', function (Blueprint $table) {
            $table->string('email')->index();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('work_date');
            $table->dateTime('check_in_at')->nullable();
            $table->dateTime('check_out_at')->nullable();
            $table->unsignedSmallInteger('late_minutes')->default(0);
            $table->string('note', 255)->nullable();
            $table->timestamps();
            $table->unique(['employee_id', 'work_date']);
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('type', 12);                  // annual | sick | casual | unpaid
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('days', 5, 1);
            $table->text('reason')->nullable();
            $table->string('status', 12)->default('pending')->index(); // pending | approved | rejected | cancelled
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_note', 255)->nullable();
            $table->timestamps();
            $table->index(['employee_id', 'status', 'start_date']);
        });

        Schema::create('performance_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('period', 20);                // e.g. 2026-Q4
            $table->unsignedTinyInteger('score');        // 1–5
            $table->text('strengths')->nullable();
            $table->text('improvements')->nullable();
            $table->text('goals')->nullable();
            $table->text('employee_comment')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->string('period', 7)->unique();       // YYYY-MM
            $table->string('status', 12)->default('draft'); // draft | posted
            $table->bigInteger('gross_cents')->default(0);
            $table->bigInteger('tax_cents')->default(0);
            $table->bigInteger('net_cents')->default(0);
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('salary_slips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('period', 7);
            $table->bigInteger('basic_cents');
            $table->bigInteger('allowance_cents');
            $table->decimal('unpaid_days', 5, 1)->default(0);
            $table->bigInteger('unpaid_deduction_cents')->default(0);
            $table->bigInteger('gross_cents');
            $table->bigInteger('tax_cents');
            $table->bigInteger('net_cents');
            $table->timestamps();
            $table->unique(['payroll_run_id', 'employee_id']);
        });

        // Permissions for the people modules, granted to the starter roles.
        $grants = [
            'manager' => ['hr.view', 'hr.manage'],
            'accountant' => ['hr.view', 'payroll.view', 'payroll.manage'],
        ];
        foreach ($grants as $slug => $add) {
            $role = DB::table('roles')->where('slug', $slug)->first();
            if ($role) {
                $current = json_decode($role->permissions ?? '[]', true) ?: [];
                DB::table('roles')->where('id', $role->id)->update(['permissions' => json_encode(array_values(array_unique([...$current, ...$add])))]);
            }
        }
    }

    public function down(): void
    {
        foreach (['salary_slips', 'payroll_runs', 'performance_reviews', 'leave_requests', 'attendance_records', 'employee_password_tokens', 'employees'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
