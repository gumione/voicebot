<?php
// src/Command/ImportAudioCommand.php

namespace App\Command;

use App\Entity\Audio;
use App\Service\Telegram\VoiceSender;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;

#[AsCommand(
    name: 'app:import-audio',
    description: 'Импортирует public/audio/** (artist = имя папки) и прогревает file_id',
)]
final class ImportAudioCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly VoiceSender            $voiceSender,
        private readonly string                 $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('no-warm', null, InputOption::VALUE_NONE, 'Не заливать file_id в Telegram после импорта');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $imported = $this->import($io);
        $io->success(sprintf('Импорт завершён, новых записей: %d.', $imported));

        if ($input->getOption('no-warm')) {
            return self::SUCCESS;
        }

        if (!$this->voiceSender->isConfigured()) {
            $io->warning('TELEGRAM_STORAGE_CHAT_ID не задан — пропускаю прогрев file_id.');
            return self::SUCCESS;
        }

        $this->warmFileIds($io);

        return self::SUCCESS;
    }

    /** Scans public/audio and inserts rows that are not in the DB yet. */
    private function import(SymfonyStyle $io): int
    {
        $finder = (new Finder())
            ->files()
            ->in($this->projectDir.'/public/audio')
            ->name('/\.(mp3|wav|m4a)$/i')          // sources only — not the generated .ogg
            ->sortByName();

        // cache of existing artist|title keys
        $exists = [];
        foreach (
            $this->em->createQuery('SELECT a.title, a.artist FROM App\Entity\Audio a')
                     ->getArrayResult() as $row
        ) {
            $exists[self::makeKey($row['artist'], $row['title'])] = true;
        }

        $io->progressStart($finder->count());
        $imported = 0;
        $batch    = 0;

        foreach ($finder as $file) {
            $io->progressAdvance();

            $artist = basename($file->getRelativePath()) ?: 'Unknown';
            $title  = pathinfo($file->getFilename(), PATHINFO_FILENAME);

            $key = self::makeKey($artist, $title);
            if (isset($exists[$key])) {
                continue;
            }
            $exists[$key] = true;

            $this->em->persist(
                (new Audio())
                    ->setTitle($title)
                    ->setArtist($artist)
                    ->setPath('audio/'.$file->getRelativePathname())
            );
            $imported++;

            if ((++$batch % 200) === 0) {
                $this->em->flush();
                $this->em->clear();
            }
        }

        $this->em->flush();
        $io->progressFinish();

        return $imported;
    }

    /** Uploads every not-yet-warmed audio once to obtain a reusable file_id. */
    private function warmFileIds(SymfonyStyle $io): void
    {
        $pending = $this->em->getRepository(Audio::class)->findBy(['fileId' => null]);

        if (!$pending) {
            $io->writeln('Все file_id уже прогреты.');
            return;
        }

        $io->section(sprintf('Прогрев file_id: %d шт.', count($pending)));
        $io->progressStart(count($pending));
        $ok = $failed = 0;

        foreach ($pending as $audio) {
            try {
                $this->voiceSender->uploadAndGetFileId($audio);
                $ok++;
            } catch (\Throwable $e) {
                $failed++;
                $io->writeln(sprintf("\n<error>%s — %s</error>", $audio->getPath(), $e->getMessage()));
            }
            $io->progressAdvance();
        }

        $io->progressFinish();
        $io->writeln(sprintf('Прогрев готов: ok=%d, ошибок=%d.', $ok, $failed));
    }

    /** Case-insensitive key, trims a stray .ogg suffix. */
    private static function makeKey(string $artist, string $title): string
    {
        $clean = preg_replace('/\.ogg$/i', '', $title);
        return mb_strtolower($artist.'|'.$clean);
    }
}
