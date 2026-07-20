<?php

declare(strict_types=1);

namespace Cbox\LaravelPostal\Dto;

/**
 * The expansions a message lookup can request. Passing a subset keeps
 * responses small — attachments, raw_message and the bodies can be large.
 */
enum MessageExpansion: string
{
    case Status = 'status';
    case Details = 'details';
    case Inspection = 'inspection';
    case PlainBody = 'plain_body';
    case HtmlBody = 'html_body';
    case Attachments = 'attachments';
    case Headers = 'headers';
    case RawMessage = 'raw_message';
    case ActivityEntries = 'activity_entries';
}
