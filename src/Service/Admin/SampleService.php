<?php

declare(strict_types=1);

namespace App\Service\Admin;

use App\Entity\Audio;
use App\Entity\Bot;
use App\Message\WarmAudioMessage;
use App\Repository\AudioRepository;
use App\Repository\BotRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\MessageBusInterface;

final class SampleService
{
    private const ALLOWED = ['mp3', 'wav', 'm4a', 'ogg'];

    public function __construct(
        private readonly AudioRepository        $repo,
        private readonly BotRepository          $bots,
        private readonly EntityManagerInterface $em,
        private readonly MessageBusInterface    $bus,
        private readonly string                 $publicDir,
    ) {}

    public function bot(int $botId): Bot
    {
        return $this->bots->find($botId) ?? throw new NotFoundHttpException('Bot not found');
    }

    /** @return array{items: Audio[], total: int} */
    public function paginate(Bot $bot, int $page, int $perPage, string $search, string $sort = 'id', string $order = 'desc'): array
    {
        return $this->repo->adminPaginate((int) $bot->getId(), $page, $perPage, $search, $sort, $order);
    }

    /** @return array{pending:int, ready:int, failed:int, total:int} */
    public function statusCounts(Bot $bot): array
    {
        return $this->repo->statusCounts((int) $bot->getId());
    }

    /** @param array<string,mixed> $data */
    public function update(Bot $bot, int $id, array $data): Audio
    {
        $audio = $this->find($bot, $id);
        if (array_key_exists('title', $data)) {
            $title = trim((string) $data['title']);
            if ($title === '') {
                throw new BadRequestHttpException('title cannot be empty');
            }
            $audio->setTitle($title);
        }
        if (array_key_exists('artist', $data)) {
            $audio->setArtist(trim((string) $data['artist']) ?: 'Unknown');
        }
        $this->em->flush();

        return $audio;
    }

    /** Re-queue every not-yet-warmed sample of the bot. @return int number queued */
    public function retryAll(Bot $bot): int
    {
        if (!$bot->getStorageChatId()) {
            throw new BadRequestHttpException('Bot has no storage chat configured.');
        }

        $ids = $this->repo->notWarmedIds((int) $bot->getId());
        if ($ids) {
            $this->em->createQuery('UPDATE App\Entity\Audio a SET a.status = :p WHERE a.bot = :b AND a.fileId IS NULL')
                ->setParameter('p', Audio::STATUS_PENDING)
                ->setParameter('b', $bot->getId())
                ->execute();
            foreach ($ids as $id) {
                $this->bus->dispatch(new WarmAudioMessage($id));
            }
        }

        return count($ids);
    }

    public function upload(Bot $bot, UploadedFile $file, ?string $title, ?string $artist): Audio
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: (string) $file->guessExtension());
        if (!in_array($ext, self::ALLOWED, true)) {
            throw new BadRequestHttpException('Unsupported file type: '.($ext ?: 'unknown'));
        }

        $artist = trim((string) $artist) ?: 'Unknown';
        $base   = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $title  = trim((string) $title) ?: $base;

        $relDir = 'audio/'.$bot->getUsername().'/'.$this->safe($artist);
        $absDir = $this->publicDir.'/'.$relDir;
        if (!is_dir($absDir) && !@mkdir($absDir, 0775, true) && !is_dir($absDir)) {
            throw new \RuntimeException('Cannot create upload directory.');
        }

        $fname = $this->safe($base).'.'.$ext;
        if (file_exists($absDir.'/'.$fname)) {
            $fname = bin2hex(random_bytes(4)).'-'.$fname;
        }
        $file->move($absDir, $fname);

        $audio = (new Audio())
            ->setBot($bot)
            ->setTitle($title)
            ->setArtist($artist)
            ->setPath($relDir.'/'.$fname)
            ->setStatus(Audio::STATUS_PENDING);
        $this->em->persist($audio);
        $this->em->flush();

        if ($bot->getStorageChatId()) {
            $this->bus->dispatch(new WarmAudioMessage((int) $audio->getId()));
        }

        return $audio;
    }

    public function delete(Bot $bot, int $id): void
    {
        $audio = $this->find($bot, $id);

        $abs = $this->publicDir.'/'.ltrim($audio->getPath(), '/');
        @unlink($abs);
        @unlink($abs.'.ogg');

        $this->em->remove($audio);
        $this->em->flush();
    }

    public function retry(Bot $bot, int $id): Audio
    {
        $audio = $this->find($bot, $id);
        $audio->setStatus(Audio::STATUS_PENDING);
        $this->em->flush();

        if ($bot->getStorageChatId()) {
            $this->bus->dispatch(new WarmAudioMessage((int) $audio->getId()));
        }

        return $audio;
    }

    private function find(Bot $bot, int $id): Audio
    {
        $audio = $this->repo->find($id);
        if (!$audio || $audio->getBot()?->getId() !== $bot->getId()) {
            throw new NotFoundHttpException('Sample not found');
        }
        return $audio;
    }

    private function safe(string $s): string
    {
        $s = preg_replace('#[/\\\\:*?"<>|]+#u', '_', $s) ?? $s;
        return trim($s) !== '' ? trim($s) : 'x';
    }

    /** @return array<string,mixed> */
    public function toArray(Audio $a): array
    {
        return [
            'id'        => $a->getId(),
            'title'     => $a->getTitle(),
            'artist'    => $a->getArtist(),
            'status'    => $a->getStatus(),
            'hasFileId' => $a->getFileId() !== null,
            'path'      => $a->getPath(),
        ];
    }
}
