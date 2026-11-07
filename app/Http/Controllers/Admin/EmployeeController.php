<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Employee;
use App\Models\JobTitle;
use App\Notifications\EmployeePasswordReset;
use App\Support\Billing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Employee records. Salary figures are only returned to people with payroll.view. */
class EmployeeController extends Controller
{
    /** Relations every employee screen needs (strict mode forbids loading them lazily). */
    public const RELATIONS = ['department', 'jobTitle', 'allowances.type'];

    public function index(Request $request): JsonResponse
    {
        $request->validate(['q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::in(['active', 'left'])]]);

        $employees = Employee::query()
            ->with(self::RELATIONS)
            ->when($request->input('q'), function ($query, string $q) {
                $like = '%'.addcslashes($q, '%_\\').'%';
                $query->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('code', 'like', $like)->orWhere('email', 'like', $like)
                    ->orWhereHas('jobTitle', fn ($j) => $j->where('name', 'like', $like))->orWhereHas('department', fn ($d) => $d->where('name', 'like', $like)));
            })
            ->when($request->input('status'), fn ($query, $status) => $query->where('status', $status))
            ->orderBy('code')->limit(500)->get();

        return response()->json(['data' => $employees->map(fn (Employee $e) => $this->present($e, $request))->values()]);
    }

    public function show(Request $request, Employee $employee): JsonResponse
    {
        $employee->load(self::RELATIONS);
        $data = $this->present($employee, $request);
        $data['attendance'] = $employee->attendance()->limit(31)->get()->map->toAdminArray()->values();
        $data['leave'] = $employee->leaveRequests()->limit(50)->get()->map->toAdminArray()->values();
        $data['reviews'] = $employee->reviews()->with('reviewer')->get()->map->toAdminArray()->values();
        $data['slips'] = $request->user()->can('payroll.view')
            ? $employee->salarySlips()->get()->map->toArray()->values()
            : [];

        return response()->json(['data' => $data]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $employee = DB::transaction(function () use ($data, $request) {
            $employee = Employee::create([...$data['employee'], 'code' => Employee::nextCode(), 'created_by' => $request->user()->id]);
            $this->syncAllowances($employee, $data['allowances']);

            return $employee;
        });
        ActivityLog::record('employee.created', "Added employee {$employee->name} ({$employee->code})", $employee);

        return response()->json(['data' => $this->present($employee->load(self::RELATIONS), $request)], 201);
    }

    public function update(Request $request, Employee $employee): JsonResponse
    {
        $data = $this->validated($request, $employee);
        DB::transaction(function () use ($employee, $data) {
            $employee->update($data['employee']);
            $this->syncAllowances($employee, $data['allowances']);
        });
        ActivityLog::record('employee.updated', "Updated employee {$employee->name}", $employee);

        return response()->json(['data' => $this->present($employee->fresh()->load(self::RELATIONS), $request)]);
    }

    /** Emails the employee a link to set their portal password (valid for 72 hours). */
    public function invite(Request $request, Employee $employee): JsonResponse
    {
        abort_unless($employee->status === 'active', 422, 'Only active employees can use the portal.');
        $token = Password::broker('employees')->createToken($employee);
        $employee->notify(new EmployeePasswordReset($token, invite: true));
        $employee->forceFill(['portal_invited_at' => now()])->save();
        ActivityLog::record('employee.invited', "Sent a portal invitation to {$employee->name}", $employee);

        return response()->json(['message' => "Invitation sent to {$employee->email}.", 'data' => $this->present($employee->load(self::RELATIONS), $request)]);
    }

    private function present(Employee $employee, Request $request): array
    {
        $data = $employee->toAdminArray();
        if (! $request->user()->can('payroll.view')) {
            unset($data['basic_salary'], $data['allowances'], $data['allowance_total'], $data['bank_name'], $data['bank_account']);
        }

        return $data;
    }

    /** Replaces the employee's allowances with the submitted list (one row per allowance type). */
    private function syncAllowances(Employee $employee, array $allowances): void
    {
        $employee->allowances()->delete();
        foreach ($allowances as $allowance) {
            $employee->allowances()->create(['allowance_type_id' => $allowance['allowance_type_id'], 'amount_cents' => $allowance['amount_cents']]);
        }
    }

    private function validated(Request $request, ?Employee $employee = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190', Rule::unique('employees', 'email')->ignore($employee?->id)],
            'phone' => ['nullable', 'string', 'max:40'],
            'job_title_id' => ['nullable', 'integer', 'exists:job_titles,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'joined_on' => ['required', 'date'],
            'status' => ['sometimes', Rule::in(['active', 'left'])],
            'basic_salary' => ['required', 'numeric', 'min:0', 'max:99999999', 'regex:/^\d{1,8}(\.\d{1,2})?$/'],
            'allowances' => ['nullable', 'array', 'max:20'],
            'allowances.*.allowance_type_id' => ['required', 'integer', 'exists:allowance_types,id'],
            'allowances.*.amount' => ['required', 'numeric', 'min:0', 'max:99999999', 'regex:/^\d{1,8}(\.\d{1,2})?$/'],
            'shift_start' => ['required', 'date_format:H:i'],
            'shift_end' => ['required', 'date_format:H:i', 'after:shift_start'],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'bank_account' => ['nullable', 'string', 'max:60'],
        ]);

        // A job title belongs to one department: pick the department from the title, or refuse a mismatch.
        if (! empty($data['job_title_id'])) {
            $titleDepartment = JobTitle::whereKey($data['job_title_id'])->value('department_id');
            if (empty($data['department_id'])) {
                $data['department_id'] = $titleDepartment;
            } elseif ((int) $data['department_id'] !== (int) $titleDepartment) {
                throw ValidationException::withMessages(['job_title_id' => 'That job title belongs to a different department.']);
            }
        }
        $allowances = collect($data['allowances'] ?? [])
            ->map(fn (array $row) => ['allowance_type_id' => (int) $row['allowance_type_id'], 'amount_cents' => Billing::toCents($row['amount'])])
            ->values();
        if ($allowances->pluck('allowance_type_id')->duplicates()->isNotEmpty()) {
            abort(422, 'Each allowance can only be listed once per person.');
        }

        return [
            'employee' => [
                ...collect($data)->only(['name', 'email', 'phone', 'department_id', 'job_title_id', 'joined_on', 'status', 'shift_start', 'shift_end', 'bank_name', 'bank_account'])->all(),
                'basic_salary_cents' => Billing::toCents($data['basic_salary']),
            ],
            'allowances' => $allowances->all(),
        ];
    }
}
