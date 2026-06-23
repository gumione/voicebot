<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\Admin\BotService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/api/bots')]
final class BotController extends AbstractController
{
    public function __construct(private readonly BotService $svc) {}

    #[Route('', name: 'admin_bots_list', methods: ['GET'])]
    public function list(Request $r): JsonResponse
    {
        $page    = max(1, (int) $r->query->get('page', 1));
        $perPage = min(100, max(1, (int) $r->query->get('per_page', 20)));
        $res     = $this->svc->paginate($page, $perPage);

        return $this->json([
            'data' => array_map(fn($b) => $this->svc->toArray($b), $res['items']),
            'meta' => [
                'page'        => $page,
                'per_page'    => $perPage,
                'total'       => $res['total'],
                'total_pages' => (int) ceil($res['total'] / $perPage),
            ],
        ]);
    }

    #[Route('', name: 'admin_bots_create', methods: ['POST'])]
    public function create(Request $r): JsonResponse
    {
        $bot = $this->svc->create(json_decode($r->getContent(), true) ?? []);
        return $this->json(['data' => $this->svc->toArray($bot)], 201);
    }

    #[Route('/{id}', name: 'admin_bots_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): JsonResponse
    {
        return $this->json(['data' => $this->svc->toArray($this->svc->get($id))]);
    }

    #[Route('/{id}', name: 'admin_bots_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(int $id, Request $r): JsonResponse
    {
        $bot = $this->svc->update($id, json_decode($r->getContent(), true) ?? []);
        return $this->json(['data' => $this->svc->toArray($bot)]);
    }

    #[Route('/{id}', name: 'admin_bots_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id): JsonResponse
    {
        $this->svc->delete($id);
        return $this->json(['status' => 'ok']);
    }
}
