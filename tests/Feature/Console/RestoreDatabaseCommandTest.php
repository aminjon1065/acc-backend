<?php

use Illuminate\Support\Facades\File;

it('restores sqlite database from backup file', function () {
    $targetPath = storage_path('framework/testing/restore-target.sqlite');
    $backupPath = storage_path('framework/testing/restore-backup.sqlite');

    File::ensureDirectoryExists(dirname($targetPath));
    File::put($targetPath, 'old-content');
    File::put($backupPath, 'new-content');

    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite.database', $targetPath);

    $this->artisan("app:db-restore {$backupPath} --force")
        ->assertExitCode(0);

    expect(File::get($targetPath))->toBe('new-content');
});

it('fails when backup file does not exist', function () {
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite.database', storage_path('framework/testing/restore-missing.sqlite'));

    $this->artisan('app:db-restore /tmp/non-existing-backup.sqlite --force')
        ->assertExitCode(1);
});

it('fails sqlite restore when extension is invalid', function () {
    $targetPath = storage_path('framework/testing/restore-invalid-target.sqlite');
    $backupPath = storage_path('framework/testing/restore-invalid.sql');

    File::ensureDirectoryExists(dirname($targetPath));
    File::put($targetPath, 'target');
    File::put($backupPath, 'sql-content');

    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite.database', $targetPath);

    $this->artisan("app:db-restore {$backupPath} --force")
        ->assertExitCode(1);
});
