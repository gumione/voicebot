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
    #[Route('/bot/index', methods: ['POST'])]
    public function index(
        HttpRequest          $request,
        InlineQueryHandler   $handler,
        LoggerInterface      $logger,
    ): JsonResponse
    {
        $payload = $request->getContent();
        $logger->debug('Incoming update', ['body' => $payload]);

        try {
            $handler->handle($payload, $request->getSchemeAndHttpHost());
        } catch (\Throwable $e) {
            $logger->error($e->getMessage(), ['trace' => $e->getTraceAsString()]);
        }

        return new JsonResponse(['ok' => true]);
    }
}
