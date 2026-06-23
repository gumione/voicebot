<?php

// src/Service/Telegram/TelegramService.php
namespace App\Service\Telegram;

use Longman\TelegramBot\Request;
use Longman\TelegramBot\Telegram;

/**
 * Thin wrapper around Longman's Telegram client.
 *
 * Longman exposes the API only through the static Request facade, so we
 * initialize it here, in the constructor. Any service that calls Request::*
 * must depend on TelegramService to guarantee this ran first.
 */
class TelegramService extends Telegram
{
    public function __construct(string $apiKey, string $botName)
    {
        parent::__construct($apiKey, $botName);
        Request::initialize($this);
    }
}
