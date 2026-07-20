<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Console;

use Cbox\LaravelPostal\Contracts\Factory;
use Illuminate\Console\Command;

class PingCommand extends Command
{
    protected $signature = 'postal:ping {server? : Server name (all configured servers when omitted)}';

    protected $description = 'Verify that each configured Postal server is reachable and its API key is valid';

    public function handle(Factory $postal): int
    {
        $server = $this->argument('server');

        $names = is_string($server) ? [$server] : $postal->names();

        $rows = [];
        $healthy = true;

        foreach ($names as $name) {
            $status = $postal->server($name)->ping();
            $healthy = $healthy && $status->ok;

            $rows[] = [
                $status->server,
                $status->url,
                $status->ok ? '<info>✓ ok</info>' : '<error>✗ failed</error>',
                number_format($status->roundTripMs, 1).' ms',
                $status->error ?? '',
            ];
        }

        $this->table(['Server', 'URL', 'Status', 'RTT', 'Error'], $rows);

        return $healthy ? self::SUCCESS : self::FAILURE;
    }
}
