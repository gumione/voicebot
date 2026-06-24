<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\BotRepository;
use App\Service\InlineQueryHandler;
use App\Service\Telegram\TelegramClientFactory;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Single multi-tenant webhook. The {webhookToken} path segment both routes the
 * update to the right Bot and authenticates it (it is an unguessable secret).
 */
final class WebhookController extends AbstractController
{
    public function __construct(
        private readonly BotRepository         $bots,
        private readonly TelegramClientFactory $clients,
    ) {}

    #[Route('/bot/{webhookToken}/webhook', name: 'bot_webhook', methods: ['POST'])]
    public function webhook(
        string             $webhookToken,
        HttpRequest        $request,
        InlineQueryHandler $handler,
        LoggerInterface    $logger,
    ): JsonResponse {
        $bot = $this->bots->findActiveByWebhookToken($webhookToken);
        if (!$bot) {
            return new JsonResponse(['ok' => false], 404);
        }

        // If setWebhook was called with secret_token, Telegram echoes it — verify.
        $given = (string) $request->headers->get('X-Telegram-Bot-Api-Secret-Token');
        if ($given !== '' && !hash_equals($webhookToken, $given)) {
            $logger->warning('Webhook secret mismatch', ['bot' => $bot->getUsername()]);
            return new JsonResponse(['ok' => false], 403);
        }

        $this->clients->use($bot); // point the global Request facade at this bot

        try {
            $handler->handle($bot, $request->getContent());
        } catch (\Throwable $e) {
            // Always answer 200 so Telegram does not retry in a loop.
            $logger->error($e->getMessage(), ['trace' => $e->getTraceAsString()]);
        }

        return new JsonResponse(['ok' => true]);
    }
}
