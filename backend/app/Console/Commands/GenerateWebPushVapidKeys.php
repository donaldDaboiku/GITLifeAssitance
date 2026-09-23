<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

class GenerateWebPushVapidKeys extends Command
{
    protected $signature = 'webpush:vapid';

    protected $description = 'Generate VAPID keys for Web Push and print .env lines.';

    public function handle(): int
    {
        try {
            $keys = VAPID::createVapidKeys();
        } catch (\Throwable $exception) {
            $this->error('OpenSSL could not create EC keys on this PHP build ('.$exception->getMessage().').');
            $this->line('Generate keys with Node instead, then paste into backend/.env:');
            $this->line('  npx --yes web-push generate-vapid-keys');
            $this->line('WEBPUSH_VAPID_SUBJECT='.(config('app.url') ?: 'mailto:hello@gitlife.local'));

            return self::FAILURE;
        }

        $this->info('Add these to backend/.env:');
        $this->line('WEBPUSH_VAPID_PUBLIC_KEY='.$keys['publicKey']);
        $this->line('WEBPUSH_VAPID_PRIVATE_KEY='.$keys['privateKey']);
        $this->line('WEBPUSH_VAPID_SUBJECT='.(config('app.url') ?: 'mailto:hello@gitlife.local'));

        return self::SUCCESS;
    }
}
