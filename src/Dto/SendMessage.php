<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Dto;

use LogicException;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Throwable;

/**
 * Fluent builder for a structured send. Renders either the
 * /api/v1/send/message JSON payload (toArray) or a MIME message (toEmail)
 * for raw / SMTP submission.
 */
class SendMessage
{
    /** @var list<string> */
    private array $to = [];

    /** @var list<string> */
    private array $cc = [];

    /** @var list<string> */
    private array $bcc = [];

    private ?string $from = null;

    private ?string $sender = null;

    private ?string $replyTo = null;

    private ?string $subject = null;

    private ?string $tag = null;

    private ?string $plainBody = null;

    private ?string $htmlBody = null;

    /** @var array<string, string> */
    private array $headers = [];

    /** @var list<Attachment> */
    private array $attachments = [];

    private bool $bounce = false;

    public static function create(): self
    {
        return new self;
    }

    /**
     * @param  string|array<string>  $address
     */
    public function to(string|array $address): self
    {
        $this->to = array_merge($this->to, is_array($address) ? array_values($address) : [$address]);

        return $this;
    }

    /**
     * @param  string|array<string>  $address
     */
    public function cc(string|array $address): self
    {
        $this->cc = array_merge($this->cc, is_array($address) ? array_values($address) : [$address]);

        return $this;
    }

    /**
     * @param  string|array<string>  $address
     */
    public function bcc(string|array $address): self
    {
        $this->bcc = array_merge($this->bcc, is_array($address) ? array_values($address) : [$address]);

        return $this;
    }

    /**
     * @param  string  $address  "Name <email>" or a bare address.
     */
    public function from(string $address): self
    {
        $this->from = $address;

        return $this;
    }

    public function sender(string $address): self
    {
        $this->sender = $address;

        return $this;
    }

    public function replyTo(string $address): self
    {
        $this->replyTo = $address;

        return $this;
    }

    public function subject(string $subject): self
    {
        $this->subject = $subject;

        return $this;
    }

    public function tag(string $tag): self
    {
        $this->tag = $tag;

        return $this;
    }

    public function plain(string $body): self
    {
        $this->plainBody = $body;

        return $this;
    }

    public function html(string $body): self
    {
        $this->htmlBody = $body;

        return $this;
    }

    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;

        return $this;
    }

    public function attach(Attachment $attachment): self
    {
        $this->attachments[] = $attachment;

        return $this;
    }

    public function bounce(bool $bounce = true): self
    {
        $this->bounce = $bounce;

        return $this;
    }

    /**
     * Render this message as MIME for raw-API or SMTP submission. The tag
     * travels as an X-Postal-Tag header, which Postal reads for messages
     * that arrive outside the structured API.
     */
    public function toEmail(): Email
    {
        if ($this->from === null) {
            throw new LogicException('A from address is required to render a MIME message.');
        }

        $email = new Email;
        $email->from($this->from);
        $email->to(...$this->to);

        if ($this->cc !== []) {
            $email->cc(...$this->cc);
        }

        if ($this->bcc !== []) {
            $email->bcc(...$this->bcc);
        }

        if ($this->sender !== null) {
            $email->sender($this->sender);
        }

        if ($this->replyTo !== null) {
            $email->replyTo($this->replyTo);
        }

        if ($this->subject !== null) {
            $email->subject($this->subject);
        }

        if ($this->plainBody !== null) {
            $email->text($this->plainBody);
        }

        if ($this->htmlBody !== null) {
            $email->html($this->htmlBody);
        }

        foreach ($this->headers as $name => $value) {
            $email->getHeaders()->addTextHeader($name, $value);
        }

        if ($this->tag !== null) {
            $email->getHeaders()->addTextHeader('X-Postal-Tag', $this->tag);
        }

        foreach ($this->attachments as $attachment) {
            $email->attach($attachment->content, $attachment->name, $attachment->contentType);
        }

        return $email;
    }

    /**
     * The bare envelope sender address, with any display name stripped.
     */
    public function envelopeFrom(): ?string
    {
        return $this->from !== null ? self::bareAddress($this->from) : null;
    }

    /**
     * All bare envelope recipient addresses: to + cc + bcc.
     *
     * @return list<string>
     */
    public function envelopeRecipients(): array
    {
        return array_map(
            static fn (string $address): string => self::bareAddress($address),
            array_merge($this->to, $this->cc, $this->bcc),
        );
    }

    public function hasRecipients(): bool
    {
        return $this->to !== [] || $this->cc !== [] || $this->bcc !== [];
    }

    public function fromAddress(): ?string
    {
        return $this->from;
    }

    public function subjectLine(): ?string
    {
        return $this->subject;
    }

    public function tagName(): ?string
    {
        return $this->tag;
    }

    public function isBounce(): bool
    {
        return $this->bounce;
    }

    /**
     * Parse via symfony/mime so the envelope agrees with the rendered
     * headers; for values Address cannot parse, fall back to the trailing
     * angle-bracket group (the addr-spec position in a name-addr), then to
     * the raw string.
     */
    private static function bareAddress(string $address): string
    {
        try {
            return Address::create($address)->getAddress();
        } catch (Throwable) {
            return preg_match('/<([^<>]+)>\s*$/', $address, $matches) === 1 ? $matches[1] : $address;
        }
    }

    /**
     * Render the /api/v1/send/message payload.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payload = [
            'to' => $this->to,
            'from' => $this->from,
        ];

        if ($this->cc !== []) {
            $payload['cc'] = $this->cc;
        }

        if ($this->bcc !== []) {
            $payload['bcc'] = $this->bcc;
        }

        foreach ([
            'sender' => $this->sender,
            'reply_to' => $this->replyTo,
            'subject' => $this->subject,
            'tag' => $this->tag,
            'plain_body' => $this->plainBody,
            'html_body' => $this->htmlBody,
        ] as $key => $value) {
            if ($value !== null) {
                $payload[$key] = $value;
            }
        }

        if ($this->headers !== []) {
            $payload['headers'] = $this->headers;
        }

        if ($this->attachments !== []) {
            $payload['attachments'] = array_map(
                static fn (Attachment $attachment): array => $attachment->toArray(),
                $this->attachments,
            );
        }

        if ($this->bounce) {
            $payload['bounce'] = true;
        }

        return $payload;
    }
}
