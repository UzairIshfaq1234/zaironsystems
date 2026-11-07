<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin;
use App\Models\ActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate(['q' => ['nullable', 'string', 'max:100']]);

        $page = ActivityLog::with('user')
            ->when($request->input('q'), fn ($query, string $q) => $query->where('description', 'like', '%'.addcslashes($q, '%_\\').'%'))
            ->latest('id')
            ->paginate(25);

        return response()->json(Admin::page($page, fn ($a) => Admin::activity($a)));
    }
}
