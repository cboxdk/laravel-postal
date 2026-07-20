<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Events;

use Cbox\LaravelPostal\Inbound\InboundMessage;
use Cbox\LaravelPostal\Webhooks\WebhookEventType;

/**
 * An inbound message was received via a Postal route's HTTP endpoint.
 */
class PostalInboundMessage extends PostalEvent
{
    public function __construct(
        string $server,
        public readonly InboundMessage $payload,
        ?string $uuid = null,
        ?float $timestamp = null,
    ) {
        parent::__construct($server, $uuid, $timestamp);
    }

    public function type(): WebhookEventType
    {
        return WebhookEventType::InboundMessage;
    }

    /**
     * Identifiers only — inbound bodies, attachments and raw MIME never
     * enter the broadcast payload.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'server' => $this->server,
            'event' => $this->type()->value,
            'uuid' => $this->uuid,
            'timestamp' => $this->timestamp,
            'message' => [
                'id' => $this->payload->id,
                'token' => $this->payload->token,
                'message_id' => $this->payload->messageId,
                'rcpt_to' => $this->payload->rcptTo,
                'mail_from' => $this->payload->mailFrom,
                'subject' => $this->payload->subject,
            ],
        ];
    }
}
