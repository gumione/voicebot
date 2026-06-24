<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Render every exception under /admin/api and /api as JSON. 500s leak nothing
 * unless APP_ENV=dev.
 */
final class ApiExceptionSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly bool $debug) {}

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::EXCEPTION => 'onException'];
    }

    public function onException(ExceptionEvent $event): void
    {
        $path = $event->getRequest()->getPathInfo();
        if (!str_starts_with($path, '/admin/api') && !str_starts_with($path, '/api')) {
            return;
        }

        $e = $event->getThrowable();
        if ($e instanceof HttpExceptionInterface) {
            $status  = $e->getStatusCode();
            $payload = ['message' => $e->getMessage()];
        } else {
            $status  = 500;
            $payload = ['message' => 'Internal server error'];
            if ($this->debug) {
                $payload['debug'] = $e->getMessage();
            }
        }

        $event->setResponse(new JsonResponse($payload, $status));
    }
}
