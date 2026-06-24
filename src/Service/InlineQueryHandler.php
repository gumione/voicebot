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
        $data = json_decode($payload, true);
        if (!is_array($data)) {
            return;
        }
        $this->handleUpdate($bot, new Update($data));
    }

    /** Same logic, but for an already-parsed Update (used by the long-poll command). */
    public function handleUpdate(Bot $bot, Update $update): void
    {
        $inlineQuery = $update->getInlineQuery();
        if (!$inlineQuery) {
            return; // we only care about inline queries
        }

        $query  = trim($inlineQuery->getQuery());
        $offset = (int) $inlineQuery->getOffset();
        $botId  = $bot->getId();

        $this->userService->ensure($inlineQuery->getFrom());

        /* ---------- Search (exact → layout → fuzzy), scoped to this bot ----------
           Fetch one extra row to know whether a next page exists. The repository
           returns only warmed rows (file_id IS NOT NULL), so every result is
           renderable and the offset stays exact across pages. */
        $fetch = self::LIMIT + 1;
        if ($query !== '') {
            $audios = $this->sampleSearch->search($query, $fetch, $offset, $botId);
        } else {
            $audios = $this->audioRepo->findAllPaginated($fetch, $offset, $botId);
        }

        $hasMore = count($audios) > self::LIMIT;
        $audios  = array_slice($audios, 0, self::LIMIT);

        /* ---------- Build results ---------- */
        $results = [];
        foreach ($audios as $audio) {
            $results[] = new InlineQueryResultCachedAudio([
                'id'            => (string) $audio->getId(),
                'audio_file_id' => (string) $audio->getFileId(),
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
        $nextOffset = $hasMore ? (string) ($offset + self::LIMIT) : '';

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
