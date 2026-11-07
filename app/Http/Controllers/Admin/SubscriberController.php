<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin;
use App\Models\ActivityLog;
use App\Models\Subscriber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SubscriberController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate(['q' => ['nullable', 'string', 'max:100']]);

        $page = Subscriber::query()
            ->when($request->input('q'), fn ($query, string $q) => $query->where('email', 'like', '%'.addcslashes($q, '%_\\').'%'))
            ->latest()
            ->paginate(20);

        return response()->json(Admin::page($page, fn ($s) => Admin::subscriber($s)));
    }

    public function destroy(Subscriber $subscriber): JsonResponse
    {
        $subscriber->delete();
        ActivityLog::record('subscriber.deleted', "Removed subscriber {$subscriber->email}");

        return response()->json(status: 204);
    }

    public function export(): StreamedResponse
    {
        ActivityLog::record('subscriber.exported', 'Exported the subscriber list');

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['email', 'subscribed_at'], escape: '');
            Subscriber::orderBy('id')->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $s) {
                    fputcsv($out, [self::safeCell($s->email), $s->created_at?->toDateTimeString()], escape: '');
                }
            });
            fclose($out);
        }, 'zairon-subscribers-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Neutralise spreadsheet formula injection (cells starting with = + - @). */
    public static function safeCell(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }
}
