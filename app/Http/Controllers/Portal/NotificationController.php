<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

/** The signed-in employee's own alerts (the portal bell). Employees only ever see or change their own. */
class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user('employee');

        return response()->json([
            'data' => $employee->notifications()->latest()->limit(30)->get()->map(fn (DatabaseNotification $n) => [
                'id' => $n->id,
                ...$n->data,
                'read' => $n->read_at !== null,
                'created_at' => $n->created_at?->toIso8601String(),
            ]),
            'unread' => $employee->unreadNotifications()->count(),
        ]);
    }

    public function read(Request $request, string $id): JsonResponse
    {
        $employee = $request->user('employee');
        $employee->notifications()->whereKey($id)->firstOrFail()->markAsRead();

        return response()->json(['unread' => $employee->unreadNotifications()->count()]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $employee = $request->user('employee');
        $employee->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['unread' => 0]);
    }
}
