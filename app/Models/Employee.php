<?php

namespace App\Models;

use App\Notifications\EmployeePasswordReset;
use App\Support\Billing;
use App\Support\Hr;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * A person on the payroll. Employees also sign in to the employee portal (their own guard and
 * password), so this model is authenticatable and never shares accounts with admin users.
 */
class Employee extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'code', 'name', 'email', 'phone', 'department_id', 'job_title_id', 'joined_on', 'status', 'basic_salary_cents',
        'shift_start', 'shift_end', 'bank_name', 'bank_account', 'created_by',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $attributes = ['status' => 'active', 'basic_salary_cents' => 0];

    protected function casts(): array
    {
        return [
            'joined_on' => 'date',
            'password' => 'hashed',
            'portal_invited_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    /** EMP-001, EMP-002… (the next number after the highest existing one). */
    public static function nextCode(): string
    {
        $last = static::query()->where('code', 'like', 'EMP-%')->whereRaw('LENGTH(code) = 7')->max('code');

        return 'EMP-'.str_pad((string) ((int) substr((string) $last, 4) + 1), 3, '0', STR_PAD_LEFT);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function jobTitle(): BelongsTo
    {
        return $this->belongsTo(JobTitle::class);
    }

    public function allowances(): HasMany
    {
        return $this->hasMany(EmployeeAllowance::class)->with('type')->orderBy('id');
    }

    public function attendance(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class)->orderByDesc('work_date');
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class)->orderByDesc('start_date');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(PerformanceReview::class)->orderByDesc('period');
    }

    public function salarySlips(): HasMany
    {
        return $this->hasMany(SalarySlip::class)->orderByDesc('period');
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new EmployeePasswordReset($token));
    }

    /** Sum of this employee's allowances per month, in cents. */
    public function allowanceCents(): int
    {
        return (int) $this->allowances()->sum('amount_cents');
    }

    /** Basic salary plus all allowances per month, in cents (before any unpaid-leave deduction). */
    public function totalSalaryCents(): int
    {
        return $this->basic_salary_cents + $this->allowanceCents();
    }

    public function toAdminArray(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'job_title' => $this->jobTitle?->name,
            'job_title_id' => $this->job_title_id,
            'department' => $this->department?->name,
            'department_id' => $this->department_id,
            'joined_on' => $this->joined_on?->toDateString(),
            'status' => $this->status,
            'basic_salary' => Billing::fromCents($this->basic_salary_cents),
            'allowances' => $this->allowances->map->toAdminArray()->values(),
            'allowance_total' => Billing::fromCents($this->allowances->sum('amount_cents')),
            'shift_start' => substr((string) $this->shift_start, 0, 5),
            'shift_end' => substr((string) $this->shift_end, 0, 5),
            'bank_name' => $this->bank_name,
            'bank_account' => $this->bank_account,
            'portal_invited' => $this->portal_invited_at !== null,
            'portal_active' => $this->password !== null,
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'leave_balances' => Hr::leaveBalances($this),
        ];
    }
}
