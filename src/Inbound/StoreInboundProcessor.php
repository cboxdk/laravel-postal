<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Inbound;

use Cbox\LaravelPostal\Contracts\InboundProcessor;
use Cbox\LaravelPostal\Events\PostalInboundMessage;
use Cbox\LaravelPostal\Models\PostalMessage;
use Cbox\LaravelPostal\Support\InboundConfig;
use Cbox\LaravelPostal\Support\MessageStore;
use Illuminate\Contracts\Events\Dispatcher;

/**
 * The default inbound pipeline: deduplicate on the Postal message id (or a
 * content hash for id-less payloads), record an incoming postal_messages
 * row, then dispatch the typed event — all inside one transaction, so a
 * failure rolls the dedupe row back and Postal's redelivery retries
 * cleanly.
 */
class StoreInboundProcessor implements InboundProcessor
{
    public function __construct(
        private readonly Dispatcher $events,
        private readonly InboundConfig $config,
        private readonly MessageStore $store,
    ) {}

    public function process(string $server, InboundMessage $message): ?PostalInboundMessage
    {
        $dedupeKey = $message->dedupeKey($server);

        // The dedupe key doubles as the event's idempotency handle, so
        // listeners can dedupe uniformly even with the store disabled.
        $event = new PostalInboundMessage($server, $message, $dedupeKey, $message->timestamp);

        if (! $this->config->store) {
            $this->events->dispatch($event);

            return $event;
        }

        return $this->store->transaction(function () use ($server, $message, $dedupeKey, $event): ?PostalInboundMessage {
            $recorded = $this->store->recordEvent(
                $server,
                $message->id,
                $dedupeKey,
                'InboundMessage',
                $this->loggablePayload($message),
                $message->timestamp,
            );

            if (! $recorded) {
                return null;
            }

            if ($message->id > 0) {
                $this->recordMessage($server, $message);
            }

            $this->events->dispatch($event);

            return $event;
        });
    }

    private function recordMessage(string $server, InboundMessage $message): void
    {
        $this->store->upsertMessage(
            $server,
            $message->id,
            [
                'token' => $message->token,
                'message_id' => $message->messageId,
                'to' => $message->rcptTo,
                'from' => $message->mailFrom,
                'subject' => $message->subject,
                'spam_status' => $message->spamStatus,
            ],
            function (PostalMessage $row) use ($message): void {
                $row->direction = 'incoming';
                $row->status = 'Received';
                $row->last_event = 'InboundMessage';
                $row->last_event_at = $this->store->occurredAt($message->timestamp);
            },
        );
    }

    /**
     * The event log keeps metadata only — attachment data and the raw
     * message can be megabytes and belong to the consuming app.
     *
     * @return array<string, mixed>
     */
    private function loggablePayload(InboundMessage $message): array
    {
        $payload = $message->raw;
        unset($payload['attachments'], $payload['message']);

        return $payload;
    }
}
