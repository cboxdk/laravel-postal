<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Webhooks;

use Cbox\LaravelPostal\Support\Coerce;

/**
 * The parsed body of a Postal webhook POST: `{event, timestamp, payload, uuid}`.
 */
readonly class WebhookEnvelope
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $event,
        public ?float $timestamp,
        public array $payload,
        public ?string $uuid,
    ) {}

    /**
     * @param  array<string, mixed>  $body
     */
    public static function fromArray(array $body): ?self
    {
        $event = Coerce::stringOrNull($body['event'] ?? null);

        if ($event === null || $event === '') {
            return null;
        }

        return new self(
            event: $event,
            timestamp: Coerce::floatOrNull($body['timestamp'] ?? null),
            payload: Coerce::map($body['payload'] ?? null),
            uuid: Coerce::stringOrNull($body['uuid'] ?? null),
        );
    }

    public function type(): ?WebhookEventType
    {
        return WebhookEventType::tryFrom($this->event);
    }

    /**
     * The idempotency key for this delivery: Postal's webhook request uuid,
     * or a content hash when the uuid is absent.
     */
    public function dedupeKey(string $server): string
    {
        if ($this->uuid !== null && $this->uuid !== '') {
            return "{$server}:{$this->uuid}";
        }

        $payload = json_encode($this->payload);

        return $server.':'.hash('sha256', $this->event.'|'.($this->timestamp ?? 0).'|'.($payload === false ? '' : $payload));
    }
}
