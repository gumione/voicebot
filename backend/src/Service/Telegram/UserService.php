<?php
namespace App\Service\Telegram;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Longman\TelegramBot\Entities\User as TgUser;
use Psr\Log\LoggerInterface;

final class UserService
{
    public function __construct(
        private readonly UserRepository       $repo,
        private readonly EntityManagerInterface $em,
        #[\SensitiveParameter] private readonly LoggerInterface $telegramLogger, // канал "telegram"
    ) {}

    /**
     * Находит или создаёт пользователя по Telegram-ID.
     */
    public function ensure(TgUser $tgUser): User
    {
        $user = $this->repo->findOneBy(['telegramId' => $tgUser->getId()]);
        if ($user) {
            return $user;
        }

        $user = (new User())
            ->setTelegramId($tgUser->getId())
            ->setUsername($tgUser->getUsername())
            ->setFirstName($tgUser->getFirstName())
            ->setLastName($tgUser->getLastName());

        $this->em->persist($user);
        $this->em->flush();

        $this->telegramLogger->info('New user saved', ['telegramId' => $tgUser->getId()]);

        return $user;
    }
}
