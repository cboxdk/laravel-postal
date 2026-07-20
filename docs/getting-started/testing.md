---
title: Testing
weight: 12
description: Fake the Postal manager in your test suite with Postal::fake() and assert on sends.
---

# Testing

The package ships the same fakes its own suite runs on.

## Faking

```php
use Cbox\LaravelPostal\Facades\Postal;

$fake = Postal::fake();
```

`Postal::fake()` swaps the manager in the container, so everything that
resolves the Postal contracts — the facade, the mail transport is *not*
included (it talks HTTP; use `Http::fake()` or `Mail::fake()` there), the
notification channel, your own services — records against the fake.

Or compose the trait in your `TestCase`:

```php
use Cbox\LaravelPostal\Testing\InteractsWithPostal;

class TestCase extends BaseTestCase
{
    use InteractsWithPostal;
}

// in a test:
$fake = $this->fakePostal();
```

## Asserting

```php
use Cbox\LaravelPostal\Dto\SendMessage;

$fake->assertSent();                 // at least one send, any server
$fake->assertSentCount(2);
$fake->assertNothingSent();

$fake->assertSent(function (SendMessage $message, string $server): bool {
    return $server === 'cbox-billing'
        && $message->toArray()['subject'] === 'Invoice ready';
});
```

`$fake->sent()` and `$fake->sentRaw()` return everything recorded when you
need to inspect payloads directly.

## Canned lookups

Seed message lookups per server:

```php
use Cbox\LaravelPostal\Dto\MessageDetails;

$fake->connection('cbox-billing')->withMessage(
    MessageDetails::fromArray(['id' => 9, 'token' => 't', 'plain_body' => 'Hi']),
);

Postal::server('cbox-billing')->message(9); // → the canned DTO
Postal::server('cbox-billing')->message(8); // → MessageNotFoundException
```

Sends through a fake connection return deterministic `SendResult`s with
incrementing per-recipient ids, so code that stores Postal message ids can
be asserted end to end.
