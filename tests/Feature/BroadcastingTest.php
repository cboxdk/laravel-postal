<?php

declare(strict_types=1);

use Cbox\LaravelPostal\Events\PostalMessageSent;
use Cbox\LaravelPostal\Webhooks\Payloads\MessageStatusPayload;
use Illuminate\Broadcasting\PrivateChannel;

function statusEvent(): PostalMessageSent
{
    return new PostalMessageSent(
        'default',
        MessageStatusPayload::fromArray([
            'message' => ['id' => 1, 'to' => 'a@b.c'],
            'status' => 'Sent',
        ]),
        'uuid-b',
        1752969600.0,
    );
}

it('does not broadcast by default', function (): void {
    expect(statusEvent()->broadcastWhen())->toBeFalse();
});

it('broadcasts on the private server channel when enabled', function (): void {
    config()->set('postal.broadcast.enabled', true);

    $event = statusEvent();

    expect($event->broadcastWhen())->toBeTrue()
        ->and($event->broadcastAs())->toBe('MessageSent');

    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(1)
        ->and($channels[0])->toBeInstanceOf(PrivateChannel::class)
        ->and((string) $channels[0]->name)->toBe('private-postal.server.default');
});

it('honours a custom channel prefix', function (): void {
    config()->set('postal.broadcast.enabled', true);
    config()->set('postal.broadcast.channel', 'mail-events');

    expect((string) statusEvent()->broadcastOn()[0]->name)->toBe('private-mail-events.server.default');
});
