<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Console;

use Cbox\LaravelPostal\Support\Coerce;
use Cbox\LaravelPostal\Support\JwkConverter;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Factory as HttpFactory;
use Throwable;

/**
 * Fetches the Postal install's webhook signing public key from its JWKS
 * endpoint and prints it as PEM — paste-ready for POSTAL_WEBHOOK_PUBLIC_KEY.
 */
class WebhookKeyCommand extends Command
{
    protected $signature = 'postal:webhook-key {server? : Server name whose base URL to query}';

    protected $description = 'Fetch the webhook signing public key from Postal (/.well-known/jwks.json) as PEM';

    public function handle(HttpFactory $http): int
    {
        $server = $this->argument('server');
        $name = is_string($server) ? $server : Coerce::string(config('postal.default'), 'default');

        $url = Coerce::stringOrNull(config("postal.servers.{$name}.url"));

        if ($url === null || $url === '') {
            $this->error("Postal server [{$name}] has no URL configured.");

            return self::FAILURE;
        }

        $jwksUrl = rtrim($url, '/').'/.well-known/jwks.json';

        try {
            $response = $http->acceptJson()->get($jwksUrl);
        } catch (Throwable $exception) {
            $this->error("Could not fetch {$jwksUrl}: {$exception->getMessage()}");

            return self::FAILURE;
        }

        if (! $response->successful()) {
            $this->error("Could not fetch {$jwksUrl} (HTTP {$response->status()}).");

            return self::FAILURE;
        }

        $keys = $response->json('keys');

        if (! is_array($keys) || $keys === [] || ! is_array($keys[0] ?? null)) {
            $this->error('The JWKS response contained no keys.');

            return self::FAILURE;
        }

        try {
            $pem = JwkConverter::toPem(Coerce::map($keys[0]));
        } catch (Throwable $exception) {
            $this->error("Could not convert the JWK to PEM: {$exception->getMessage()}");

            return self::FAILURE;
        }

        $this->line($pem);
        $this->components->info('Set this as POSTAL_WEBHOOK_PUBLIC_KEY (escape newlines as \n in .env).');

        return self::SUCCESS;
    }
}
