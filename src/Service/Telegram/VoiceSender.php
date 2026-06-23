<?php
namespace App\Service\Telegram;

use App\Entity\Audio;
use Doctrine\ORM\EntityManagerInterface;
use Longman\TelegramBot\Request;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Converts a source file to OGG/Opus and uploads it once to the storage chat
 * to obtain a reusable Telegram file_id. Meant to run offline (import command),
 * NOT inside the inline-query hot path.
 */
final class VoiceSender
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly string                 $publicDir,
        private readonly TelegramService        $telegram, // ensures Request:: is initialized
        private readonly LoggerInterface        $ffmpegLogger,
        private readonly int                    $storageChatId,
    ) {}

    public function isConfigured(): bool
    {
        return $this->storageChatId !== 0;
    }

    /** Returns the cached file_id, uploading via the storage chat on first call. */
    public function uploadAndGetFileId(Audio $audio): string
    {
        if ($cached = $audio->getFileId()) {
            return $cached;
        }

        if ($this->storageChatId === 0) {
            throw new \RuntimeException(
                'TELEGRAM_STORAGE_CHAT_ID is not configured — cannot upload to obtain a file_id.'
            );
        }

        $src = $this->publicDir.'/'.ltrim($audio->getPath(), '/');
        $dst = $src.'.ogg';

        if (!file_exists($dst)) {
            $this->convertToOpus($src, $dst);
        }

        /* Sent as AUDIO (two-line title/performer in the inline picker). */
        $resp = Request::sendAudio([
            'chat_id'              => $this->storageChatId,
            'audio'                => Request::encodeFile($dst),
            'title'                => $audio->getTitle(),
            'performer'            => $audio->getArtist(),
            'disable_notification' => true,
        ]);

        if (!$resp->isOk()) {
            throw new \RuntimeException('Telegram error: '.$resp->getDescription());
        }

        $result = $resp->getResult();

        $fileId = $result->getAudio()?->getFileId()
            ?? $result->getVoice()?->getFileId();

        if (!$fileId) {
            throw new \RuntimeException('Cannot obtain file_id from sendAudio result');
        }

        $audio->setFileId($fileId);
        $this->em->flush();

        /* Remove the technical upload message from the storage chat. */
        Request::deleteMessage([
            'chat_id'    => $this->storageChatId,
            'message_id' => $result->getMessageId(),
        ]);

        return $fileId;
    }

    private function convertToOpus(string $src, string $dst): void
    {
        $cmd  = ['ffmpeg', '-i', $src, '-c:a', 'libopus', '-y', $dst];
        $proc = new Process($cmd);
        $proc->setTimeout(30)->run();

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
