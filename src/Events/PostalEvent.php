<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Events;

use Cbox\LaravelPostal\Support\Coerce;
use Cbox\LaravelPostal\Webhooks\Payloads\WebhookMessage;
use Cbox\LaravelPostal\Webhooks\WebhookEventType;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Base class for the typed webhook events. Broadcasting is opt-in sugar: the
 * ShouldBroadcast contract is gated behind `postal.broadcast.enabled` via
 * broadcastWhen(), so a disabled install does no broadcasting work and the
 * package needs no broadcaster dependency.
 */
abstract class PostalEvent implements PostalWebhookEvent, ShouldBroadcast
{
    use Dispatchable;

    public function __construct(
        public readonly string $server,
        public readonly ?string $uuid = null,
        public readonly ?float $timestamp = null,
    ) {}

    abstract public function type(): WebhookEventType;

    /**
     * The Postal message this event is about, when it is message-bearing.
     * For a bounce this is the original outbound message. Each event class
     * owns this mapping — the store and broadcast layers only call it.
     */
    public function relatedMessage(): ?WebhookMessage
    {
        return null;
    }

    public function server(): string
    {
        return $this->server;
    }

    public function uuid(): ?string
    {
        return $this->uuid;
    }

    public function occurredAt(): ?float
    {
        return $this->timestamp;
    }

    public function broadcastWhen(): bool
    {
        return Coerce::bool(config('postal.broadcast.enabled'));
    }

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        $prefix = Coerce::string(config('postal.broadcast.channel'), 'postal');

        return [new PrivateChannel("{$prefix}.server.{$this->server}")];
    }

    public function broadcastAs(): string
    {
        return $this->type()->value;
    }

    /**
     * A deliberately small broadcast payload: identifiers only, never
     * message bodies, attachments or raw MIME — those stay server-side.
     * Subscribe listeners that need more and fetch it over your own API.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $message = $this->relatedMessage();

        return [
            'server' => $this->server,
            'event' => $this->type()->value,
            'uuid' => $this->uuid,
            'timestamp' => $this->timestamp,
            'message' => $message === null ? null : [
                'id' => $message->id,
                'token' => $message->token,
                'message_id' => $message->messageId,
                'to' => $message->to,
                'subject' => $message->subject,
                'tag' => $message->tag,
            ],
        ];
    }
}
