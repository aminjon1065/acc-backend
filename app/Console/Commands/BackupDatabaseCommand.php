<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class BackupDatabaseCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:db-backup
                            {--retention-days=30 : Delete backup files older than this number of days}
                            {--path= : Directory where backups are stored (defaults to storage/app/backups)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create database backup and prune old backup files';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $connection = (string) config('database.default');
        $backupPath = (string) ($this->option('path') ?: storage_path('app/backups'));
        $retentionDays = (int) $this->option('retention-days');

        File::ensureDirectoryExists($backupPath);

        $filename = sprintf('backup_%s_%s', $connection, now()->format('Ymd_His'));

        try {
            $finalPath = match ($connection) {
                'sqlite' => $this->backupSqlite($backupPath, $filename),
                'pgsql' => $this->backupPostgres($backupPath, $filename),
                'mysql', 'mariadb' => $this->backupMySql($backupPath, $filename),
                default => throw new \RuntimeException("Unsupported database connection [{$connection}] for backup."),
            };
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->pruneOldBackups($backupPath, $retentionDays);

        $this->info("Backup created: {$finalPath}");

        return self::SUCCESS;
    }

    private function backupSqlite(string $backupPath, string $filename): string
    {
        $databasePath = (string) config('database.connections.sqlite.database');

        if ($databasePath === ':memory:') {
            throw new \RuntimeException('SQLite in-memory database cannot be backed up.');
        }

        if (! str_starts_with($databasePath, DIRECTORY_SEPARATOR)) {
            $databasePath = database_path($databasePath);
        }

        if (! File::exists($databasePath)) {
            throw new \RuntimeException("SQLite database file not found at [{$databasePath}].");
        }

        $targetPath = "{$backupPath}/{$filename}.sqlite";
        File::copy($databasePath, $targetPath);

        return $targetPath;
    }

    private function backupPostgres(string $backupPath, string $filename): string
    {
        $config = (array) config('database.connections.pgsql', []);

        $dumpPath = "{$backupPath}/{$filename}.sql";
        $command = sprintf(
            'PGPASSWORD=%s pg_dump -h %s -p %s -U %s -F p -f %s %s',
            escapeshellarg((string) ($config['password'] ?? '')),
            escapeshellarg((string) ($config['host'] ?? '127.0.0.1')),
            escapeshellarg((string) ($config['port'] ?? '5432')),
            escapeshellarg((string) ($config['username'] ?? 'postgres')),
            escapeshellarg($dumpPath),
            escapeshellarg((string) ($config['database'] ?? ''))
        );

        $this->runShellCommand($command, 'pg_dump failed.');

        return $this->gzipFile($dumpPath);
    }

    private function backupMySql(string $backupPath, string $filename): string
    {
        $connection = (string) config('database.default');
        $config = (array) config("database.connections.{$connection}", []);

        $dumpPath = "{$backupPath}/{$filename}.sql";
        $command = sprintf(
            'mysqldump --single-transaction -h %s -P %s -u %s --password=%s %s > %s',
            escapeshellarg((string) ($config['host'] ?? '127.0.0.1')),
            escapeshellarg((string) ($config['port'] ?? '3306')),
            escapeshellarg((string) ($config['username'] ?? 'root')),
            escapeshellarg((string) ($config['password'] ?? '')),
            escapeshellarg((string) ($config['database'] ?? '')),
            escapeshellarg($dumpPath)
        );

        $this->runShellCommand($command, 'mysqldump failed.');

        return $this->gzipFile($dumpPath);
    }

    private function runShellCommand(string $command, string $errorPrefix): void
    {
        $process = Process::fromShellCommandline($command);
        $process->run();

        if (! $process->isSuccessful()) {
            $error = trim($process->getErrorOutput()) ?: trim($process->getOutput());

            throw new \RuntimeException("{$errorPrefix} {$error}");
        }
    }

    private function gzipFile(string $path): string
    {
        $contents = File::get($path);
        $gzPath = "{$path}.gz";
        File::put($gzPath, gzencode($contents, 9));
        File::delete($path);

        return $gzPath;
    }

    private function pruneOldBackups(string $backupPath, int $retentionDays): void
    {
        if ($retentionDays <= 0) {
            return;
        }

        $cutoff = now()->subDays($retentionDays)->timestamp;

        foreach (File::files($backupPath) as $file) {
            if ($file->getMTime() < $cutoff) {
                File::delete($file->getPathname());
            }
        }
    }
}
