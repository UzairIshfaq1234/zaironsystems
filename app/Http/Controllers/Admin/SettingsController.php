<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Setting;
use App\Support\Accounting;
use App\Support\Billing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Company and billing settings. */
class SettingsController extends Controller
{
    public function billing(): JsonResponse
    {
        return response()->json(['data' => Billing::settings(), 'currencies' => Billing::currencies(), 'base_currency' => Accounting::baseCurrency()]);
    }

    public function updateBilling(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:120'],
            'company_email' => ['required', 'email:rfc', 'max:190'],
            'company_phone' => ['nullable', 'string', 'max:40'],
            'company_address' => ['nullable', 'string', 'max:400'],
            'tax_number' => ['nullable', 'string', 'max:60'],
            'currency' => ['required', Rule::in(array_keys(Billing::currencies()))],
            'tax_rate' => ['required', 'numeric', 'between:0,100'],
            'due_days' => ['required', 'integer', 'between:0,365'],
            'quote_valid_days' => ['required', 'integer', 'between:1,365'],
            'payment_instructions' => ['nullable', 'string', 'max:2000'],
            'default_terms' => ['nullable', 'string', 'max:4000'],
            'quote_terms' => ['nullable', 'string', 'max:4000'],
            'quote_notes' => ['nullable', 'string', 'max:2000'],
            'reminders' => ['boolean'],
        ]);

        Setting::put('billing', $data);
        ActivityLog::record('settings.updated', 'Updated the billing settings');

        return response()->json(['data' => Billing::settings()]);
    }
}
