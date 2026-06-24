<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\BotRepository;
use App\Service\InlineQueryHandler;
use App\Service\Telegram\TelegramClientFactory;
use Longman\TelegramBot\Request;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Local-dev alternative to the webhook: long-poll Telegram for one bot and feed
 * its inline queries into the same handler. Needs no public URL / tunnel — only
 * outbound calls to api.telegram.org. Mutually exclusive with a set webhook
 * (this command drops it on start).
 */
#[AsCommand(name: 'app:bot:poll', description: 'Long-poll Telegram for one bot (local dev, no webhook needed)')]
final class PollBotCommand extends Command
{
    public function __construct(
        private readonly BotRepository         $bots,
        private readonly TelegramClientFactory $clients,
        private readonly InlineQueryHandler    $handler,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('bot', InputArgument::REQUIRED, 'Bot id or username')
             ->addOption('timeout', null, InputOption::VALUE_REQUIRED, 'Long-poll timeout, sec', '25');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io  = new SymfonyStyle($input, $output);
        $ref = (string) $input->getArgument('bot');
        $bot = ctype_digit($ref) ? $this->bots->find((int) $ref) : $this->bots->findOneBy(['username' => ltrim($ref, '@')]);
        if (!$bot) {
            $io->error('Bot not found.');
            return Command::FAILURE;
        }

        $this->clients->use($bot);
        Request::deleteWebhook(['drop_pending_updates' => false]); // so getUpdates is allowed

        $timeout = max(1, (int) $input->getOption('timeout'));
        $io->success(sprintf('Polling @%s — type "@%s <query>" in Telegram. Ctrl+C to stop.', $bot->getUsername(), $bot->getUsername()));

        $offset = 0;
        while (true) {
            $resp = Request::getUpdates([
                'offset'          => $offset,
                'timeout'         => $timeout,
                'allowed_updates' => ['inline_query', 'chosen_inline_result'],
            ]);

            if (!$resp->isOk()) {
                $io->writeln('<error>getUpdates: '.$resp->getDescription().'</error>');
                sleep(3);
                continue;
            }

            foreach ($resp->getResult() as $update) {
                $offset = $update->getUpdateId() + 1;
                if (!$update->getInlineQuery()) {
                    continue;
                }
                $q = $update->getInlineQuery()->getQuery();
                try {
                    $this->handler->handleUpdate($bot, $update);
                    $io->writeln(sprintf('  inline "%s" → answered', $q));
                } catch (\Throwable $e) {
                    $io->writeln(sprintf('  <error>inline "%s" → %s</error>', $q, $e->getMessage()));
                }
            }
        }
    }
}
