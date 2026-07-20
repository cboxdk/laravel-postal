<?php

declare(strict_types=1);

use Cbox\LaravelPostal\Models\PostalMessageEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('prints stored webhook events with --once', function (): void {
    PostalMessageEvent::query()->create([
        'server' => 'default',
        'postal_message_id' => 4200,
        'dedupe_key' => 'default:uuid-tail',
        'event' => 'MessageSent',
        'payload' => [
            'message' => ['id' => 4200, 'to' => 'alice@example.com', 'subject' => 'Welcome'],
            'status' => 'Sent',
        ],
        'occurred_at' => now(),
        'created_at' => now(),
    ]);

    $this->artisan('postal:tail', ['--once' => true])
        ->expectsOutputToContain('MessageSent')
        ->assertExitCode(0);
});

it('filters by server', function (): void {
    PostalMessageEvent::query()->create([
        'server' => 'second',
        'postal_message_id' => 1,
        'dedupe_key' => 'second:uuid-x',
        'event' => 'MessageDelayed',
        'payload' => ['message' => ['id' => 1]],
        'created_at' => now(),
    ]);

    $this->artisan('postal:tail', ['server' => 'default', '--once' => true])
        ->doesntExpectOutputToContain('MessageDelayed')
        ->assertExitCode(0);
});

it('fails cleanly when the store is disabled', function (): void {
    config()->set('postal.webhooks.store', false);

    $this->artisan('postal:tail', ['--once' => true])->assertExitCode(1);
});
