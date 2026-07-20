<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Webhooks;

use Cbox\LaravelPostal\Contracts\WebhookProcessor;
use Cbox\LaravelPostal\Events\PostalEvent;
use Cbox\LaravelPostal\Events\PostalMessageBounced;
use Cbox\LaravelPostal\Events\PostalMessageLinkClicked;
use Cbox\LaravelPostal\Events\PostalMessageLoaded;
use Cbox\LaravelPostal\Events\PostalWebhookEvent;
use Cbox\LaravelPostal\Models\PostalMessage;
use Cbox\LaravelPostal\Support\MessageStore;
use Cbox\LaravelPostal\Support\WebhookConfig;
use Illuminate\Contracts\Events\Dispatcher;

/**
 * The default webhook pipeline: deduplicate on Postal's webhook request
 * uuid, keep the postal_messages status row current, then dispatch the
 * typed event — all inside one database transaction, so a failure at any
 * point rolls the dedupe row back and the redelivery is retried cleanly.
 */
class StoreWebhookProcessor implements WebhookProcessor
{
    public function __construct(
        private readonly EventFactory $factory,
        private readonly Dispatcher $events,
        private readonly WebhookConfig $config,
        private readonly MessageStore $store,
    ) {}

    public function process(string $server, WebhookEnvelope $envelope): ?PostalWebhookEvent
    {
        $event = $this->factory->make($server, $envelope);

        if ($event === null) {
            return null;
        }

        if (! $this->config->store) {
            $this->events->dispatch($event);

            return $event;
        }

        return $this->store->transaction(function () use ($server, $envelope, $event): ?PostalWebhookEvent {
            $recorded = $this->store->recordEvent(
                $server,
                $event->relatedMessage()?->id,
                $envelope->dedupeKey($server),
                $envelope->event,
                $envelope->payload,
                $envelope->timestamp,
            );

            if (! $recorded) {
                return null;
            }

            $this->applyToMessage($server, $envelope, $event);
            $this->events->dispatch($event);

            return $event;
        });
    }

    private function applyToMessage(string $server, WebhookEnvelope $envelope, PostalEvent $event): void
    {
        $webhookMessage = $event->relatedMessage();

        if ($webhookMessage === null || $webhookMessage->id === 0) {
            return;
        }

        $this->store->upsertMessage(
            $server,
            $webhookMessage->id,
            [
                'token' => $webhookMessage->token,
                'message_id' => $webhookMessage->messageId,
                'direction' => $webhookMessage->direction,
                'to' => $webhookMessage->to,
                'from' => $webhookMessage->from,
                'subject' => $webhookMessage->subject,
                'tag' => $webhookMessage->tag,
                'spam_status' => $webhookMessage->spamStatus,
            ],
            function (PostalMessage $message) use ($envelope, $event): void {
                if ($event instanceof PostalMessageLoaded) {
                    $message->opens++;
                }

                if ($event instanceof PostalMessageLinkClicked) {
                    $message->clicks++;
                }

                if ($event->type()->isDeliveryStatus()) {
                    $status = $envelope->payload['status'] ?? null;
                    $details = $envelope->payload['details'] ?? null;

                    if (is_string($status) && $status !== '') {
                        $message->status = $status;
                    }

                    if (is_string($details) && $details !== '') {
                        $message->status_details = $details;
                    }
                }

                if ($event instanceof PostalMessageBounced) {
                    $message->status = 'Bounced';
                }

                $message->last_event = $envelope->event;
                $message->last_event_at = $this->store->occurredAt($envelope->timestamp);
            },
        );
    }
}
