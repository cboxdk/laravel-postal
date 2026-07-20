<?php

declare(strict_types=1);

use Cbox\LaravelPostal\Tests\Fixtures\WebhookFixtures;
use Cbox\LaravelPostal\Webhooks\ProcessWebhook;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;

use function Pest\Laravel\call;

beforeEach(function (): void {
    config()->set('postal.webhooks.public_key', WebhookFixtures::publicKey());
});

function postWebhook(string $uri, string $rawBody, array $headers = []): TestResponse
{
    $server = ['CONTENT_TYPE' => 'application/json'];

    foreach ($headers as $name => $value) {
        $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
    }

    return call('POST', $uri, [], [], [], $server, $rawBody);
}

it('accepts a correctly signed webhook and queues processing', function (): void {
    Queue::fake();

    $body = WebhookFixtures::messageSentBody();

    postWebhook('/postal/webhook', $body, [
        'X-Postal-Signature-256' => WebhookFixtures::sign256($body),
    ])->assertOk();

    Queue::assertPushed(ProcessWebhook::class, function (ProcessWebhook $job): bool {
        return $job->server === 'default' && $job->body['event'] === 'MessageSent';
    });
});

it('attributes the webhook to the server named in the URL', function (): void {
    Queue::fake();

    $body = WebhookFixtures::messageSentBody();

    postWebhook('/postal/webhook/second', $body, [
        'X-Postal-Signature-256' => WebhookFixtures::sign256($body),
    ])->assertOk();

    Queue::assertPushed(ProcessWebhook::class, fn (ProcessWebhook $job): bool => $job->server === 'second');
});

it('rejects webhooks for unknown servers', function (): void {
    Queue::fake();

    $body = WebhookFixtures::messageSentBody();

    postWebhook('/postal/webhook/nope', $body, [
        'X-Postal-Signature-256' => WebhookFixtures::sign256($body),
    ])->assertNotFound();

    Queue::assertNothingPushed();
});

it('rejects an invalid signature with 401', function (): void {
    Queue::fake();

    $body = WebhookFixtures::messageSentBody();

    postWebhook('/postal/webhook', $body, [
        'X-Postal-Signature-256' => WebhookFixtures::signWithOtherKey($body),
    ])->assertUnauthorized();

    Queue::assertNothingPushed();
});

it('rejects a missing signature with 401 when verification is enabled', function (): void {
    Queue::fake();

    postWebhook('/postal/webhook', WebhookFixtures::messageSentBody())->assertUnauthorized();

    Queue::assertNothingPushed();
});

it('accepts the legacy SHA1 header when no SHA256 header is sent', function (): void {
    Queue::fake();

    $body = WebhookFixtures::messageSentBody();

    postWebhook('/postal/webhook', $body, [
        'X-Postal-Signature' => WebhookFixtures::signSha1($body),
    ])->assertOk();

    Queue::assertPushed(ProcessWebhook::class);
});

it('skips verification when disabled by config', function (): void {
    config()->set('postal.webhooks.verify_signature', false);

    Queue::fake();

    postWebhook('/postal/webhook', WebhookFixtures::messageSentBody())->assertOk();

    Queue::assertPushed(ProcessWebhook::class);
});

it('rejects a signed but non-JSON body with 400', function (): void {
    Queue::fake();

    $body = 'this is not json';

    postWebhook('/postal/webhook', $body, [
        'X-Postal-Signature-256' => WebhookFixtures::sign256($body),
    ])->assertBadRequest();

    Queue::assertNothingPushed();
});

it('dispatches to the configured queue and connection', function (): void {
    config()->set('postal.webhooks.queue', 'webhooks');

    Queue::fake();

    $body = WebhookFixtures::messageSentBody();

    postWebhook('/postal/webhook', $body, [
        'X-Postal-Signature-256' => WebhookFixtures::sign256($body),
    ])->assertOk();

    Queue::assertPushedOn('webhooks', ProcessWebhook::class);
});
