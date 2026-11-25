<?php

namespace App\Services\Maintenance;

use App\Models\DatabaseBackup;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class DatabaseBackupService
{
    private const BACKUP_DIR = 'backups';

    public function __construct(private readonly AuditLogService $auditLog)
    {
    }

    /**
     * Create a logical PostgreSQL snapshot stored safely under storage/app/backups.
     * Uses pure PDO PostgreSQL schema/data export so it works reliably on Windows/Laragon
     * and Linux without requiring pg_dump on PATH.
     */
    public function createBackup(User $actor, ?string $notes = null): DatabaseBackup
    {
        $timestamp = now()->format('Ymd_His') . '_' . substr(md5((string) microtime(true)), 0, 6);
        $filename = "grading_system_backup_{$timestamp}.json";
        $relativePath = self::BACKUP_DIR . '/' . $filename;

        $tables = DB::select("
            SELECT tablename
            FROM pg_tables
            WHERE schemaname = 'public'
              AND tablename NOT IN ('migrations', 'database_backups')
            ORDER BY tablename
        ");

        $payload = [
            'meta' => [
                'app' => config('app.name'),
                'database' => config('database.connections.pgsql.database'),
                'created_at' => now()->toIso8601String(),
                'created_by' => $actor->email,
                'table_count' => count($tables),
            ],
            'tables' => [],
        ];

        foreach ($tables as $t) {
            $tableName = $t->tablename;
            $rows = DB::table($tableName)->get()->map(fn ($r) => (array) $r)->all();
            $payload['tables'][$tableName] = $rows;
        }

        $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            throw new RuntimeException('Failed to serialize database backup.');
        }

        Storage::disk('local')->put($relativePath, $encoded);
        $size = Storage::disk('local')->size($relativePath);

        $backup = DatabaseBackup::query()->create([
            'filename' => $filename,
            'disk' => 'local',
            'format' => 'json',
            'size_bytes' => $size,
            'status' => 'completed',
            'created_by' => $actor->id,
            'notes' => $notes ? trim($notes) : null,
        ]);

        $this->auditLog->record(
            eventType: 'database_backup.created',
            auditable: $backup,
            newValues: ['filename' => $filename, 'size_bytes' => $size, 'tables' => count($tables)],
            actor: $actor
        );

        return $backup;
    }

    /**
     * Restore a verified logical PostgreSQL backup inside a single database transaction.
     */
    public function restoreBackup(DatabaseBackup $backup, User $actor): void
    {
        $relativePath = self::BACKUP_DIR . '/' . $backup->filename;
        if (! Storage::disk($backup->disk)->exists($relativePath)) {
            throw new RuntimeException("Backup file [{$backup->filename}] was not found on disk.");
        }

        $decoded = json_decode((string) Storage::disk($backup->disk)->get($relativePath), true);
        if (! is_array($decoded) || ! isset($decoded['tables']) || ! is_array($decoded['tables'])) {
            throw new RuntimeException('Backup file is corrupted or has an invalid format.');
        }

        $tablesData = $decoded['tables'];
        $existingTables = collect(DB::select("SELECT tablename FROM pg_tables WHERE schemaname = 'public'"))
            ->pluck('tablename')
            ->all();

        $targetTables = array_values(array_filter(
            array_keys($tablesData),
            fn (string $t) => in_array($t, $existingTables, true) && ! in_array($t, ['migrations', 'database_backups'], true)
        ));

        DB::transaction(function () use ($targetTables, $tablesData): void {
            if (! empty($targetTables)) {
                $quoted = implode(', ', array_map(fn (string $t) => '"' . str_replace('"', '', $t) . '"', $targetTables));
                DB::statement("TRUNCATE TABLE {$quoted} RESTART IDENTITY CASCADE");
            }

            // Temporarily disable FK trigger checks during bulk load inside this transaction (safe on PostgreSQL)
            DB::statement("SET session_replication_role = 'replica'");

            try {
                foreach ($targetTables as $tableName) {
                    $rows = $tablesData[$tableName] ?? [];
                    foreach (array_chunk($rows, 200) as $chunk) {
                        if (! empty($chunk)) {
                            DB::table($tableName)->insert($chunk);
                        }
                    }

                    // Sync PostgreSQL serial sequence if table has an `id` column
                    $hasId = DB::selectOne("
                        SELECT 1 FROM information_schema.columns
                        WHERE table_schema = 'public' AND table_name = ? AND column_name = 'id'
                    ", [$tableName]);

                    if ($hasId) {
                        $seq = DB::selectOne("SELECT pg_get_serial_sequence(?, 'id') AS seq", [$tableName]);
                        if ($seq && $seq->seq) {
                            DB::statement("SELECT setval(?, COALESCE((SELECT MAX(id) FROM \"{$tableName}\"), 1), true)", [$seq->seq]);
                        }
                    }
                }
            } finally {
                DB::statement("SET session_replication_role = 'origin'");
            }
        });

        $backup->update(['restored_at' => now()]);

        $this->auditLog->record(
            eventType: 'database_backup.restored',
            auditable: $backup,
            newValues: ['filename' => $backup->filename, 'restored_tables' => count($targetTables)],
            actor: $actor
        );
    }

    public function deleteBackup(DatabaseBackup $backup, User $actor): void
    {
        $relativePath = self::BACKUP_DIR . '/' . $backup->filename;
        if (Storage::disk($backup->disk)->exists($relativePath)) {
            Storage::disk($backup->disk)->delete($relativePath);
        }

        $filename = $backup->filename;
        $backup->delete();

        $this->auditLog->record(
            eventType: 'database_backup.deleted',
            oldValues: ['filename' => $filename],
            actor: $actor
        );
    }

    public function getPath(DatabaseBackup $backup): string
    {
        return Storage::disk($backup->disk)->path(self::BACKUP_DIR . '/' . $backup->filename);
    }
}
