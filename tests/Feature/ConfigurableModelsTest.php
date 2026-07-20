<?php

declare(strict_types=1);

use Cbox\LaravelPostal\Contracts\WebhookProcessor;
use Cbox\LaravelPostal\Models\PostalMessage;
use Cbox\LaravelPostal\Models\PostalMessageEvent;
use Cbox\LaravelPostal\Support\Models;
use Cbox\LaravelPostal\Webhooks\WebhookEnvelope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

class CustomPostalMessage extends PostalMessage
{
    protected $table = 'postal_messages';

    public function shoutSubject(): string
    {
        return strtoupper($this->subject ?? '');
    }
}

it('resolves the base models by default', function (): void {
    expect(Models::message())->toBe(PostalMessage::class)
        ->and(Models::event())->toBe(PostalMessageEvent::class);
});

it('lets a host swap in a model subclass', function (): void {
    config()->set('postal.models.message', CustomPostalMessage::class);

    Event::fake();

    $envelope = WebhookEnvelope::fromArray([
        'event' => 'MessageSent',
        'timestamp' => 1752969600.0,
        'uuid' => 'custom-model-1',
        'payload' => [
            'message' => ['id' => 1, 'to' => 'a@b.c', 'subject' => 'hello'],
            'status' => 'Sent',
        ],
    ]);

    app(WebhookProcessor::class)->process('default', $envelope);

    $row = CustomPostalMessage::query()->sole();

    expect($row->shoutSubject())->toBe('HELLO');
});

it('rejects model classes that are not subclasses of the package model', function (): void {
    config()->set('postal.models.message', stdClass::class);

    Models::message();
})->throws(InvalidArgumentException::class, 'subclass');
