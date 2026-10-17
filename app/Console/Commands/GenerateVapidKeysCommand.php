<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;
use Throwable;

class GenerateVapidKeysCommand extends Command
{
    protected $signature = 'app:vapid-keys';

    protected $description = 'Generate a VAPID key pair for Web Push (prints it, does not modify .env)';

    public function handle(): int
    {
        try {
            $keys = VAPID::createVapidKeys();
        } catch (Throwable $exception) {
            $this->error('Impossibile generare le chiavi: '.$exception->getMessage());
            $this->line('Su Windows imposta la variabile OPENSSL_CONF verso un file openssl.cnf prima di avviare PHP.');

            return self::FAILURE;
        }

        $this->info('Aggiungi queste righe al file .env (genera le chiavi una sola volta e conservale):');
        $this->newLine();
        $this->line('VAPID_PUBLIC_KEY='.$keys['publicKey']);
        $this->line('VAPID_PRIVATE_KEY='.$keys['privateKey']);
        $this->line('VAPID_SUBJECT=mailto:info@anticoburger.it');

        return self::SUCCESS;
    }
}
