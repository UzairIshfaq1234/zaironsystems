<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate(['q' => ['nullable', 'string', 'max:100']]);

        $vendors = Vendor::withCount(['expenses', 'expenses as unpaid_count' => fn ($q) => $q->whereNull('paid_on')])
            ->when($request->input('q'), function ($query, string $q) {
                $like = '%'.addcslashes($q, '%_\\').'%';
                $query->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('email', 'like', $like));
            })
            ->orderBy('name')
            ->limit(500)
            ->get();

        return response()->json(['data' => $vendors->map->toAdminArray()->values()]);
    }

    public function store(Request $request): JsonResponse
    {
        $vendor = Vendor::create($this->validated($request));
        ActivityLog::record('vendor.created', "Added vendor {$vendor->name}", $vendor);

        return response()->json(['data' => $vendor->toAdminArray()], 201);
    }

    public function update(Request $request, Vendor $vendor): JsonResponse
    {
        $vendor->update($this->validated($request));
        ActivityLog::record('vendor.updated', "Updated vendor {$vendor->name}", $vendor);

        return response()->json(['data' => $vendor->toAdminArray()]);
    }

    public function destroy(Vendor $vendor): JsonResponse
    {
        abort_if($vendor->expenses()->exists(), 422, 'This vendor has expenses recorded against it.');
        $vendor->delete();
        ActivityLog::record('vendor.deleted', "Deleted vendor {$vendor->name}");

        return response()->json(status: 204);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['nullable', 'email:rfc', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'tax_number' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
