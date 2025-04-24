<?php
// src/Command/ImportAudioCommand.php

namespace App\Command;

use App\Entity\Audio;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

#[AsCommand(
    name: 'app:import-audio',
    description: 'Scan public/audio/** and import (artist = folder name)',
)]
final class ImportAudioCommand extends \Symfony\Component\Console\Command\Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly string                 $projectDir,
    ) { parent::__construct(); }

    protected function execute(InputInterface $in, OutputInterface $out): int
    {
        $dir = $this->projectDir . '/public/audio';
        $finder = (new Finder())->files()->in($dir)->name('/\.(mp3|wav|ogg|m4a)$/i');

        $progress = new ProgressBar($out, $finder->count());
        foreach ($finder as $file) {
            $progress->advance();

            $artist = basename($file->getRelativePath()) ?: 'Unknown';
            $title  = pathinfo($file->getFilename(), PATHINFO_FILENAME);

            $exist = $this->em->getRepository(Audio::class)
                      ->findOneBy(['title' => $title, 'artist' => $artist]);
            if ($exist) {
                continue;
            }

            $audio = (new Audio())
                ->setTitle($title)
                ->setArtist($artist)
                ->setPath('audio/' . $file->getRelativePathname());

            $this->em->persist($audio);

            if (($progress->getProgress() % 100) === 0) {
                $this->em->flush();
            }
        }
        $this->em->flush();
        $progress->finish();
        $out->writeln("\nDone!");

        return self::SUCCESS;
    }
}
