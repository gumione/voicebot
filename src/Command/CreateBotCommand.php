<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Bot;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:bot:create', description: 'Create a Bot tenant (generates a webhook token)')]
final class CreateBotCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('name', null, InputOption::VALUE_REQUIRED, 'Display name')
             ->addOption('username', null, InputOption::VALUE_REQUIRED, 'Bot username (without @)')
             ->addOption('token', null, InputOption::VALUE_REQUIRED, 'Telegram Bot API token')
             ->addOption('storage-chat', null, InputOption::VALUE_OPTIONAL, 'Storage chat/channel id');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $username = (string) ($input->getOption('username') ?? '');
        $token    = (string) ($input->getOption('token') ?? '');
        if ($username === '' || $token === '') {
            $io->error('--username and --token are required.');
            return Command::FAILURE;
        }

        $bot = (new Bot())
            ->setName((string) ($input->getOption('name') ?: $username))
            ->setUsername($username)
            ->setToken($token)
            ->setStorageChatId($input->getOption('storage-chat'))
            ->setWebhookToken(bin2hex(random_bytes(24)));

        $this->em->persist($bot);
        $this->em->flush();

        $io->success(sprintf('Bot #%d "%s" created.', $bot->getId(), $bot->getUsername()));
        $io->writeln('Webhook path: <info>/bot/'.$bot->getWebhookToken().'/webhook</info>');
        $io->writeln('Register it: setWebhook?url=https://HOST/bot/'.$bot->getWebhookToken().'/webhook&secret_token='.$bot->getWebhookToken());

        return Command::SUCCESS;
    }
}
