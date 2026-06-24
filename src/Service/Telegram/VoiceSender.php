<?php

declare(strict_types=1);

namespace App\Service\Telegram;

use App\Entity\Audio;
use App\Entity\Bot;
use Doctrine\ORM\EntityManagerInterface;
use Longman\TelegramBot\Entities\ServerResponse;
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
    /** Gap before each upload — keeps the warming worker well under Telegram's ~20/min-per-chat
     *  limit (reliability over speed; warming is a background job, so pacing it is fine). */
    private const SEND_GAP_SECONDS = 5;

    /** How many times to wait out a Telegram 429 (retry_after) before giving up on a sample. */
    private const MAX_RATE_LIMIT_RETRIES = 6;

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

        /* Sent as AUDIO (two-line title/performer in the inline picker), throttled + 429-aware. */
        $resp = $this->sendAudioPaced($storageChatId, $dst, $audio);

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

    /**
     * sendAudio with a fixed inter-send gap (proactive throttle) + 429 handling: on a Telegram
     * "Too Many Requests: retry after N", wait N (+buffer) and retry instead of failing the sample.
     * Blocking by design — warming runs in a dedicated background worker.
     */
    private function sendAudioPaced(int|string $chatId, string $file, Audio $audio): ServerResponse
    {
        for ($attempt = 0; ; ++$attempt) {
            sleep(self::SEND_GAP_SECONDS);

            $resp = Request::sendAudio([
                'chat_id'              => $chatId,
                'audio'                => Request::encodeFile($file),
                'title'                => $audio->getTitle(),
                'performer'            => $audio->getArtist(),
                'disable_notification' => true,
            ]);

            if ($resp->isOk()) {
                return $resp;
            }

            $desc = (string) $resp->getDescription();
            if (1 === preg_match('/retry after (\d+)/i', $desc, $m) && $attempt < self::MAX_RATE_LIMIT_RETRIES) {
                $wait = (int) $m[1] + 2; // honour Telegram's cooldown, with a small buffer
                $this->ffmpegLogger->warning('Telegram 429 — backing off', [
                    'audio'       => $audio->getId(),
                    'retry_after' => $wait,
                    'attempt'     => $attempt + 1,
                ]);
                sleep($wait);
                continue;
            }

            throw new \RuntimeException('Telegram error: '.$desc);
        }
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
