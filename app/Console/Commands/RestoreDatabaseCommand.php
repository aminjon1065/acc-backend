<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class RestoreDatabaseCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:db-restore
                            {file : Path to backup file}
                            {--force : Confirm restoring without interactive prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Restore database from backup file';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $backupFile = (string) $this->argument('file');

        if (! File::exists($backupFile)) {
            $this->error("Backup file not found: {$backupFile}");

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('This will overwrite current database data. Continue?')) {
            $this->warn('Restore cancelled.');

            return self::INVALID;
        }

        $connection = (string) config('database.default');

        try {
            match ($connection) {
                'sqlite' => $this->restoreSqlite($backupFile),
                'pgsql' => $this->restorePostgres($backupFile),
                'mysql', 'mariadb' => $this->restoreMySql($backupFile),
                default => throw new \RuntimeException("Unsupported database connection [{$connection}] for restore."),
            };
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Database restored successfully.');

        return self::SUCCESS;
    }

    private function restoreSqlite(string $backupFile): void
    {
        if (! str_ends_with($backupFile, '.sqlite')) {
            throw new \RuntimeException('SQLite restore expects a .sqlite backup file.');
        }

        $databasePath = (string) config('database.connections.sqlite.database');

        if ($databasePath === ':memory:') {
            throw new \RuntimeException('Cannot restore into SQLite in-memory database.');
        }

        if (! str_starts_with($databasePath, DIRECTORY_SEPARATOR)) {
            $databasePath = database_path($databasePath);
        }

        File::ensureDirectoryExists(dirname($databasePath));
        File::copy($backupFile, $databasePath);
    }

    private function restorePostgres(string $backupFile): void
    {
        $config = (array) config('database.connections.pgsql', []);
        $sqlFile = $this->prepareSqlFile($backupFile);

        $command = sprintf(
            'PGPASSWORD=%s psql -h %s -p %s -U %s -d %s -f %s',
            escapeshellarg((string) ($config['password'] ?? '')),
            escapeshellarg((string) ($config['host'] ?? '127.0.0.1')),
            escapeshellarg((string) ($config['port'] ?? '5432')),
            escapeshellarg((string) ($config['username'] ?? 'postgres')),
            escapeshellarg((string) ($config['database'] ?? '')),
            escapeshellarg($sqlFile)
        );

        $this->runShellCommand($command, 'psql restore failed.');

        if ($sqlFile !== $backupFile) {
            File::delete($sqlFile);
        }
    }

    private function restoreMySql(string $backupFile): void
    {
        $connection = (string) config('database.default');
        $config = (array) config("database.connections.{$connection}", []);
        $sqlFile = $this->prepareSqlFile($backupFile);

        $command = sprintf(
            'mysql -h %s -P %s -u %s --password=%s %s < %s',
            escapeshellarg((string) ($config['host'] ?? '127.0.0.1')),
            escapeshellarg((string) ($config['port'] ?? '3306')),
            escapeshellarg((string) ($config['username'] ?? 'root')),
            escapeshellarg((string) ($config['password'] ?? '')),
            escapeshellarg((string) ($config['database'] ?? '')),
            escapeshellarg($sqlFile)
        );

        $this->runShellCommand($command, 'mysql restore failed.');

        if ($sqlFile !== $backupFile) {
            File::delete($sqlFile);
        }
    }

    private function prepareSqlFile(string $backupFile): string
    {
        if (str_ends_with($backupFile, '.sql')) {
            return $backupFile;
        }

        if (! str_ends_with($backupFile, '.sql.gz')) {
            throw new \RuntimeException('SQL restore expects .sql or .sql.gz file.');
        }

        $decompressed = storage_path('framework/cache/'.basename($backupFile, '.gz'));
        File::ensureDirectoryExists(dirname($decompressed));

        $decoded = gzdecode((string) File::get($backupFile));

        if ($decoded === false) {
            throw new \RuntimeException('Unable to decode gzip backup file.');
        }

        File::put($decompressed, $decoded);

        return $decompressed;
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
}
