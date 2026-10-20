<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class AdminUserSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_an_admin_that_can_access_the_panel_and_is_idempotent(): void
    {
        config(['admin.email' => 'admin@anticoburger.it', 'admin.password' => 'una-password-sicura']);

        $this->seed(AdminUserSeeder::class);
        $this->seed(AdminUserSeeder::class);

        $admin = User::query()->sole();
        $this->assertSame('admin@anticoburger.it', $admin->email);
        $this->assertTrue($admin->isAdmin());
        $this->assertTrue(Hash::check('una-password-sicura', $admin->password));

        $this->actingAs($admin)->get('/admin')->assertOk();
    }

    public function test_refuses_to_run_without_a_password(): void
    {
        config(['admin.password' => null]);

        $this->expectException(RuntimeException::class);

        $this->seed(AdminUserSeeder::class);
    }
}
