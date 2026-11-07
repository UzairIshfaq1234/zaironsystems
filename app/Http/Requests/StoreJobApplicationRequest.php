<?php

namespace App\Http\Requests;

use App\Models\JobOpening;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

/** Mirrors the client-side checks in resources/js/components/forms/JobApplyForm.jsx. */
class StoreJobApplicationRequest extends FormRequest
{
    public const MAX_CV_KB = 10 * 1024;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'job_opening_id' => ['required', 'integer', Rule::exists('job_openings', 'id')->where('status', JobOpening::STATUS_OPEN)],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'max:190', 'email:rfc,strict'],
            'phone' => ['nullable', 'string', 'max:40', 'regex:/^[0-9+\-().\s]{6,40}$/'],
            'links' => ['nullable', 'string', 'max:500'],
            'message' => ['nullable', 'string', 'max:5000'],
            'cv' => ['required', File::types(['pdf', 'doc', 'docx'])->max(self::MAX_CV_KB)],
            'consent' => ['accepted'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'job_opening_id.required' => 'Please choose a role.',
            'job_opening_id.exists' => 'This role is no longer open. Please choose another.',
            'name.required' => 'Please enter your name.',
            'email.required' => 'Please enter a valid email.',
            'email.email' => 'Please enter a valid email.',
            'phone.regex' => 'Please enter a valid phone number.',
            'cv.required' => 'Please attach your CV.',
            'cv.max' => 'Please attach a file under 10 MB.',
            'cv.mimes' => 'Please attach a PDF, DOC or DOCX file.',
            'consent.accepted' => 'Please accept the privacy policy.',
        ];
    }
}
