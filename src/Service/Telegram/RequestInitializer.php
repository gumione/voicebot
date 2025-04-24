<?php
// src/Service/Telegram/RequestInitializer.php

namespace App\Service\Telegram;

use Longman\TelegramBot\Request;

final class RequestInitializer
{
    public function __construct(TelegramService $telegram)
    {
        // 👇 регистрация Telegram-инстанса в глобальном Request
        Request::initialize($telegram);
    }
}
