<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $logs = $this->buildFilteredQuery($request)->paginate(20)->withQueryString();
        $eventTypes = AuditLog::query()->select('event_type')->distinct()->orderBy('event_type')->pluck('event_type');

        return view('admin.audit-logs.index', [
            'logs' => $logs,
            'eventTypes' => $eventTypes,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $query = $this->buildFilteredQuery($request);
        $filename = 'audit_logs_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'w');
            // UTF-8 BOM so Microsoft Excel opens accented Filipino names cleanly
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Timestamp', 'Actor Name', 'Actor Email', 'Event Type', 'Previous Grade', 'Updated Grade', 'Reason', 'IP Address']);

            $query->chunk(500, function ($chunk) use ($out): void {
                foreach ($chunk as $log) {
                    fputcsv($out, [
                        $log->created_at?->format('Y-m-d H:i:s'),
                        $log->actor?->name ?? 'System',
                        $log->actor?->email ?? '',
                        $log->event_type,
                        $log->previous_grade ?? '',
                        $log->updated_grade ?? '',
                        $log->reason ?? '',
                        $log->ip_address ?? '',
                    ]);
                }
            });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function buildFilteredQuery(Request $request): Builder
    {
        $query = AuditLog::query()->with('actor')->latest('created_at');

        if ($event = trim((string) $request->query('event_type'))) {
            $query->where('event_type', $event);
        }

        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('event_type', 'ilike', "%{$search}%")
                    ->orWhere('reason', 'ilike', "%{$search}%")
                    ->orWhere('ip_address', 'ilike', "%{$search}%")
                    ->orWhereHas('actor', fn ($actorQ) => $actorQ->where('name', 'ilike', "%{$search}%")->orWhere('email', 'ilike', "%{$search}%"));
            });
        }

        return $query;
    }
}
