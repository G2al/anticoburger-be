<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class GrantAdminCommand extends Command
{
    protected $signature = 'app:grant-admin
        {email : Email of an existing user}
        {--revoke : Remove the administrator role instead of granting it}';

    protected $description = 'Grant (or revoke) access to the Filament admin panel for an existing user';

    public function handle(): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();

        if ($user === null) {
            $this->error('Nessun utente trovato con questa email.');

            return self::FAILURE;
        }

        $isAdmin = ! $this->option('revoke');
        $user->forceFill(['is_admin' => $isAdmin])->save();

        $this->info($isAdmin
            ? "L'utente {$user->email} è ora amministratore."
            : "L'utente {$user->email} non è più amministratore.");

        return self::SUCCESS;
    }
}
