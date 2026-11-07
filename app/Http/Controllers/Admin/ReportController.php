<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Reports;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Financial statements. Dates are YYYY-MM-DD; amounts are in the base currency. */
class ReportController extends Controller
{
    public function trialBalance(Request $request): JsonResponse
    {
        return response()->json(['data' => Reports::trialBalance($this->asOf($request))]);
    }

    public function balanceSheet(Request $request): JsonResponse
    {
        return response()->json(['data' => Reports::balanceSheet($this->asOf($request))]);
    }

    public function profitAndLoss(Request $request): JsonResponse
    {
        $data = $request->validate(['from' => ['required', 'date'], 'to' => ['required', 'date', 'after_or_equal:from']]);

        return response()->json(['data' => Reports::profitAndLoss($data['from'], $data['to'])]);
    }

    public function receivables(): JsonResponse
    {
        return response()->json(['data' => Reports::receivablesAging()]);
    }

    public function payables(): JsonResponse
    {
        return response()->json(['data' => Reports::payablesAging()]);
    }

    private function asOf(Request $request): string
    {
        return $request->validate(['as_of' => ['nullable', 'date']])['as_of'] ?? now()->toDateString();
    }
}
