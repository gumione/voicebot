<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Audio;
use App\Message\WarmAudioMessage;
use App\Repository\AudioRepository;
use App\Service\Telegram\VoiceSender;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class WarmAudioHandler
{
    public function __construct(
        private readonly AudioRepository        $repo,
        private readonly VoiceSender            $voiceSender,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface        $telegramLogger,
    ) {}

    public function __invoke(WarmAudioMessage $message): void
    {
        $audio = $this->repo->find($message->audioId);
        if (!$audio || $audio->getFileId()) {
            return; // deleted or already warmed
        }

        $bot = $audio->getBot();
        if (!$bot) {
            $this->telegramLogger->warning('Warm skipped: audio has no bot', ['audio' => $message->audioId]);
            return;
        }

        try {
            $this->voiceSender->uploadAndGetFileId($bot, $audio); // sets file_id + flushes
            $audio->setStatus(Audio::STATUS_READY);
        } catch (\Throwable $e) {
            $audio->setStatus(Audio::STATUS_FAILED);
            $this->telegramLogger->error('Warm failed', ['audio' => $message->audioId, 'error' => $e->getMessage()]);
        }

        $this->em->flush();
    }
}
