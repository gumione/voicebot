<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Audio;
use App\Entity\Bot;
use App\Message\WarmAudioMessage;
use App\Repository\BotRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:import-audio',
    description: 'Импортирует сэмплы бота и ставит их в очередь на прогрев file_id',
)]
final class ImportAudioCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly BotRepository          $bots,
        private readonly MessageBusInterface    $bus,
        private readonly string                 $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('bot', InputArgument::REQUIRED, 'Bot id or username')
             ->addOption('no-warm', null, InputOption::VALUE_NONE, 'Не ставить в очередь на прогрев');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io  = new SymfonyStyle($input, $output);
        $bot = $this->resolveBot((string) $input->getArgument('bot'));
        if (!$bot) {
            $io->error('Bot not found (pass an id or username).');
            return Command::FAILURE;
        }

        // Per-bot folder if present, otherwise the shared audio root.
        $publicAudio = $this->projectDir.'/public/audio';
        $perBot      = $publicAudio.'/'.$bot->getUsername();
        $scanDir     = is_dir($perBot) ? $perBot : $publicAudio;
        $prefix      = is_dir($perBot) ? 'audio/'.$bot->getUsername() : 'audio';

        if (!is_dir($scanDir)) {
            $io->error("Directory not found: $scanDir");
            return Command::FAILURE;
        }

        $new = $this->import($io, $bot, $scanDir, $prefix);
        $io->success(sprintf('Импорт "%s": новых записей %d.', $bot->getUsername(), count($new)));

        if ($input->getOption('no-warm') || !$new) {
            return Command::SUCCESS;
        }
        if (!$bot->getStorageChatId()) {
            $io->warning('У бота не задан storage_chat_id — прогрев пропущен (задайте и запустите снова).');
            return Command::SUCCESS;
        }

        foreach ($new as $id) {
            $this->bus->dispatch(new WarmAudioMessage($id));
        }
        $io->writeln(sprintf('В очередь на прогрев поставлено: %d. Запустите воркер: <info>messenger:consume async</info>', count($new)));

        return Command::SUCCESS;
    }

    /** @return int[] ids of newly created rows */
    private function import(SymfonyStyle $io, Bot $bot, string $scanDir, string $prefix): array
    {
        $finder = (new Finder())->files()->in($scanDir)->name('/\.(mp3|wav|m4a)$/i')->sortByName();

        // existing keys for THIS bot only
        $exists = [];
        foreach (
            $this->em->createQuery('SELECT a.title, a.artist FROM App\Entity\Audio a WHERE a.bot = :bot')
                     ->setParameter('bot', $bot->getId())->getArrayResult() as $row
        ) {
            $exists[self::makeKey($row['artist'], $row['title'])] = true;
        }

        $io->progressStart($finder->count());
        $new = [];

        foreach ($finder as $file) {
            $io->progressAdvance();

            $artist = basename($file->getRelativePath()) ?: 'Unknown';
            $title  = pathinfo($file->getFilename(), PATHINFO_FILENAME);

            $key = self::makeKey($artist, $title);
            if (isset($exists[$key])) {
                continue;
            }
            $exists[$key] = true;

            $audio = (new Audio())
                ->setBot($bot)
                ->setTitle($title)
                ->setArtist($artist)
                ->setPath($prefix.'/'.$file->getRelativePathname())
                ->setStatus(Audio::STATUS_PENDING);
            $this->em->persist($audio);
            $new[] = $audio;
        }

        $this->em->flush();
        $io->progressFinish();

        return array_map(static fn(Audio $a) => (int) $a->getId(), $new);
    }

    private function resolveBot(string $ref): ?Bot
    {
        if (ctype_digit($ref)) {
            return $this->bots->find((int) $ref);
        }
        return $this->bots->findOneBy(['username' => ltrim($ref, '@')]);
    }

    /** Case-insensitive key, trims a stray .ogg suffix. */
    private static function makeKey(string $artist, string $title): string
    {
        $clean = preg_replace('/\.ogg$/i', '', $title);
        return mb_strtolower($artist.'|'.$clean);
    }
}
