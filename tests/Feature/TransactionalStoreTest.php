<?php

declare(strict_types=1);

use Cbox\LaravelPostal\Contracts\InboundProcessor;
use Cbox\LaravelPostal\Contracts\WebhookProcessor;
use Cbox\LaravelPostal\Events\PostalInboundMessage;
use Cbox\LaravelPostal\Events\PostalMessageSent;
use Cbox\LaravelPostal\Inbound\InboundMessage;
use Cbox\LaravelPostal\Models\PostalMessage;
use Cbox\LaravelPostal\Models\PostalMessageEvent;
use Cbox\LaravelPostal\Webhooks\WebhookEnvelope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

function sentEnvelope(): WebhookEnvelope
{
    $envelope = WebhookEnvelope::fromArray([
        'event' => 'MessageSent',
        'timestamp' => 1752969600.0,
        'uuid' => 'tx-uuid-1',
        'payload' => [
            'message' => ['id' => 4300, 'to' => 'a@b.c', 'subject' => 'Tx'],
            'status' => 'Sent',
        ],
    ]);

    if ($envelope === null) {
        throw new RuntimeException('fixture envelope did not parse');
    }

    return $envelope;
}

it('rolls the webhook dedupe row back when processing fails, so the redelivery is processed', function (): void {
    $throwOnce = true;

    Event::listen(PostalMessageSent::class, function () use (&$throwOnce): void {
        if ($throwOnce) {
            $throwOnce = false;

            throw new RuntimeException('listener exploded mid-processing');
        }
    });

    $processor = app(WebhookProcessor::class);

    // First delivery: the sync listener throws inside the store transaction.
    expect(fn () => $processor->process('default', sentEnvelope()))
        ->toThrow(RuntimeException::class, 'listener exploded');

    // Nothing may have been committed — the dedupe row in particular, or
    // the redelivery below would be swallowed as a duplicate.
    expect(PostalMessageEvent::query()->count())->toBe(0)
        ->and(PostalMessage::query()->count())->toBe(0);

    // Postal redelivers (same uuid): now it must process fully.
    expect($processor->process('default', sentEnvelope()))->not->toBeNull();

    expect(PostalMessageEvent::query()->sole()->dedupe_key)->toBe('default:tx-uuid-1')
        ->and(PostalMessage::query()->sole()->status)->toBe('Sent');
});

it('rolls the inbound dedupe row back when processing fails, so the redelivery is processed', function (): void {
    $throwOnce = true;

    Event::listen(PostalInboundMessage::class, function () use (&$throwOnce): void {
        if ($throwOnce) {
            $throwOnce = false;

            throw new RuntimeException('inbound listener exploded');
        }
    });

    $message = InboundMessage::fromArray([
        'id' => 7500,
        'rcpt_to' => 'support@inbound.test',
        'mail_from' => 'a@b.c',
        'subject' => 'Tx inbound',
        'timestamp' => 1752969600.0,
    ]);

    $processor = app(InboundProcessor::class);

    expect(fn () => $processor->process('default', $message))
        ->toThrow(RuntimeException::class, 'inbound listener exploded');

    expect(PostalMessageEvent::query()->count())->toBe(0)
        ->and(PostalMessage::query()->count())->toBe(0);

    expect($processor->process('default', $message))->not->toBeNull();

    expect(PostalMessage::query()->sole()->direction)->toBe('incoming');
});
