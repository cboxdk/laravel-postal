<?php

declare(strict_types=1);

use Cbox\LaravelPostal\Dto\MessageExpansion;
use Cbox\LaravelPostal\Dto\MessageLoad;
use Cbox\LaravelPostal\Facades\Postal;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('requests a subset of expansions as their API names', function (): void {
    Http::fake([
        'postal.test/api/v1/messages/message' => Http::response([
            'status' => 'success', 'time' => 0.1, 'flags' => [],
            'data' => ['id' => 55, 'token' => 'tok', 'status' => ['status' => 'Sent', 'held' => false]],
        ]),
    ]);

    $message = Postal::message(55, [MessageExpansion::Status, MessageExpansion::PlainBody]);

    expect($message->status?->status)->toBe('Sent')
        ->and($message->details)->toBeNull()
        ->and($message->attachments)->toBeNull();

    Http::assertSent(fn (Request $request): bool => $request['_expansions'] === ['status', 'plain_body']);
});

it('hydrates attachments, activity, headers and the raw message', function (): void {
    Http::fake([
        'postal.test/api/v1/messages/message' => Http::response([
            'status' => 'success', 'time' => 0.1, 'flags' => [],
            'data' => [
                'id' => 55,
                'token' => 'tok',
                'headers' => [
                    'from' => ['Cbox <no-reply@cboxid.com>'],
                    'subject' => 'Hi',
                ],
                'attachments' => [[
                    'filename' => 'report.pdf',
                    'content_type' => 'application/pdf',
                    'size' => 8,
                    'hash' => sha1('pdfbytes'),
                    'data' => base64_encode('pdfbytes'),
                ]],
                'activity_entries' => [
                    'loads' => [[
                        'ip_address' => '203.0.113.9',
                        'user_agent' => 'Mozilla/5.0',
                        'timestamp' => '2026-07-20T10:00:00.000+02:00',
                    ]],
                    'clicks' => [[
                        'url' => 'https://cboxid.com/welcome',
                        'ip_address' => '203.0.113.9',
                        'user_agent' => 'Mozilla/5.0',
                        'timestamp' => '2026-07-20T10:05:00.000+02:00',
                    ]],
                ],
                'raw_message' => base64_encode("From: x@y.z\r\n\r\nBody"),
            ],
        ]),
    ]);

    $message = Postal::message(55);

    expect($message->headers)->toBe(['from' => ['Cbox <no-reply@cboxid.com>'], 'subject' => ['Hi']])
        ->and($message->attachments)->toHaveCount(1)
        ->and($message->attachments[0]->filename)->toBe('report.pdf')
        ->and($message->attachments[0]->content)->toBe('pdfbytes')
        ->and($message->activity?->loads[0]->ipAddress)->toBe('203.0.113.9')
        ->and($message->activity->loads[0]->occurredAt()?->format('H:i'))->toBe('10:00')
        ->and($message->activity->clicks[0]->url)->toBe('https://cboxid.com/welcome')
        ->and($message->rawMessage)->toBe("From: x@y.z\r\n\r\nBody");

    Http::assertSent(fn (Request $request): bool => $request['_expansions'] === true);
});

it('parses numeric timestamps in activity entries too', function (): void {
    $load = MessageLoad::fromArray([
        'ip_address' => '1.2.3.4',
        'user_agent' => 'x',
        'timestamp' => 1752969600.5,
    ]);

    expect($load->occurredAt()?->getTimestamp())->toBe(1752969600);
});
