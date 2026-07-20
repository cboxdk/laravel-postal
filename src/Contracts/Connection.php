<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Contracts;

use Cbox\LaravelPostal\Client\ConnectionStatus;
use Cbox\LaravelPostal\Dto\Delivery;
use Cbox\LaravelPostal\Dto\MessageDetails;
use Cbox\LaravelPostal\Dto\MessageExpansion;
use Cbox\LaravelPostal\Dto\SendMessage;
use Cbox\LaravelPostal\Dto\SendResult;

/**
 * A connection to a single Postal mail server.
 */
interface Connection
{
    /**
     * The configured server name this connection belongs to.
     */
    public function name(): string;

    /**
     * Send a structured message via /api/v1/send/message.
     */
    public function send(SendMessage $message): SendResult;

    /**
     * Send a raw RFC 2822 message via /api/v1/send/raw.
     *
     * @param  list<string>  $rcptTo
     * @param  string  $rawMessage  The full, un-encoded MIME message.
     */
    public function sendRaw(string $mailFrom, array $rcptTo, string $rawMessage, bool $bounce = false): SendResult;

    /**
     * Fetch a message via /api/v1/messages/message.
     *
     * @param  true|list<MessageExpansion>  $expansions  True for everything,
     *                                                   or the subset to load.
     */
    public function message(int $id, bool|array $expansions = true): MessageDetails;

    /**
     * Fetch the delivery attempts for a message via /api/v1/messages/deliveries.
     *
     * @return list<Delivery>
     */
    public function deliveries(int $id): array;

    /**
     * Prove the server URL is reachable and the API key is valid.
     */
    public function ping(): ConnectionStatus;
}
