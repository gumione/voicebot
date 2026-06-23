<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Bot;
use App\Repository\AudioRepository;
use App\Service\Telegram\UserService;
use Longman\TelegramBot\Entities\InlineQuery\InlineQueryResultArticle;
use Longman\TelegramBot\Entities\InlineQuery\InlineQueryResultCachedAudio;
use Longman\TelegramBot\Entities\InputMessageContent\InputTextMessageContent;
use Longman\TelegramBot\Entities\Update;
use Longman\TelegramBot\Request;
use Psr\Log\LoggerInterface;

/**
 * Inline-query handler, scoped to one bot. Read-only: it only serves file_ids
 * already warmed by the worker, so the webhook stays fast.
 */
final class InlineQueryHandler
{
    private const LIMIT = 50;

    public function __construct(
        private readonly AudioRepository $audioRepo,
        private readonly SampleSearch    $sampleSearch,
        private readonly UserService     $userService,
        #[\SensitiveParameter] private readonly LoggerInterface $telegramLogger, // channel "telegram"
    ) {}

    /**
     * The global Request facade must already point at $bot (see TelegramClientFactory::use).
     *
     * @param string $payload raw JSON from the Telegram webhook
     */
    public function handle(Bot $bot, string $payload): void
    {
        $update = new Update(json_decode($payload, true));

        $inlineQuery = $update->getInlineQuery();
        if (!$inlineQuery) {
            return; // we only care about inline queries
        }

        $query  = trim($inlineQuery->getQuery());
        $offset = (int) $inlineQuery->getOffset();
        $botId  = $bot->getId();

        $this->userService->ensure($inlineQuery->getFrom());

        /* ---------- Search (exact → layout → fuzzy), scoped to this bot ---------- */
        if ($query !== '') {
            $audios = $this->sampleSearch->search($query, self::LIMIT, $offset, $botId);
        } else {
            $audios = $this->audioRepo->findAllPaginated(self::LIMIT, $offset, $botId);
        }

        /* ---------- Build results ---------- */
        $results = [];
        foreach ($audios as $audio) {
            $fileId = $audio->getFileId();
            if (!$fileId) {
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
            'bot'     => $bot->getUsername(),
            'query'   => $query,
            'results' => count($results),
        ]);
    }
}
