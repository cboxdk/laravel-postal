<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Console;

use Cbox\LaravelPostal\Models\PostalMessageEvent;
use Cbox\LaravelPostal\Support\Coerce;
use Cbox\LaravelPostal\Support\Models;
use Cbox\LaravelPostal\Support\WebhookConfig;
use Illuminate\Console\Command;

/**
 * Live-tails the webhook event log — proves the webhook spine end to end
 * with nothing but a queue worker and a database, no broadcaster needed.
 */
class TailCommand extends Command
{
    protected $signature = 'postal:tail
        {server? : Only show events for this server}
        {--interval=2 : Seconds between polls}
        {--once : Print one batch and exit (no follow)}';

    protected $description = 'Tail incoming Postal webhook events from the event store';

    private bool $shouldQuit = false;

    public function handle(WebhookConfig $config): int
    {
        if (! $config->store) {
            $this->error('postal:tail reads the event store, but postal.webhooks.store is disabled.');

            return self::FAILURE;
        }

        $server = $this->argument('server');
        $server = is_string($server) ? $server : null;
        $interval = max(1, Coerce::int($this->option('interval'), 2));

        if ($this->option('once')) {
            // One shot: the most recent batch, oldest-first for reading.
            $latest = Models::event()::query()
                ->when($server !== null, fn ($query) => $query->where('server', $server))
                ->orderByDesc('id')
                ->limit(500)
                ->get()
                ->reverse();

            foreach ($latest as $event) {
                $this->line($this->format($event));
            }

            return self::SUCCESS;
        }

        // Follow: start at the tail and show only events from now on.
        $cursor = Coerce::int(Models::event()::query()->max('id'));

        $this->registerSignalHandlers();

        $this->info('Tailing Postal webhook events'.($server !== null ? " for [{$server}]" : '').' — Ctrl+C to stop.');

        while (! $this->shouldQuit) {
            $events = Models::event()::query()
                ->when($server !== null, fn ($query) => $query->where('server', $server))
                ->where('id', '>', $cursor)
                ->orderBy('id')
                ->limit(500)
                ->get();

            foreach ($events as $event) {
                $cursor = max($cursor, $event->id);
                $this->line($this->format($event));
            }

            sleep($interval);
        }

        return self::SUCCESS;
    }

    /**
     * Exit the follow loop cleanly on Ctrl+C / SIGTERM when pcntl is
     * available; otherwise the process terminates the classic way.
     */
    private function registerSignalHandlers(): void
    {
        if (! function_exists('pcntl_async_signals')) {
            return;
        }

        pcntl_async_signals(true);

        $quit = function (): void {
            $this->shouldQuit = true;
        };

        pcntl_signal(SIGINT, $quit);
        pcntl_signal(SIGTERM, $quit);
    }

    private function format(PostalMessageEvent $event): string
    {
        $time = $event->occurred_at?->toTimeString() ?? $event->created_at?->toTimeString() ?? '--:--:--';

        $message = Coerce::map($event->payload['message'] ?? null);
        $to = Coerce::stringOrNull($message['to'] ?? null);
        $subject = Coerce::stringOrNull($message['subject'] ?? null);
        $status = Coerce::stringOrNull($event->payload['status'] ?? null);

        $parts = array_filter([
            "<comment>{$time}</comment>",
            "<info>{$event->server}</info>",
            $event->event,
            $event->postal_message_id !== null ? "#{$event->postal_message_id}" : null,
            $to,
            $subject !== null ? "\"{$subject}\"" : null,
            $status,
        ], static fn (?string $part): bool => $part !== null && $part !== '');

        return implode('  ', $parts);
    }
}
