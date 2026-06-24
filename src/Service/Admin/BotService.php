<?php

declare(strict_types=1);

namespace App\Service\Admin;

use App\Entity\Bot;
use App\Repository\BotRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class BotService
{
    public function __construct(
        private readonly BotRepository          $repo,
        private readonly EntityManagerInterface $em,
    ) {}

    /** @return array{items: Bot[], total: int} */
    public function paginate(int $page, int $perPage): array
    {
        return $this->repo->paginate($page, $perPage);
    }

    public function get(int $id): Bot
    {
        return $this->repo->find($id) ?? throw new NotFoundHttpException('Bot not found');
    }

    /** @param array<string,mixed> $data */
    public function create(array $data): Bot
    {
        $bot = (new Bot())->setWebhookToken(bin2hex(random_bytes(24)));
        $this->apply($bot, $data, true);
        $this->em->persist($bot);
        $this->em->flush();

        return $bot;
    }

    /** @param array<string,mixed> $data */
    public function update(int $id, array $data): Bot
    {
        $bot = $this->get($id);
        $this->apply($bot, $data, false);
        $this->em->flush();

        return $bot;
    }

    public function delete(int $id): void
    {
        $this->em->remove($this->get($id));
        $this->em->flush();
    }

    /** @param array<string,mixed> $data */
    private function apply(Bot $bot, array $data, bool $isNew): void
    {
        if ($isNew || array_key_exists('username', $data)) {
            $username = trim((string) ($data['username'] ?? ''));
            if ($username === '') {
                throw new BadRequestHttpException('username is required');
            }
            $bot->setUsername($username);
        }
        if ($isNew || array_key_exists('name', $data)) {
            $name = trim((string) ($data['name'] ?? ''));
            $bot->setName($name !== '' ? $name : $bot->getUsername());
        }
        // Token is write-only and optional on update (empty = keep current).
        if (array_key_exists('token', $data) && (string) $data['token'] !== '') {
            $bot->setToken((string) $data['token']);
        }
        if ($isNew && $bot->getToken() === '') {
            throw new BadRequestHttpException('token is required');
        }
        if (array_key_exists('storageChatId', $data)) {
            $bot->setStorageChatId($data['storageChatId'] === '' ? null : $data['storageChatId']);
        }
        if (array_key_exists('isActive', $data)) {
            $bot->setIsActive((bool) $data['isActive']);
        }
    }

    /** @return array<string,mixed> */
    public function toArray(Bot $bot): array
    {
        return [
            'id'            => $bot->getId(),
            'name'          => $bot->getName(),
            'username'      => $bot->getUsername(),
            'hasToken'      => $bot->getToken() !== '',
            'storageChatId' => $bot->getStorageChatId(),
            'webhookToken'  => $bot->getWebhookToken(),
            'isActive'      => $bot->isActive(),
            'sampleCount'   => $bot->getAudios()->count(),
            'createdAt'     => $bot->getCreatedAt()->format(\DATE_ATOM),
        ];
    }
}
