<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Client;

/**
 * How a server connection submits mail. Postal accepts messages three ways:
 * the structured HTTP API, raw RFC 2822 over the HTTP API, and classic SMTP
 * submission (API / SMTP credential types in Postal).
 */
enum ConnectionType: string
{
    /** Structured JSON sends via /api/v1/send/message. */
    case Api = 'api';

    /** Raw RFC 2822 messages via /api/v1/send/raw — SMTP semantics over the HTTP API. */
    case SmtpApi = 'smtp-api';

    /** Classic SMTP submission with an SMTP (or SMTP-IP) credential. */
    case Smtp = 'smtp';

    /**
     * Whether this connection talks to Postal's HTTP API (and can therefore
     * look up messages, deliveries and ping via the API).
     */
    public function usesApi(): bool
    {
        return $this !== self::Smtp;
    }
}
