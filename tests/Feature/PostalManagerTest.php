<?php

declare(strict_types=1);

use Cbox\LaravelPostal\Contracts\Connection;
use Cbox\LaravelPostal\Contracts\Factory;
use Cbox\LaravelPostal\Dto\MessageDetails;
use Cbox\LaravelPostal\Dto\SendMessage;
use Cbox\LaravelPostal\Exceptions\MessageNotFoundException;
use Cbox\LaravelPostal\Facades\Postal;
use Cbox\LaravelPostal\PostalManager;

it('resolves the manager from the container under all aliases', function (): void {
    expect($this->app->make('postal'))->toBeInstanceOf(PostalManager::class)
        ->and($this->app->make(Factory::class))->toBe($this->app->make('postal'))
        ->and($this->app->make(PostalManager::class))->toBe($this->app->make('postal'));
});

it('caches one connection per server', function (): void {
    $manager = $this->app->make(Factory::class);

    expect($manager->server())->toBe($manager->server('default'))
        ->and($manager->server('second'))->not->toBe($manager->server('default'))
        ->and($manager->server('second'))->toBeInstanceOf(Connection::class);
});

it('lists configured server names', function (): void {
    expect($this->app->make(Factory::class)->names())->toBe(['default', 'second']);
});

it('rejects unknown server names', function (): void {
    $this->app->make(Factory::class)->server('nope');
})->throws(InvalidArgumentException::class, 'not configured');

it('fakes the manager and records sends', function (): void {
    $fake = $this->fakePostal();

    expect($this->app->make(Factory::class))->toBe($fake);

    $result = Postal::send(SendMessage::create()->to('a@b.c')->from('x@y.z')->subject('Yo'));

    expect($result->first()?->id)->toBe(1);

    $fake->assertSent(fn (SendMessage $message, string $server): bool => $message->toArray()['subject'] === 'Yo' && $server === 'default');
    $fake->assertSentCount(1);
});

it('asserts nothing sent on a pristine fake', function (): void {
    $this->fakePostal()->assertNothingSent();
});

it('serves canned message lookups through the fake', function (): void {
    $fake = $this->fakePostal();

    $fake->connection()->withMessage(MessageDetails::fromArray([
        'id' => 9, 'token' => 't9', 'plain_body' => 'canned',
    ]));

    expect(Postal::message(9)->plainBody)->toBe('canned');
    expect(fn () => Postal::message(10))->toThrow(MessageNotFoundException::class);
});
