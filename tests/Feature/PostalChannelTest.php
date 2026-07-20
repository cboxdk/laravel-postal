<?php

declare(strict_types=1);

use Cbox\LaravelPostal\Dto\SendMessage;
use Cbox\LaravelPostal\Models\PostalMessage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

class ChannelTestUser extends Model
{
    use Notifiable;

    protected $table = 'channel_test_users';

    protected $guarded = [];
}

class WelcomeNotification extends Notification
{
    public function via(object $notifiable): array
    {
        return ['postal'];
    }

    public function toPostal(object $notifiable): SendMessage
    {
        return SendMessage::create()
            ->from('no-reply@cboxid.com')
            ->subject('Welcome!')
            ->tag('onboarding')
            ->html('<p>Welcome</p>');
    }
}

class SecondServerNotification extends WelcomeNotification
{
    public function postalServer(object $notifiable): string
    {
        return 'second';
    }
}

beforeEach(function (): void {
    Schema::create('channel_test_users', function ($table): void {
        $table->id();
        $table->string('email');
        $table->timestamps();
    });
});

it('sends a notification through the default server and routes to the notifiable email', function (): void {
    $fake = $this->fakePostal();

    $user = ChannelTestUser::query()->create(['email' => 'alice@example.com']);

    $user->notify(new WelcomeNotification);

    $fake->assertSent(function (SendMessage $message, string $server): bool {
        $payload = $message->toArray();

        return $server === 'default'
            && $payload['to'] === ['alice@example.com']
            && $payload['subject'] === 'Welcome!';
    });
});

it('links the sent message to the notifiable model in the store', function (): void {
    $this->fakePostal();

    $user = ChannelTestUser::query()->create(['email' => 'alice@example.com']);

    $user->notify(new WelcomeNotification);

    $row = PostalMessage::query()->sole();

    expect($row->server)->toBe('default')
        ->and($row->to)->toBe('alice@example.com')
        ->and($row->subject)->toBe('Welcome!')
        ->and($row->tag)->toBe('onboarding')
        ->and($row->direction)->toBe('outgoing')
        ->and($row->notifiable_type)->toBe(ChannelTestUser::class)
        ->and((int) $row->notifiable_id)->toBe($user->id)
        ->and($row->notifiable?->is($user))->toBeTrue();
});

it('honours a notification-selected server', function (): void {
    $fake = $this->fakePostal();

    $user = ChannelTestUser::query()->create(['email' => 'alice@example.com']);

    $user->notify(new SecondServerNotification);

    $fake->assertSent(fn (SendMessage $message, string $server): bool => $server === 'second');
});

it('does not write store rows when the store is disabled', function (): void {
    config()->set('postal.webhooks.store', false);

    $this->fakePostal();

    $user = ChannelTestUser::query()->create(['email' => 'alice@example.com']);

    $user->notify(new WelcomeNotification);

    expect(PostalMessage::query()->count())->toBe(0);
});

it('rejects notifications without toPostal', function (): void {
    $this->fakePostal();

    $user = ChannelTestUser::query()->create(['email' => 'alice@example.com']);

    $user->notify(new class extends Notification
    {
        public function via(object $notifiable): array
        {
            return ['postal'];
        }
    });
})->throws(LogicException::class, 'toPostal');
