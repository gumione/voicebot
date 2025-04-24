<?php
namespace App\Service;

use App\Repository\AudioRepository;
use App\Service\Telegram\UserService;
use App\Service\Telegram\VoiceSender;
use Longman\TelegramBot\Entities\InlineQuery\InlineQueryResultArticle;
use Longman\TelegramBot\Entities\InlineQuery\InlineQueryResultCachedVoice;
use Longman\TelegramBot\Entities\InlineQuery\InlineQueryResultCachedAudio;
use Longman\TelegramBot\Entities\InputMessageContent\InputTextMessageContent;
use Longman\TelegramBot\Entities\Update;
use Longman\TelegramBot\Request;
use Psr\Log\LoggerInterface;

/**
 * Центральный обработчик inline-запросов.
 */
final class InlineQueryHandler
{
    public function __construct(
        private readonly AudioRepository $audioRepo,
        private readonly UserService     $userService,
        private readonly VoiceSender     $voiceSender,
        #[\SensitiveParameter] private readonly LoggerInterface $telegramLogger, // канал "telegram"
    ) {}

    /**
     * @param string $payload raw JSON из Telegram-webhook
     * @param string $host    "https://example.com" — пригодится, если решите формировать абсолютные URL
     */
    public function handle(string $payload, string $host): void
    {
        $update = new Update(json_decode($payload, true));

        if (!$update->getInlineQuery()) {
            return; // нас интересуют только inline-запросы
        }

        $inlineQuery = $update->getInlineQuery();
        $query       = trim($inlineQuery->getQuery());
        $offset      = (int) $inlineQuery->getOffset();
        $limit       = 50;

        $user = $this->userService->ensure($inlineQuery->getFrom());

        /* ---------- Поиск ---------- */
        if ($query !== '') {
            $audios = $this->audioRepo->search($query, $limit, $offset);
            $total  = $this->audioRepo->countSearch($query);
        } else {
            $audios = $this->audioRepo->findAllPaginated($limit, $offset);
            $total  = $this->audioRepo->count([]);
        }

        /* ---------- Формируем ответы ---------- */
        $results = [];
        foreach ($audios as $audio) {
            $fileId = $audio->getFileId()
                  ?? $this->voiceSender->uploadAndGetFileId($audio, $user->getTelegramId(), $host);

            $results[] = new InlineQueryResultCachedAudio([
                'id'            => $audio->getId(),
                'audio_file_id' => $fileId,
                'title'         => $audio->getTitle(),     // 1-я строка
                'performer'     => $audio->getArtist(),    // 2-я строка
            ]);
        }

        if (!$results) {
            $results[] = new InlineQueryResultArticle([
                'id'    => 0,
                'title' => 'Ничего не найдено',
                'input_message_content' => new InputTextMessageContent([
                    'message_text' => '¯\\_(ツ)_/¯',
                ]),
            ]);
        }

        /* ---------- Отправляем в Telegram ---------- */
        Request::answerInlineQuery([
            'inline_query_id' => $inlineQuery->getId(),
            'results'         => $results,
            'cache_time'      => 5,
            'next_offset'     => ($offset + $limit < $total) ? (string) ($offset + $limit) : '',
        ]);

        $this->telegramLogger->debug('inline query', [
            'query'   => $query,
            'results' => count($results),
            'offset'  => $offset,
        ]);
    }
}
