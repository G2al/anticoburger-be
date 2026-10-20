<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Creates (or restores) the administrator of the Filament panel:
 * `php artisan db:seed --class=AdminUserSeeder --force`.
 *
 * Credentials come from config/admin.php (ADMIN_EMAIL, ADMIN_NAME, ADMIN_PASSWORD in .env),
 * never from the code. Idempotent: an existing user with that email is updated and made admin.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = (string) config('admin.email');
        $password = (string) config('admin.password');

        if ($password === '') {
            throw new RuntimeException('Imposta ADMIN_PASSWORD nel file .env prima di lanciare AdminUserSeeder.');
        }

        $user = User::query()->firstOrNew(['email' => $email]);
        $user->forceFill([
            'name' => (string) config('admin.name'),
            'password' => $password,
            'email_verified_at' => $user->email_verified_at ?? now(),
            'is_admin' => true,
        ])->save();

        $this->command?->info("Amministratore pronto: {$email}");
    }
}
