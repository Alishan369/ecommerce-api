<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class AdminUserSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_the_admin_from_config(): void
    {
        (new AdminUserSeeder)->run();

        $this->assertTrue(User::query()->where('email', 'admin@example.com')->sole()->isAdmin());
    }

    public function test_refuses_an_empty_admin_email(): void
    {
        config(['shop.admin.email' => '']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Set ADMIN_EMAIL');

        (new AdminUserSeeder)->run();
    }

    public function test_production_refuses_the_readme_password(): void
    {
        $this->app['env'] = 'production';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Set a strong ADMIN_PASSWORD');

        (new AdminUserSeeder)->run();
    }

    public function test_production_accepts_a_strong_password(): void
    {
        $this->app['env'] = 'production';
        config(['shop.admin.password' => 'a-long-unique-passphrase']);

        (new AdminUserSeeder)->run();

        $this->assertDatabaseHas('users', ['email' => 'admin@example.com', 'role' => 'admin']);
    }
}
