<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\Admin\SampleService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/api/bots/{botId}/samples', requirements: ['botId' => '\d+'])]
final class SampleController extends AbstractController
{
    public function __construct(private readonly SampleService $svc) {}

    #[Route('', name: 'admin_samples_list', methods: ['GET'])]
    public function list(int $botId, Request $r): JsonResponse
    {
        $bot     = $this->svc->bot($botId);
        $page    = max(1, (int) $r->query->get('page', 1));
        $perPage = min(100, max(1, (int) $r->query->get('per_page', 20)));
        $res     = $this->svc->paginate(
            $bot, $page, $perPage,
            trim((string) $r->query->get('search', '')),
            (string) $r->query->get('sort', 'id'),
            (string) $r->query->get('order', 'desc'),
        );

        return $this->json([
            'data' => array_map(fn($a) => $this->svc->toArray($a), $res['items']),
            'meta' => [
                'page'          => $page,
                'per_page'      => $perPage,
                'total'         => $res['total'],
                'total_pages'   => (int) ceil($res['total'] / $perPage),
                'status_counts' => $this->svc->statusCounts($bot),
            ],
        ]);
    }

    #[Route('/retry-all', name: 'admin_samples_retry_all', methods: ['POST'])]
    public function retryAll(int $botId): JsonResponse
    {
        $queued = $this->svc->retryAll($this->svc->bot($botId));
        return $this->json(['data' => ['queued' => $queued]]);
    }

    #[Route('/{id}', name: 'admin_samples_update', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    public function update(int $botId, int $id, Request $r): JsonResponse
    {
        $audio = $this->svc->update($this->svc->bot($botId), $id, json_decode($r->getContent(), true) ?? []);
        return $this->json(['data' => $this->svc->toArray($audio)]);
    }

    #[Route('', name: 'admin_samples_upload', methods: ['POST'])]
    public function upload(int $botId, Request $r): JsonResponse
    {
        $bot  = $this->svc->bot($botId);
        $file = $r->files->get('file');
        if (!$file) {
            throw new BadRequestHttpException('No file uploaded (field "file").');
        }

        $audio = $this->svc->upload($bot, $file, $r->request->get('title'), $r->request->get('artist'));

        return $this->json(['data' => $this->svc->toArray($audio)], 201);
    }

    #[Route('/{id}', name: 'admin_samples_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $botId, int $id): JsonResponse
    {
        $this->svc->delete($this->svc->bot($botId), $id);
        return $this->json(['status' => 'ok']);
    }

    #[Route('/{id}/retry', name: 'admin_samples_retry', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function retry(int $botId, int $id): JsonResponse
    {
        $audio = $this->svc->retry($this->svc->bot($botId), $id);
        return $this->json(['data' => $this->svc->toArray($audio)]);
    }
}
