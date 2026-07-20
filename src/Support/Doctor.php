<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Support;

use Cbox\LaravelPostal\Contracts\Factory;
use Cbox\LaravelPostal\Contracts\ServerRegistry;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Database\Schema\Builder as SchemaBuilder;
use Illuminate\Routing\Router;
use Throwable;

/**
 * Runs the postal:doctor diagnostics: configuration validity, signing key,
 * routes, store tables, mailer wiring and (optionally) live connectivity.
 */
class Doctor
{
    public function __construct(
        private readonly ServerRegistry $registry,
        private readonly Factory $postal,
        private readonly WebhookConfig $webhooks,
        private readonly InboundConfig $inbound,
        private readonly ConfigRepository $config,
        private readonly SchemaBuilder $schema,
        private readonly Router $router,
    ) {}

    /**
     * @return list<DoctorCheck>
     */
    public function run(bool $ping = true): array
    {
        return [
            ...$this->checkServers($ping),
            ...$this->checkSigningKey(),
            ...$this->checkRoutes(),
            ...$this->checkStore(),
            ...$this->checkMailer(),
        ];
    }

    /**
     * @return list<DoctorCheck>
     */
    private function checkServers(bool $ping): array
    {
        $checks = [];
        $names = $this->registry->names();

        if ($names === []) {
            return [DoctorCheck::failure('servers', 'configured', 'No servers configured — add entries under postal.servers or bind a ServerRegistry.')];
        }

        $default = Coerce::string($this->config->get('postal.default'), 'default');

        $checks[] = in_array($default, $names, true)
            ? DoctorCheck::ok('servers', 'default', "Default server [{$default}] exists.")
            : DoctorCheck::failure('servers', 'default', "postal.default is [{$default}] but no such server is configured.");

        foreach ($names as $name) {
            try {
                $config = $this->registry->find($name);
            } catch (Throwable $exception) {
                $checks[] = DoctorCheck::failure('servers', $name, $exception->getMessage());

                continue;
            }

            if ($config === null) {
                $checks[] = DoctorCheck::failure('servers', $name, 'Registry lists this name but returns no configuration for it.');

                continue;
            }

            $capabilities = $config->type->value.($config->hasApi() ? ', api lookups' : ', no api lookups');
            $checks[] = DoctorCheck::ok('servers', $name, "Valid ({$capabilities}).");

            if (! $ping) {
                continue;
            }

            try {
                $status = $this->postal->server($name)->ping();

                $checks[] = $status->ok
                    ? DoctorCheck::ok('connectivity', $name, sprintf('Reachable and authenticated (%.1f ms).', $status->roundTripMs))
                    : DoctorCheck::failure('connectivity', $name, $status->error ?? 'Unreachable.');
            } catch (Throwable $exception) {
                $checks[] = DoctorCheck::failure('connectivity', $name, $exception->getMessage());
            }
        }

        return $checks;
    }

    /**
     * @return list<DoctorCheck>
     */
    private function checkSigningKey(): array
    {
        $verificationUsed = ($this->webhooks->enabled && $this->webhooks->verifySignature)
            || ($this->inbound->enabled && $this->inbound->verifySignature);

        if (! $verificationUsed) {
            return [DoctorCheck::warning('signing', 'verification', 'Signature verification is disabled — only acceptable in local development.')];
        }

        $key = $this->webhooks->publicKey;

        if ($key === null || trim($key) === '') {
            return [DoctorCheck::failure('signing', 'public key', 'No POSTAL_WEBHOOK_PUBLIC_KEY configured — every webhook/inbound delivery will be rejected. Run: php artisan postal:webhook-key')];
        }

        return openssl_pkey_get_public($key) === false
            ? [DoctorCheck::failure('signing', 'public key', 'POSTAL_WEBHOOK_PUBLIC_KEY is not valid PEM (check \n escaping in .env).')]
            : [DoctorCheck::ok('signing', 'public key', 'Valid PEM public key configured.')];
    }

    /**
     * Checks the actual router, not just config — so a stale route cache
     * (route:cache run before a config change) is caught too.
     *
     * @return list<DoctorCheck>
     */
    private function checkRoutes(): array
    {
        return [
            $this->checkRoute('webhooks', $this->webhooks->enabled, 'postal.webhook', $this->webhooks->path),
            $this->checkRoute('inbound', $this->inbound->enabled, 'postal.inbound', $this->inbound->path),
        ];
    }

    private function checkRoute(string $name, bool $enabled, string $routeName, string $path): DoctorCheck
    {
        $registered = $this->router->has($routeName);

        if ($enabled) {
            return $registered
                ? DoctorCheck::ok('routes', $name, "POST /{$path}/{server?} registered.")
                : DoctorCheck::failure('routes', $name, "Enabled but route [{$routeName}] is not registered — stale route cache? Run: php artisan route:clear");
        }

        return $registered
            ? DoctorCheck::warning('routes', $name, "Disabled in config but route [{$routeName}] is still registered — stale route cache; run: php artisan route:clear")
            : DoctorCheck::warning('routes', $name, ucfirst($name).' receiving is disabled.');
    }

    /**
     * @return list<DoctorCheck>
     */
    private function checkStore(): array
    {
        if (! $this->webhooks->store && ! $this->inbound->store) {
            return [DoctorCheck::warning('store', 'tables', 'Store disabled — no status rows, and idempotency falls to your listeners.')];
        }

        try {
            $messages = $this->schema->hasTable('postal_messages');
            $events = $this->schema->hasTable('postal_message_events');
        } catch (Throwable $exception) {
            return [DoctorCheck::failure('store', 'tables', "Could not inspect the database: {$exception->getMessage()}")];
        }

        return $messages && $events
            ? [DoctorCheck::ok('store', 'tables', 'postal_messages and postal_message_events exist.')]
            : [DoctorCheck::failure('store', 'tables', 'Store is enabled but its tables are missing — run: php artisan migrate')];
    }

    /**
     * @return list<DoctorCheck>
     */
    private function checkMailer(): array
    {
        $mailers = Coerce::map($this->config->get('mail.mailers', []));

        foreach ($mailers as $name => $mailer) {
            if (is_array($mailer) && ($mailer['transport'] ?? null) === 'postal') {
                $default = Coerce::string($this->config->get('mail.default'), '');

                return [DoctorCheck::ok(
                    'mailer',
                    'transport',
                    "Mailer [{$name}] uses the postal transport".($default === $name ? ' and is the default mailer.' : '.'),
                )];
            }
        }

        return [DoctorCheck::warning('mailer', 'transport', 'No mailer uses the postal transport — fine if you only send via the Postal facade/channel.')];
    }
}
