<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\Enquiry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate(['q' => ['nullable', 'string', 'max:100'], 'all' => ['boolean']]);

        $query = Client::withCount(['quotes', 'invoices'])
            ->when($request->input('q'), function ($q, string $term) {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $q->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('company', 'like', $like)->orWhere('email', 'like', $like));
            })
            ->orderBy('company')->orderBy('name');

        // ?all=1 → compact list for the client picker in the document editor.
        if ($request->boolean('all')) {
            return response()->json(['data' => $query->limit(500)->get()->map->toSummaryArray()]);
        }

        return response()->json(Admin::page($query->paginate(20), fn (Client $c) => $c->toAdminArray()));
    }

    public function show(Client $client): JsonResponse
    {
        $client->load(['quotes' => fn ($q) => $q->latest('id'), 'invoices' => fn ($q) => $q->latest('id')]);

        return response()->json([
            'data' => $client->toAdminArray(),
            'quotes' => $client->quotes->map(fn ($q) => $q->setRelation('client', $client)->toAdminArray()),
            'invoices' => $client->invoices->map(fn ($i) => $i->setRelation('client', $client)->toAdminArray()),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $client = Client::create($request->validate(self::rules()));
        ActivityLog::record('client.created', "Added client {$client->displayName()}", $client);

        return response()->json(['data' => $client->toAdminArray()], 201);
    }

    public function update(Request $request, Client $client): JsonResponse
    {
        $client->update($request->validate(self::rules()));
        ActivityLog::record('client.updated', "Updated client {$client->displayName()}", $client);

        return response()->json(['data' => $client->toAdminArray()]);
    }

    public function destroy(Client $client): JsonResponse
    {
        abort_if($client->quotes()->exists() || $client->invoices()->exists(), 422, 'This client has quotes or invoices, so it cannot be deleted.');
        $client->delete();
        ActivityLog::record('client.deleted', "Deleted client {$client->displayName()}");

        return response()->json(status: 204);
    }

    /** Creates (or reuses) a client from an enquiry so a quote can be prepared straight away. */
    public function fromEnquiry(Enquiry $enquiry): JsonResponse
    {
        $client = Client::firstOrCreate(
            ['email' => $enquiry->email],
            ['name' => $enquiry->name, 'company' => $enquiry->company, 'phone' => $enquiry->phone, 'enquiry_id' => $enquiry->id],
        );
        if ($client->wasRecentlyCreated) {
            ActivityLog::record('client.created', "Created client {$client->displayName()} from enquiry #{$enquiry->id}", $client);
        }

        return response()->json(['data' => $client->toAdminArray()], $client->wasRecentlyCreated ? 201 : 200);
    }

    private static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'company' => ['nullable', 'string', 'max:160'],
            'email' => ['required', 'string', 'email:rfc,strict', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:400'],
            'tax_number' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
