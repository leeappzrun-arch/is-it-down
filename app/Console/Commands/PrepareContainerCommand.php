<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;

#[Signature('app:prepare-container')]
#[Description('Prepare the application for containerized production startup.')]
class PrepareContainerCommand extends Command
{
    public function __construct(
        private readonly Filesystem $files,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->prepareSqliteDatabase();

        $this->call('migrate', [
            '--force' => true,
        ]);

        $this->call('storage:link', [
            '--force' => true,
        ]);

        $this->ensureInitialAdmin();

        $this->components->info('Container preparation complete.');

        return self::SUCCESS;
    }

    /**
     * Create the configured SQLite database file when it does not exist yet.
     */
    private function prepareSqliteDatabase(): void
    {
        if (config('database.default') !== 'sqlite') {
            return;
        }

        $databasePath = (string) config('database.connections.sqlite.database');

        if ($databasePath === '' || $databasePath === ':memory:') {
            return;
        }

        $directory = dirname($databasePath);

        $this->files->ensureDirectoryExists($directory);

        if (! $this->files->exists($databasePath)) {
            $this->files->put($databasePath, '');

            $this->components->info("Created SQLite database at [{$databasePath}].");
        }

        DB::purge('sqlite');
    }

    /**
     * Ensure an initial administrator exists when container bootstrap credentials are configured.
     */
    private function ensureInitialAdmin(): void
    {
        $email = trim((string) config('deployment.initial_admin.email'));
        $password = (string) config('deployment.initial_admin.password');

        if ($email === '' || $password === '') {
            $this->components->twoColumnDetail('Initial admin', 'Skipped');

            return;
        }

        $name = trim((string) config('deployment.initial_admin.name'));
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            User::query()->forceCreate([
                'name' => $name === '' ? 'Administrator' : $name,
                'email' => $email,
                'password' => $password,
                'role' => User::ROLE_ADMIN,
                'email_verified_at' => now(),
            ]);

            $this->components->info("Created the initial admin user [{$email}].");

            return;
        }

        $updates = [];

        if (! $user->isAdmin()) {
            $updates['role'] = User::ROLE_ADMIN;
        }

        if ($user->email_verified_at === null) {
            $updates['email_verified_at'] = now();
        }

        if ($updates === []) {
            $this->components->twoColumnDetail('Initial admin', 'Already ready');

            return;
        }

        $user->forceFill($updates)->save();

        $this->components->info("Updated the existing user [{$email}] for container access.");
    }
}
