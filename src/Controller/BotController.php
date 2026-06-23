<?php
// src/Controller/BotController.php

namespace App\Controller;

use App\Service\InlineQueryHandler;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Symfony\Component\Routing\Annotation\Route;

final class BotController extends AbstractController
{
    public function __construct(
        #[\SensitiveParameter] private readonly string $webhookSecret,
    ) {}

    #[Route('/bot/index', methods: ['POST'])]
    public function index(
        HttpRequest        $request,
        InlineQueryHandler $handler,
        LoggerInterface    $logger,
    ): JsonResponse {
        // Verify the request really comes from Telegram (set the same value via setWebhook secret_token).
        if ($this->webhookSecret !== '') {
            $given = (string) $request->headers->get('X-Telegram-Bot-Api-Secret-Token');
            if (!hash_equals($this->webhookSecret, $given)) {
                $logger->warning('Webhook secret mismatch — rejected');
                return new JsonResponse(['ok' => false], 403);
            }
        }

        try {
            $handler->handle($request->getContent());
        } catch (\Throwable $e) {
            // Always answer 200 so Telegram does not retry the same update in a loop.
            $logger->error($e->getMessage(), ['trace' => $e->getTraceAsString()]);
        }

        return new JsonResponse(['ok' => true]);
    }
}
