<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Client;

use Cbox\LaravelPostal\Contracts\Connection;
use Cbox\LaravelPostal\Dto\Delivery;
use Cbox\LaravelPostal\Dto\MessageDetails;
use Cbox\LaravelPostal\Dto\MessageExpansion;
use Cbox\LaravelPostal\Dto\SendMessage;
use Cbox\LaravelPostal\Dto\SendResult;
use Cbox\LaravelPostal\Exceptions\MessageNotFoundException;
use Cbox\LaravelPostal\Exceptions\PostalException;
use Cbox\LaravelPostal\Support\Coerce;
use Cbox\LaravelPostal\Support\HttpConfig;
use Cbox\LaravelPostal\Support\ServerConfig;
use Illuminate\Http\Client\Factory as HttpFactory;
use LogicException;

/**
 * The typed API client for one Postal mail server.
 */
class PostalClient implements Connection
{
    private readonly PendingRequest $request;

    public function __construct(
        HttpFactory $http,
        private readonly ServerConfig $server,
        HttpConfig $config = new HttpConfig,
    ) {
        $this->request = new PendingRequest($http, $server, $config);
    }

    public function name(): string
    {
        return $this->server->name;
    }

    /**
     * On an `api` connection this is a structured JSON send; on an
     * `smtp-api` connection the message is rendered to MIME and submitted
     * through /send/raw instead — SMTP semantics over the HTTP API.
     */
    public function send(SendMessage $message): SendResult
    {
        if ($this->server->type === ConnectionType::SmtpApi) {
            $from = $message->envelopeFrom();

            if ($from === null) {
                throw new LogicException('A from address is required to send a message.');
            }

            return $this->sendRaw(
                $from,
                $message->envelopeRecipients(),
                $message->toEmail()->toString(),
                $message->isBounce(),
            );
        }

        $envelope = $this->request->post('/api/v1/send/message', $message->toArray());

        return SendResult::fromArray($envelope->data);
    }

    public function sendRaw(string $mailFrom, array $rcptTo, string $rawMessage, bool $bounce = false): SendResult
    {
        $payload = [
            'mail_from' => $mailFrom,
            'rcpt_to' => $rcptTo,
            'data' => base64_encode($rawMessage),
        ];

        if ($bounce) {
            $payload['bounce'] = true;
        }

        $envelope = $this->request->post('/api/v1/send/raw', $payload);

        return SendResult::fromArray($envelope->data);
    }

    public function message(int $id, bool|array $expansions = true): MessageDetails
    {
        $envelope = $this->request->post('/api/v1/messages/message', [
            'id' => $id,
            '_expansions' => is_array($expansions)
                ? array_map(static fn (MessageExpansion $expansion): string => $expansion->value, $expansions)
                : $expansions,
        ]);

        return MessageDetails::fromArray($envelope->data);
    }

    public function deliveries(int $id): array
    {
        $envelope = $this->request->post('/api/v1/messages/deliveries', ['id' => $id]);

        $deliveries = [];

        foreach ($envelope->data as $delivery) {
            if (is_array($delivery)) {
                $deliveries[] = Delivery::fromArray(Coerce::map($delivery));
            }
        }

        return $deliveries;
    }

    /**
     * Postal has no ping endpoint, so we look up a message id that cannot
     * exist: a valid key yields MessageNotFound (healthy), a bad key yields
     * InvalidServerAPIKey, and transport problems surface as exceptions.
     */
    public function ping(): ConnectionStatus
    {
        $start = hrtime(true);

        try {
            $this->request->post('/api/v1/messages/message', ['id' => 0]);

            return $this->status(true, $start);
        } catch (MessageNotFoundException) {
            return $this->status(true, $start);
        } catch (PostalException $exception) {
            return $this->status(false, $start, $exception->getMessage());
        }
    }

    private function status(bool $ok, int $start, ?string $error = null): ConnectionStatus
    {
        return new ConnectionStatus(
            server: $this->server->name,
            url: $this->server->url ?? '',
            ok: $ok,
            roundTripMs: round((hrtime(true) - $start) / 1_000_000, 1),
            error: $error,
        );
    }
}
