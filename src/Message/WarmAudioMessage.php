<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Async job: convert a sample and upload it once to its bot's storage chat to
 * obtain a reusable Telegram file_id. Dispatched on import / admin upload.
 */
final class WarmAudioMessage
{
    public function __construct(public readonly int $audioId)
    {
    }
}
