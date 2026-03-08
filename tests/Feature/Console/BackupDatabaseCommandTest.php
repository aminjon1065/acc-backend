<?php

use Illuminate\Support\Facades\File;

it('creates sqlite backup file', function () {
    $sqlitePath = storage_path('framework/testing/backup-test.sqlite');
    $backupPath = storage_path('framework/testing/backups');

    File::ensureDirectoryExists(dirname($sqlitePath));
    File::put($sqlitePath, 'sqlite-test-content');
    File::ensureDirectoryExists($backupPath);
    File::cleanDirectory($backupPath);

    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite.database', $sqlitePath);

    $this->artisan("app:db-backup --path={$backupPath} --retention-days=30")
        ->assertExitCode(0);

    $backups = File::files($backupPath);

    expect($backups)->toHaveCount(1);
    expect($backups[0]->getFilename())->toStartWith('backup_sqlite_');
    expect($backups[0]->getExtension())->toBe('sqlite');
});

it('prunes old backup files by retention period', function () {
    $sqlitePath = storage_path('framework/testing/backup-prune.sqlite');
    $backupPath = storage_path('framework/testing/backups-prune');

    File::ensureDirectoryExists(dirname($sqlitePath));
    File::put($sqlitePath, 'sqlite-test-content');
    File::ensureDirectoryExists($backupPath);
    File::cleanDirectory($backupPath);

    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite.database', $sqlitePath);

    $oldFile = "{$backupPath}/old-backup.sqlite";
    File::put($oldFile, 'old-backup');
    touch($oldFile, now()->subDays(40)->timestamp);

    $this->artisan("app:db-backup --path={$backupPath} --retention-days=30")
        ->assertExitCode(0);

    expect(File::exists($oldFile))->toBeFalse();
    expect(count(File::files($backupPath)))->toBe(1);
});
