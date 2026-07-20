<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Health;

use Cbox\LaravelHealth\Contracts\HealthCheck;
use Cbox\LaravelHealth\DataTransferObjects\CheckResult;
use Cbox\LaravelPostal\Contracts\Factory;
use Throwable;

/**
 * Optional cboxdk/laravel-health check: pings every configured Postal server.
 * Only usable when the host app installs cboxdk/laravel-health and lists this
 * class in its `health.checks` config — this package does not require the
 * health package.
 */
class PostalConnectionCheck implements HealthCheck
{
    public function __construct(private readonly Factory $postal) {}

    public function name(): string
    {
        return 'postal';
    }

    public function run(): CheckResult
    {
        $metadata = [];
        $failures = [];

        foreach ($this->postal->names() as $name) {
            try {
                $status = $this->postal->server($name)->ping();
            } catch (Throwable $exception) {
                $failures[] = "{$name}: {$exception->getMessage()}";
                $metadata[$name] = ['ok' => false, 'error' => $exception->getMessage()];

                continue;
            }

            $metadata[$name] = [
                'ok' => $status->ok,
                'rtt_ms' => $status->roundTripMs,
                'url' => $status->url,
            ];

            if (! $status->ok) {
                $failures[] = "{$name}: ".($status->error ?? 'unreachable');
            }
        }

        if ($failures !== []) {
            return CheckResult::critical($this->name(), implode('; ', $failures), $metadata);
        }

        return CheckResult::ok($this->name(), 'All Postal servers reachable and authenticated', $metadata);
    }
}
