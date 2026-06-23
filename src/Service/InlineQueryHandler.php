<?php
namespace App\Service;

use App\Repository\AudioRepository;
use App\Service\Telegram\TelegramService;
use App\Service\Telegram\UserService;
use Longman\TelegramBot\Entities\InlineQuery\InlineQueryResultArticle;
use Longman\TelegramBot\Entities\InlineQuery\InlineQueryResultCachedAudio;
use Longman\TelegramBot\Entities\InputMessageContent\InputTextMessageContent;
use Longman\TelegramBot\Entities\Update;
use Longman\TelegramBot\Request;
use Psr\Log\LoggerInterface;

/**
 * Central inline-query handler. Read-only: it only serves file_ids that were
 * already warmed by the import command, so the webhook stays fast.
 */
final class InlineQueryHandler
{
    private const LIMIT = 50;

    public function __construct(
        private readonly AudioRepository $audioRepo,
        private readonly UserService     $userService,
        private readonly TelegramService $telegram, // guarantees Request:: is initialized
        #[\SensitiveParameter] private readonly LoggerInterface $telegramLogger, // channel "telegram"
    ) {}

    /** @param string $payload raw JSON from the Telegram webhook */
    public function handle(string $payload): void
    {
        $update = new Update(json_decode($payload, true));

        $inlineQuery = $update->getInlineQuery();
        if (!$inlineQuery) {
            return; // we only care about inline queries
        }

        $query  = trim($inlineQuery->getQuery());
        $offset = (int) $inlineQuery->getOffset();

        $this->userService->ensure($inlineQuery->getFrom());

        /* ---------- Search ---------- */
        if ($query !== '') {
            $audios = $this->audioRepo->search($query, self::LIMIT, $offset);
        } else {
            $audios = $this->audioRepo->findAllPaginated(self::LIMIT, $offset);
        }

        /* ---------- Build results ---------- */
        $results = [];
        foreach ($audios as $audio) {
            $fileId = $audio->getFileId();
            if (!$fileId) {
                // Not warmed yet — run app:import-audio. Skip instead of blocking the webhook.
                $this->telegramLogger->warning('Audio without file_id skipped', ['id' => $audio->getId()]);
                continue;
            }

            $results[] = new InlineQueryResultCachedAudio([
                'id'            => (string) $audio->getId(),
                'audio_file_id' => $fileId,
                'title'         => $audio->getTitle(),
                'performer'     => $audio->getArtist(),
            ]);
        }

        if (!$results) {
            $results[] = new InlineQueryResultArticle([
                'id'    => '0',
                'title' => 'Ничего не найдено',
                'input_message_content' => new InputTextMessageContent([
                    'message_text' => '¯\\_(ツ)_/¯',
                ]),
            ]);
        }

        /* ---------- Answer ---------- */
        $nextOffset = (count($results) >= self::LIMIT) ? (string) ($offset + self::LIMIT) : '';

        Request::answerInlineQuery([
            'inline_query_id' => $inlineQuery->getId(),
            'results'         => $results,
            'cache_time'      => 300,
            'next_offset'     => $nextOffset,
        ]);

        $this->telegramLogger->debug('inline query', [
            'query'   => $query,
            'results' => count($results),
            'offset'  => $offset,
        ]);
    }
}
