<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Testing;

use Cbox\LaravelPostal\Client\ConnectionStatus;
use Cbox\LaravelPostal\Contracts\Connection;
use Cbox\LaravelPostal\Dto\Delivery;
use Cbox\LaravelPostal\Dto\MessageDetails;
use Cbox\LaravelPostal\Dto\SendMessage;
use Cbox\LaravelPostal\Dto\SendRecipient;
use Cbox\LaravelPostal\Dto\SendResult;
use Cbox\LaravelPostal\Exceptions\MessageNotFoundException;

/**
 * An in-memory Postal connection: records sends, answers with deterministic
 * results, and serves canned message lookups.
 */
class FakeConnection implements Connection
{
    /** @var list<SendMessage> */
    public array $sent = [];

    /** @var list<RecordedRawMessage> */
    public array $sentRaw = [];

    /** @var array<int, MessageDetails> */
    private array $messages = [];

    /** @var array<int, list<Delivery>> */
    private array $deliveries = [];

    private int $nextId = 1;

    public function __construct(private readonly string $name) {}

    public function name(): string
    {
        return $this->name;
    }

    public function send(SendMessage $message): SendResult
    {
        $this->sent[] = $message;

        return $this->result($message->envelopeRecipients());
    }

    public function sendRaw(string $mailFrom, array $rcptTo, string $rawMessage, bool $bounce = false): SendResult
    {
        $this->sentRaw[] = new RecordedRawMessage($this->name, $mailFrom, $rcptTo, $rawMessage, $bounce);

        return $this->result($rcptTo);
    }

    public function message(int $id, bool|array $expansions = true): MessageDetails
    {
        return $this->messages[$id] ?? throw new MessageNotFoundException(
            'No message found matching provided ID',
            'MessageNotFound',
            ['id' => $id],
        );
    }

    public function deliveries(int $id): array
    {
        return $this->deliveries[$id] ?? [];
    }

    public function ping(): ConnectionStatus
    {
        return new ConnectionStatus($this->name, "https://postal.fake/{$this->name}", true, 0.1);
    }

    /**
     * Seed a canned message lookup.
     */
    public function withMessage(MessageDetails $message): self
    {
        $this->messages[$message->id] = $message;

        return $this;
    }

    /**
     * Seed canned delivery attempts for a message id.
     *
     * @param  list<Delivery>  $deliveries
     */
    public function withDeliveries(int $id, array $deliveries): self
    {
        $this->deliveries[$id] = $deliveries;

        return $this;
    }

    /**
     * @param  list<string>  $addresses
     */
    private function result(array $addresses): SendResult
    {
        $recipients = [];

        foreach ($addresses as $address) {
            $id = $this->nextId++;
            $recipients[$address] = new SendRecipient($address, $id, "fake-token-{$id}");
        }

        return new SendResult(
            messageId: sprintf('%s@%s.postal.fake', bin2hex(random_bytes(8)), $this->name),
            recipients: $recipients,
        );
    }
}
