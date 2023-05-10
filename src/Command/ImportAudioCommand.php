<?php

namespace App\Command;

use App\Entity\Audio;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

class ImportAudioCommand extends Command {

    protected static $defaultName = 'app:import-audio';
    private $entityManager;
    private $projectDir;

    public function __construct(EntityManagerInterface $entityManager, string $projectDir) {
        $this->entityManager = $entityManager;
        $this->projectDir = $projectDir;
        parent::__construct();
    }

    // ...

    protected function execute(InputInterface $input, OutputInterface $output) {
        // Configure the Finder object to search for audio files in the specified directory
		$finder = new Finder();
		$finder->files()->in($audioDirectory)->name('/\.(mp3|wav|ogg|m4a)$/i');

		// Loop through the found files and add them to the database
		foreach ($finder as $file) {
			// Get the file name without extension
			$filenameWithoutExtension = pathinfo($file->getFilename(), PATHINFO_FILENAME);

			// Check if there's already an audio entry with that file name in the database
			$existingAudio = $this->entityManager->getRepository(Audio::class)->findOneBy(['title' => $filenameWithoutExtension]);

			if (!$existingAudio) {
				// Create a new audio entry and set its properties
				$audio = new Audio();
				$audio->setTitle($filenameWithoutExtension);

				// Get the relative path to the file
				$relativePath = 'audio/' . $file->getFilename();
				$audio->setPath($relativePath);

				// Add the audio entry to the database
				$this->entityManager->persist($audio);
			}
		}

		// Save changes to the database
        $this->entityManager->flush();

        $output->writeln('Audio files imported successfully!');

        return Command::SUCCESS;
    }

}
