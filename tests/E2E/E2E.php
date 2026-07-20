<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Tests\E2E;

use Cbox\LaravelPostal\Facades\Postal;
use Closure;
use Illuminate\Contracts\Foundation\Application;
use RuntimeException;

/**
 * Helpers for the end-to-end suite that runs against a real Postal install
 * (see e2e/run.sh). Every test self-skips unless the POSTAL_E2E_* env vars
 * are present, so the suite is inert in normal runs.
 */
class E2E
{
    public static function enabled(): bool
    {
        return self::env('POSTAL_E2E_URL') !== null;
    }

    /**
     * Point the package at the real install: an api-type default server, an
     * smtp-api variant and an smtp-type connection — all three protocols.
     */
    public static function configure(Application $app): void
    {
        $url = self::require('POSTAL_E2E_URL');
        $key = self::require('POSTAL_E2E_KEY');

        $config = $app->make('config');

        $config->set('postal.default', 'default');
        $config->set('postal.servers', [
            'default' => ['url' => $url, 'key' => $key],
            'raw' => ['url' => $url, 'key' => $key, 'type' => 'smtp-api'],
            'smtp' => [
                'type' => 'smtp',
                'url' => $url,
                'key' => $key,
                'smtp' => [
                    'host' => self::require('POSTAL_E2E_SMTP_HOST'),
                    'port' => (int) self::require('POSTAL_E2E_SMTP_PORT'),
                    'username' => 'e2e',
                    'password' => self::require('POSTAL_E2E_SMTP_KEY'),
                ],
            ],
            // Unauthenticated connection to Postal's SMTP port — how the
            // outside world delivers inbound mail to routed domains.
            'smtp-inbound' => [
                'type' => 'smtp',
                'smtp' => [
                    'host' => self::require('POSTAL_E2E_SMTP_HOST'),
                    'port' => (int) self::require('POSTAL_E2E_SMTP_PORT'),
                ],
            ],
        ]);

        $app->forgetInstance('postal');
        Postal::clearResolvedInstances();
    }

    public static function captureDir(): string
    {
        return self::require('POSTAL_E2E_CAPTURE_DIR');
    }

    public static function mailpitUrl(): string
    {
        return self::require('POSTAL_E2E_MAILPIT_URL');
    }

    /**
     * Poll until the probe returns non-null, or fail with the given message.
     *
     * @template TResult
     *
     * @param  Closure(): (TResult|null)  $probe
     * @return TResult
     */
    public static function waitFor(Closure $probe, string $what, int $timeoutSeconds = 60): mixed
    {
        $deadline = microtime(true) + $timeoutSeconds;

        while (microtime(true) < $deadline) {
            $result = $probe();

            if ($result !== null) {
                return $result;
            }

            usleep(500_000);
        }

        throw new RuntimeException("Timed out after {$timeoutSeconds}s waiting for {$what}.");
    }

    /**
     * Captured deliveries (webhooks / inbound endpoint posts) whose capture
     * path matches the given slug, oldest first.
     *
     * @return list<array{headers: array<string, string>, body: string, uri: string}>
     */
    public static function captured(string $slug): array
    {
        $files = glob(rtrim(self::captureDir(), '/')."/*{$slug}*.json");

        if ($files === false) {
            return [];
        }

        sort($files);

        $records = [];

        foreach ($files as $file) {
            $decoded = json_decode((string) file_get_contents($file), true);

            if (! is_array($decoded)) {
                continue;
            }

            $headers = [];

            if (is_array($decoded['headers'] ?? null)) {
                foreach ($decoded['headers'] as $name => $value) {
                    if (is_string($name) && is_string($value)) {
                        $headers[strtoupper($name)] = $value;
                    }
                }
            }

            $body = base64_decode((string) ($decoded['body_b64'] ?? ''), true);

            $records[] = [
                'headers' => $headers,
                'body' => $body === false ? '' : $body,
                'uri' => (string) ($decoded['uri'] ?? ''),
            ];
        }

        return $records;
    }

    private static function env(string $name): ?string
    {
        $value = getenv($name);

        return $value === false || $value === '' ? null : $value;
    }

    private static function require(string $name): string
    {
        return self::env($name) ?? throw new RuntimeException("E2E env var {$name} is not set.");
    }
}
