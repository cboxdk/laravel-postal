<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Notifications;

use Cbox\LaravelPostal\Contracts\Factory;
use Cbox\LaravelPostal\Dto\SendMessage;
use Cbox\LaravelPostal\Dto\SendResult;
use Cbox\LaravelPostal\Support\Coerce;
use Cbox\LaravelPostal\Support\Models;
use Cbox\LaravelPostal\Support\WebhookConfig;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification;
use LogicException;

/**
 * Sends a notification straight through the Postal API. The notification
 * implements toPostal($notifiable): SendMessage; an optional
 * postalServer($notifiable): ?string picks a non-default server.
 *
 * When the store is enabled and the notifiable is an Eloquent model, one
 * postal_messages row per recipient is created up front and linked to the
 * model, so later webhook events land on a row that already knows its owner.
 */
class PostalChannel
{
    public function __construct(
        private readonly Factory $postal,
        private readonly WebhookConfig $webhooks,
    ) {}

    public function send(object $notifiable, Notification $notification): ?SendResult
    {
        if (! method_exists($notification, 'toPostal')) {
            throw new LogicException(
                $notification::class.' must implement toPostal($notifiable) to use the Postal channel.',
            );
        }

        $message = $notification->toPostal($notifiable);

        if (! $message instanceof SendMessage) {
            throw new LogicException(
                $notification::class.'::toPostal() must return a '.SendMessage::class.'.',
            );
        }

        $this->applyRoute($message, $notifiable, $notification);

        $server = null;

        if (method_exists($notification, 'postalServer')) {
            $server = Coerce::stringOrNull($notification->postalServer($notifiable));
        }

        $connection = $this->postal->server($server);
        $result = $connection->send($message);

        if ($this->webhooks->store && $notifiable instanceof Model) {
            $this->link($connection->name(), $message, $result, $notifiable);
        }

        return $result;
    }

    /**
     * Fill the recipient from the notifiable's routing when the message has
     * none of its own.
     */
    private function applyRoute(SendMessage $message, object $notifiable, Notification $notification): void
    {
        if ($message->hasRecipients()) {
            return;
        }

        if (! method_exists($notifiable, 'routeNotificationFor')) {
            return;
        }

        $route = $notifiable->routeNotificationFor('postal', $notification)
            ?? $notifiable->routeNotificationFor('mail', $notification);

        if (is_string($route) && $route !== '') {
            $message->to($route);

            return;
        }

        if (is_array($route)) {
            foreach ($route as $key => $value) {
                // Support mail-style ['address' => 'name'] maps as well as
                // plain address lists.
                if (is_string($key)) {
                    $message->to($key);
                } elseif (is_string($value)) {
                    $message->to($value);
                }
            }
        }
    }

    private function link(string $server, SendMessage $message, SendResult $result, Model $notifiable): void
    {
        foreach ($result->recipients as $address => $recipient) {
            Models::message()::query()->updateOrCreate(
                [
                    'server' => $server,
                    'postal_message_id' => $recipient->id,
                ],
                [
                    'token' => $recipient->token,
                    'message_id' => $result->messageId,
                    'direction' => 'outgoing',
                    'to' => $address,
                    'from' => $message->fromAddress(),
                    'subject' => $message->subjectLine(),
                    'tag' => $message->tagName(),
                    'notifiable_type' => $notifiable->getMorphClass(),
                    'notifiable_id' => $notifiable->getKey(),
                ],
            );
        }
    }
}
