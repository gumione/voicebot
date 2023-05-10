<?php

// src/Service/TelegramService.php
namespace App\Service;

use Longman\TelegramBot\Telegram;

class TelegramService extends Telegram
{
    public function __construct(string $apiKey, string $botName)
    {
        parent::__construct($apiKey, $botName);
    }
}