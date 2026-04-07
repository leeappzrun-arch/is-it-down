<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class PrepareContainerCommandTest extends TestCase
{
    /**
     * The temporary runtime directory used by the container bootstrap tests.
     */
    private string $runtimeDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->runtimeDirectory = storage_path('framework/testing/container-'.Str::random(10));

        File::ensureDirectoryExists($this->runtimeDirectory);
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        File::deleteDirectory($this->runtimeDirectory);

        parent::tearDown();
    }

    public function test_prepare_container_command_creates_the_sqlite_database_runs_migrations_and_bootstraps_the_initial_admin(): void
    {
        $databasePath = $this->configureContainerRuntimeDatabase();

        Config::set('deployment.initial_admin.name', 'Docker Admin');
        Config::set('deployment.initial_admin.email', 'admin@example.com');
        Config::set('deployment.initial_admin.password', 'secret-password');

        $this->artisan('app:prepare-container')
            ->assertSuccessful();

        $this->assertFileExists($databasePath);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->assertDatabaseHas('users', [
            'email' => 'admin@example.com',
            'role' => User::ROLE_ADMIN,
        ]);

        $admin = User::query()->where('email', 'admin@example.com')->first();

        $this->assertNotNull($admin);
        $this->assertSame('Docker Admin', $admin->name);
        $this->assertTrue($admin->isAdmin());
        $this->assertNotNull($admin->email_verified_at);
    }

    public function test_prepare_container_command_promotes_an_existing_matching_user_without_overwriting_the_password(): void
    {
        $this->configureContainerRuntimeDatabase();

        Config::set('deployment.initial_admin.email', null);
        Config::set('deployment.initial_admin.password', null);

        $this->artisan('app:prepare-container')
            ->assertSuccessful();

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $user = User::query()->create([
            'name' => 'Existing User',
            'email' => 'owner@example.com',
            'password' => 'existing-password',
            'role' => User::ROLE_USER,
            'email_verified_at' => null,
        ]);

        $hashedPassword = $user->password;

        Config::set('deployment.initial_admin.name', 'Ignored Name');
        Config::set('deployment.initial_admin.email', 'owner@example.com');
        Config::set('deployment.initial_admin.password', 'new-password');

        $this->artisan('app:prepare-container')
            ->assertSuccessful();

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $user->refresh();

        $this->assertTrue($user->isAdmin());
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame($hashedPassword, $user->password);
        $this->assertSame('Existing User', $user->name);
    }

    public function test_runtime_dockerfile_declares_the_persistent_app_data_volume(): void
    {
        $dockerfile = File::get(base_path('Dockerfile'));

        $this->assertStringContainsString(
            'VOLUME ["/var/www/html/database/data"]',
            $dockerfile
        );

        $this->assertTrue(
            Str::contains($dockerfile, 'APP_DATA_PATH=/var/www/html/database/data')
        );
    }

    /**
     * Point the application at a temporary SQLite runtime database.
     */
    private function configureContainerRuntimeDatabase(): string
    {
        $databasePath = $this->runtimeDirectory.DIRECTORY_SEPARATOR.'database.sqlite';

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', $databasePath);

        DB::purge('sqlite');

        return $databasePath;
    }
}
