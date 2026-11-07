<?php

namespace App\Http\Controllers\Admin;

use App\Mail\DocumentMail;
use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Models\Quote;
use App\Support\Billing;
use App\Support\DocumentPdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/** Validation and sending shared by the quote and invoice controllers. */
trait ManagesDocuments
{
    /**
     * Validated document fields. Terms are not taken from the request: every save copies the
     * standard terms for `$kind` from Billing settings, so all documents carry the same terms.
     *
     * @param  array<string, list<mixed>>  $extra  Rules for the document's own date fields.
     */
    protected function validateDocument(Request $request, string $kind, array $extra): array
    {
        $money = ['numeric', 'regex:/^\d{1,10}(\.\d{1,2})?$/'];

        $data = $request->validate([
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'issue_date' => ['required', 'date'],
            'currency' => ['required', Rule::in(array_keys(Billing::currencies()))],
            'tax_rate' => ['required', 'numeric', 'between:0,100'],
            'discount' => ['nullable', ...$money],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.description' => ['required', 'string', 'max:500'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999', 'regex:/^\d+(\.\d{1,2})?$/'],
            'items.*.unit_price' => ['required', ...$money],
            ...$extra,
        ], [
            'items.required' => 'Add at least one line item.',
            'items.*.description.required' => 'Every line needs a description.',
        ]);

        return [...$data, 'terms' => Billing::termsFor($kind)];
    }

    protected function sendDocument(Request $request, Quote|Invoice $doc): void
    {
        $data = $request->validate([
            'message' => ['nullable', 'string', 'max:3000'],
            'email' => ['nullable', 'string', 'email:rfc,strict', 'max:190'],
        ]);
        abort_if($doc->total_cents <= 0, 422, 'Add at least one priced line item before sending.');

        $doc->loadMissing('client');
        $to = $data['email'] ?? $doc->client->email;
        $doc->forceFill(['sent_at' => now(), 'status' => $doc->status === 'draft' ? 'sent' : $doc->status])->save();

        // The PDF is saved before it is attached, so the stored copy is exactly what the client received.
        $pdfPath = DocumentPdf::storeSentCopy($doc);
        Mail::to($to, $doc->client->name)->queue(new DocumentMail($doc, 'sent', $data['message'] ?? null, $pdfPath));

        $kind = $doc::KIND;
        ActivityLog::record("{$kind}.sent", ucfirst($kind)." {$doc->number} emailed to {$to} with PDF", $doc);
    }

    /**
     * The document as a PDF: rendered now, or with ?copy=sent the copy saved when it was last emailed.
     * ?inline=1 opens it in the browser instead of downloading.
     */
    protected function pdfResponse(Request $request, Quote|Invoice $doc): Response
    {
        $request->validate(['copy' => ['nullable', Rule::in(['sent'])], 'inline' => ['nullable', 'boolean']]);

        if ($request->input('copy') === 'sent') {
            abort_unless($doc->pdf_path && Storage::disk('local')->exists($doc->pdf_path), 404, 'No emailed copy has been saved for this document yet.');
            [$bytes, $filename] = [Storage::disk('local')->get($doc->pdf_path), basename($doc->pdf_path)];
        } else {
            [$bytes, $filename] = [DocumentPdf::render($doc), DocumentPdf::filename($doc)];
        }

        return DocumentPdf::response($bytes, $filename, $request->boolean('inline'));
    }
}
