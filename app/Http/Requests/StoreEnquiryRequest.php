<?php

namespace App\Http\Requests;

use App\Support\Content;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

/** Mirrors the client-side checks in resources/js/components/forms/ContactForm.jsx. */
class StoreEnquiryRequest extends FormRequest
{
    public const MAX_FILE_KB = 10 * 1024;

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'max:190', 'email:rfc,strict'],
            'company' => ['required', 'string', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40', 'regex:/^[0-9+\-().\s]{6,40}$/'],
            'services' => ['nullable', 'array', 'max:10'],
            'services.*' => ['string', 'distinct', Rule::in(Content::serviceOptions())],
            'service' => ['nullable', 'string', Rule::in(Content::serviceSlugs())],
            'plan' => ['nullable', 'string', Rule::in(Content::planIds())],
            'budget' => ['nullable', 'string', Rule::in(Content::budgets())],
            'details' => ['required', 'string', 'min:10', 'max:5000'],
            'brief' => ['nullable', File::types(['pdf', 'doc', 'docx'])->max(self::MAX_FILE_KB)],
            'consent' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Please enter your name.',
            'email.required' => 'Please enter a valid email.',
            'email.email' => 'Please enter a valid email.',
            'company.required' => 'Please enter your company.',
            'phone.regex' => 'Please enter a valid phone number.',
            'details.required' => 'Please add a few details.',
            'details.min' => 'Please add a few details (at least 10 characters).',
            'brief.max' => 'Please attach a file under 10 MB.',
            'brief.mimes' => 'Please attach a PDF, DOC or DOCX file.',
            'consent.accepted' => 'Please accept the privacy policy.',
        ];
    }
}
