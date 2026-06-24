<?php

declare(strict_types=1);

namespace App\Service\Telegram;

use App\Entity\Audio;
use App\Entity\Bot;
use Doctrine\ORM\EntityManagerInterface;
use Longman\TelegramBot\Request;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Converts a source file to OGG/Opus and uploads it once to the bot's storage
 * chat to obtain a reusable Telegram file_id. Runs offline (warming worker /
 * import) — NEVER in the inline-query hot path. file_ids are per-bot.
 */
final class VoiceSender
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TelegramClientFactory  $clients,
        private readonly string                 $publicDir,
        private readonly LoggerInterface        $ffmpegLogger,
    ) {}

    /** Returns the cached file_id, uploading via the bot's storage chat on first call. */
    public function uploadAndGetFileId(Bot $bot, Audio $audio): string
    {
        if ($cached = $audio->getFileId()) {
            return $cached;
        }

        $storageChatId = $bot->getStorageChatId();
        if (!$storageChatId) {
            throw new \RuntimeException(sprintf('Bot "%s" has no storage_chat_id configured.', $bot->getUsername()));
        }

        $this->clients->use($bot); // point the global Request facade at this bot

        $src = $this->publicDir.'/'.ltrim($audio->getPath(), '/');
        $dst = $src.'.ogg';
        if (!file_exists($dst)) {
            $this->convertToOpus($src, $dst);
        }

        /* Sent as AUDIO (two-line title/performer in the inline picker). */
        $resp = Request::sendAudio([
            'chat_id'              => $storageChatId,
            'audio'                => Request::encodeFile($dst),
            'title'                => $audio->getTitle(),
            'performer'            => $audio->getArtist(),
            'disable_notification' => true,
        ]);

        if (!$resp->isOk()) {
            throw new \RuntimeException('Telegram error: '.$resp->getDescription());
        }

        $result = $resp->getResult();
        $fileId = $result->getAudio()?->getFileId() ?? $result->getVoice()?->getFileId();
        if (!$fileId) {
            throw new \RuntimeException('Cannot obtain file_id from sendAudio result');
        }

        $audio->setFileId($fileId)->setStatus(Audio::STATUS_READY);
        $this->em->flush();

        /* Remove the technical upload message from the storage chat. */
        Request::deleteMessage(['chat_id' => $storageChatId, 'message_id' => $result->getMessageId()]);

        return $fileId;
    }

    private function convertToOpus(string $src, string $dst): void
    {
        $cmd  = ['ffmpeg', '-i', $src, '-c:a', 'libopus', '-y', $dst];
        $proc = new Process($cmd);
        $proc->setTimeout(60)->run();

        $this->ffmpegLogger->debug('ffmpeg', [
            'cmd'  => implode(' ', $cmd),
            'exit' => $proc->getExitCode(),
            'err'  => $proc->getErrorOutput(),
        ]);

        if (!$proc->isSuccessful()) {
            throw new ProcessFailedException($proc);
        }
    }
}
