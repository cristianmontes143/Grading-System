<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DatabaseBackup;
use App\Services\Maintenance\DatabaseBackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class DatabaseBackupController extends Controller
{
    public function __construct(private readonly DatabaseBackupService $backupService)
    {
    }

    public function index(): View
    {
        $backups = DatabaseBackup::query()->with('creator')->latest()->get();

        return view('admin.backups.index', [
            'backups' => $backups,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $backup = $this->backupService->createBackup($request->user(), $validated['notes'] ?? null);

        return redirect()
            ->route('admin.backups.index')
            ->with('status', "Database backup \"{$backup->filename}\" ({$backup->human_size}) created successfully.");
    }

    public function download(DatabaseBackup $databaseBackup): BinaryFileResponse
    {
        $path = $this->backupService->getPath($databaseBackup);
        abort_unless(file_exists($path), 404, 'Backup file no longer exists on disk.');

        return response()->download($path, $databaseBackup->filename);
    }

    public function restore(Request $request, DatabaseBackup $databaseBackup): RedirectResponse
    {
        try {
            $this->backupService->restoreBackup($databaseBackup, $request->user());
        } catch (Throwable $e) {
            return redirect()
                ->route('admin.backups.index')
                ->with('error', 'Restore failed: ' . $e->getMessage());
        }

        return redirect()
            ->route('admin.backups.index')
            ->with('status', "Database restored from \"{$databaseBackup->filename}\".");
    }

    public function destroy(Request $request, DatabaseBackup $databaseBackup): RedirectResponse
    {
        $this->backupService->deleteBackup($databaseBackup, $request->user());

        return redirect()
            ->route('admin.backups.index')
            ->with('status', 'Backup deleted.');
    }
}
