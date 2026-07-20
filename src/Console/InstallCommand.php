<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Console;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'postal:install {--migrations : Also copy the migrations into the app for editing}';

    protected $description = 'Publish the Postal config and print the setup checklist';

    public function handle(): int
    {
        $this->call('vendor:publish', ['--tag' => 'postal-config']);

        if ((bool) $this->option('migrations')) {
            $this->call('vendor:publish', ['--tag' => 'postal-migrations']);
        }

        $this->newLine();
        $this->components->info('Cbox Postal is installed. Next steps:');

        foreach ([
            'Set POSTAL_URL and POSTAL_KEY in .env (Postal admin → your mail server → Credentials → API).',
            'Run "php artisan migrate" to create the status store tables.',
            'Verify connectivity: "php artisan postal:ping".',
            'Webhooks: add a webhook in Postal pointing at /postal/webhook/{server}, then fetch the signing key with "php artisan postal:webhook-key" and set POSTAL_WEBHOOK_PUBLIC_KEY.',
            'Inbound (optional): add a Route → HTTP Endpoint in Postal pointing at /postal/inbound/{server} (BodyAsJSON).',
            'Mail transport (optional): add a "postal" mailer in config/mail.php and set MAIL_MAILER=postal.',
            'Check everything at once: "php artisan postal:doctor".',
        ] as $index => $step) {
            $this->components->twoColumnDetail('<info>'.($index + 1).'</info>', $step);
        }

        $this->newLine();
        $this->line('  Docs: https://github.com/cboxdk/laravel-postal/tree/main/docs');

        return self::SUCCESS;
    }
}
