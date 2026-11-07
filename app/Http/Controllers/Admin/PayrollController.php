<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\PayrollRun;
use App\Models\SalarySlip;
use App\Notifications\PortalAlert;
use App\Support\Accounting;
use App\Support\Ledger;
use App\Support\Payroll;
use App\Support\SalarySlipPdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Monthly payroll: create a month, generate the slips, review them, then post to the books. */
class PayrollController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => PayrollRun::withCount('slips')->orderByDesc('period')->get()->map->toAdminArray()->values()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['period' => ['required', 'date_format:Y-m', Rule::unique('payroll_runs', 'period')]], [
            'period.unique' => 'Payroll for that month already exists.',
        ]);
        $run = PayrollRun::create(['period' => $data['period'], 'created_by' => $request->user()->id]);
        Payroll::generate($run);
        ActivityLog::record('payroll.created', "Started payroll for {$run->period}");

        return response()->json(['data' => $run->fresh()->loadCount('slips')->toAdminArray()], 201);
    }

    public function show(PayrollRun $run): JsonResponse
    {
        $slips = $run->slips()->with(['employee', 'items'])->get()->map(fn (SalarySlip $slip) => [
            ...$slip->toArray(),
            'id' => $slip->id,
            'employee_id' => $slip->employee_id,
            'employee' => $slip->employee->name,
            'code' => $slip->employee->code,
        ]);

        return response()->json([
            'data' => [...$run->toAdminArray(), 'slips' => $slips->values()],
            'base_currency' => Accounting::baseCurrency(),
        ]);
    }

    /** Rebuilds the slips from the current salaries and leave (drafts only). */
    public function generate(PayrollRun $run): JsonResponse
    {
        Payroll::generate($run);

        return response()->json(['data' => $run->fresh()->loadCount('slips')->toAdminArray(), 'message' => 'Slips regenerated.']);
    }

    /** Locks the month and posts it to the books. Cannot be undone; corrections are made as a new month's adjustment. */
    public function post(Request $request, PayrollRun $run): JsonResponse
    {
        abort_if($run->isPosted(), 422, 'This month is already posted.');
        abort_if($run->slips()->count() === 0, 422, 'Generate the slips before posting.');

        DB::transaction(function () use ($run) {
            $run->forceFill(['status' => 'posted', 'posted_at' => now()])->save();
            Ledger::syncPayrollRun($run);
        });
        $run->slips()->with('employee')->get()->each(fn (SalarySlip $slip) => $slip->employee->notify(
            new PortalAlert('payslip', "Your salary slip for {$run->period} is ready", 'Download it from Salary slips in the portal.', '/payslips'),
        ));
        ActivityLog::record('payroll.posted', "Posted payroll for {$run->period} to the books", $run);

        return response()->json(['data' => $run->fresh()->loadCount('slips')->toAdminArray(), 'message' => 'Posted to the books.']);
    }

    public function destroy(PayrollRun $run): JsonResponse
    {
        Payroll::delete($run);
        ActivityLog::record('payroll.deleted', "Deleted the draft payroll for {$run->period}");

        return response()->json(status: 204);
    }

    public function slipPdf(SalarySlip $slip): Response
    {
        return SalarySlipPdf::response(SalarySlipPdf::render($slip), SalarySlipPdf::filename($slip));
    }
}
