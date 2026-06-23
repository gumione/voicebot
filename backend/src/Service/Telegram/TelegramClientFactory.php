<?php

declare(strict_types=1);

namespace App\Service\Telegram;

use App\Entity\Bot;
use Longman\TelegramBot\Request;

/**
 * Longman exposes the API only through the global static Request facade, so in a
 * multi-bot process we must point it at the right bot before each use. This
 * builds (and caches) a Telegram client per bot and re-initializes Request.
 *
 * One HTTP webhook request handles exactly one bot; the warming worker calls
 * use() per message before talking to Telegram.
 */
final class TelegramClientFactory
{
    /** @var array<int|string, TelegramService> */
    private array $cache = [];

    public function use(Bot $bot): TelegramService
    {
        $key = $bot->getId() ?? $bot->getWebhookToken();
        $client = $this->cache[$key] ??= new TelegramService($bot->getToken(), $bot->getUsername());
        Request::initialize($client); // re-point the global facade at this bot
        return $client;
    }
}
