<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Console;

use Cbox\LaravelPostal\Contracts\Factory;
use Cbox\LaravelPostal\Exceptions\MessageNotFoundException;
use Illuminate\Console\Command;

class MessageCommand extends Command
{
    protected $signature = 'postal:message {server : Server name} {id : Postal message id}';

    protected $description = 'Fetch and print a message with its delivery attempts';

    public function handle(Factory $postal): int
    {
        $server = $this->argument('server');
        $id = $this->argument('id');

        if (! is_string($server) || ! is_string($id) || ! ctype_digit($id)) {
            $this->error('Usage: postal:message {server} {id}');

            return self::INVALID;
        }

        $connection = $postal->server($server);

        try {
            $message = $connection->message((int) $id);
            $deliveries = $connection->deliveries((int) $id);
        } catch (MessageNotFoundException) {
            $this->error("No message [{$id}] on server [{$server}].");

            return self::FAILURE;
        }

        $this->components->twoColumnDetail('<info>Id</info>', (string) $message->id);
        $this->components->twoColumnDetail('Token', $message->token);

        if ($message->status !== null) {
            $this->components->twoColumnDetail('Status', $message->status->status.($message->status->held ? ' (held)' : ''));
        }

        if ($message->details !== null) {
            $this->components->twoColumnDetail('From', $message->details->mailFrom ?? '-');
            $this->components->twoColumnDetail('To', $message->details->rcptTo ?? '-');
            $this->components->twoColumnDetail('Subject', $message->details->subject ?? '-');
            $this->components->twoColumnDetail('Message-Id', $message->details->messageId ?? '-');
            $this->components->twoColumnDetail('Tag', $message->details->tag ?? '-');
            $this->components->twoColumnDetail('Direction', $message->details->direction ?? '-');
            $this->components->twoColumnDetail('Size', $message->details->size !== null ? "{$message->details->size} bytes" : '-');
        }

        if ($message->inspection !== null) {
            $this->components->twoColumnDetail(
                'Spam',
                sprintf('%s (score %.2f)', $message->inspection->spam ? 'yes' : 'no', $message->inspection->spamScore),
            );
        }

        if ($deliveries === []) {
            $this->components->info('No delivery attempts recorded.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->table(
            ['#', 'Status', 'Details', 'Output', 'SSL', 'Time'],
            array_map(static fn ($delivery): array => [
                $delivery->id,
                $delivery->status,
                $delivery->details ?? '',
                $delivery->output ?? '',
                $delivery->sentWithSsl ? 'yes' : 'no',
                $delivery->time !== null ? number_format($delivery->time, 2).'s' : '',
            ], $deliveries),
        );

        return self::SUCCESS;
    }
}
