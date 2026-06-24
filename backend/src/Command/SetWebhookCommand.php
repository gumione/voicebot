<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Bot;
use App\Repository\BotRepository;
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
 * Registers (or deletes) the Telegram webhook for one or all bots. The bot's
 * webhookToken is used both as the URL path and as the Telegram secret_token,
 * which WebhookController verifies.
 *
 *   php bin/console app:bot:set-webhook --all --url=https://voicebot.example.com
 *   php bin/console app:bot:set-webhook voice14demobot --delete
 */
#[AsCommand(name: 'app:bot:set-webhook', description: 'Register or delete the Telegram webhook for a bot')]
final class SetWebhookCommand extends Command
{
    public function __construct(
        private readonly BotRepository         $bots,
        private readonly TelegramClientFactory $clients,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('bot', InputArgument::OPTIONAL, 'Bot id or username (omit with --all)')
             ->addOption('url', null, InputOption::VALUE_REQUIRED, 'Public base URL, e.g. https://voicebot.example.com')
             ->addOption('all', null, InputOption::VALUE_NONE, 'Apply to every active bot')
             ->addOption('delete', null, InputOption::VALUE_NONE, 'Delete the webhook instead of setting it');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io     = new SymfonyStyle($input, $output);
        $delete = (bool) $input->getOption('delete');
        $base   = rtrim((string) ($input->getOption('url') ?? ''), '/');

        if (!$delete && $base === '') {
            $io->error('--url is required (e.g. --url=https://voicebot.example.com).');
            return Command::FAILURE;
        }

        /** @var Bot[] $bots */
        if ($input->getOption('all')) {
            $bots = $this->bots->findBy(['isActive' => true]);
        } else {
            $ref = (string) $input->getArgument('bot');
            if ($ref === '') {
                $io->error('Pass a bot id/username, or --all.');
                return Command::FAILURE;
            }
            $bot  = ctype_digit($ref) ? $this->bots->find((int) $ref) : $this->bots->findOneBy(['username' => ltrim($ref, '@')]);
            $bots = $bot ? [$bot] : [];
        }

        if (!$bots) {
            $io->error('No matching bot(s).');
            return Command::FAILURE;
        }

        $failed = 0;
        foreach ($bots as $bot) {
            $this->clients->use($bot);

            if ($delete) {
                $resp = Request::deleteWebhook(['drop_pending_updates' => false]);
                $io->writeln($resp->isOk()
                    ? sprintf('  @%s: webhook deleted', $bot->getUsername())
                    : sprintf('  <error>@%s: %s</error>', $bot->getUsername(), $resp->getDescription()));
            } else {
                $url  = $base.'/bot/'.$bot->getWebhookToken().'/webhook';
                $resp = Request::setWebhook([
                    'url'             => $url,
                    'secret_token'    => $bot->getWebhookToken(),
                    'allowed_updates' => ['inline_query', 'chosen_inline_result'],
                ]);
                $io->writeln($resp->isOk()
                    ? sprintf('  @%s → %s', $bot->getUsername(), $url)
                    : sprintf('  <error>@%s: %s</error>', $bot->getUsername(), $resp->getDescription()));
            }

            if (!$resp->isOk()) {
                $failed++;
            }
        }

        return $failed ? Command::FAILURE : Command::SUCCESS;
    }
}
