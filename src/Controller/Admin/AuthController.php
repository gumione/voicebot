<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\AdminUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/api')]
final class AuthController extends AbstractController
{
    /** Intercepted by the json_login firewall — never actually executed. */
    #[Route('/login', name: 'admin_login', methods: ['POST'])]
    public function login(): never
    {
        throw new \LogicException('Handled by the json_login firewall.');
    }

    #[Route('/me', name: 'admin_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        /** @var AdminUser $user */
        $user = $this->getUser();

        return $this->json(['data' => [
            'id'    => $user->getId(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
        ]]);
    }
}
