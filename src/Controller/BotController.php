<?php

// src/Controller/BotController.php

namespace App\Controller;

use App\Service\TelegramService;
use Doctrine\ORM\EntityManagerInterface;
use Longman\TelegramBot\Entities\InlineQuery\InlineQueryResultVoice;
use Longman\TelegramBot\Entities\InlineQuery\InlineQueryResultCachedVoice;
use Longman\TelegramBot\Entities\InlineQuery\InlineQueryResultArticle;
use Longman\TelegramBot\Entities\InputMessageContent\InputTextMessageContent;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\Entities\Update;
use Psr\Log\LoggerInterface;
use App\Repository\UserRepository;
use App\Repository\AudioRepository;
use App\Entity\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request as HttpRequest;

class BotController extends AbstractController {

    #[Route('/bot/index')]
    public function index(
            EntityManagerInterface $entityManager,
            LoggerInterface $logger,
            UserRepository $userRepository,
            AudioRepository $audioRepository,
            HttpRequest $request,
            TelegramService $telegramService
    ): JsonResponse {
        $logger->info('Incoming request: ' . json_encode($request->request->all()));
        $payload = json_decode($request->getContent(), true);

        try {
            $telegram = $telegramService;

            if (isset($payload['inline_query'])) {
                $logger->info('Received inline_query: ' . json_encode($payload['inline_query']));
                $update = new Update($payload);
                $inlineQuery = $update->getInlineQuery();
                $query = $inlineQuery->getQuery();
                $userId = $inlineQuery->getFrom()->getId();

                $user = $userRepository->findOneBy(['telegramId' => $userId]);
                $logger->info("Searching for user: {$userId}");
                if (!$user) {
                    $logger->info("No such user: {$userId}");
                    $user = new User();
                    $user->setTelegramId($userId);
                    $user->setUsername($inlineQuery->getFrom()->getUsername());
                    $user->setFirstName($inlineQuery->getFrom()->getFirstName());
                    $user->setLastName($inlineQuery->getFrom()->getLastName());

                    $em = $entityManager;
                    $em->persist($user);
                    $em->flush();
                }

                $logger->info("User exists: {$userId}");

                $offset = (int) $inlineQuery->getOffset(); // Get the offset parameter
                $limit = 50; // Limit results per page

                if (!empty($query)) {
                    $audioList = $audioRepository->findByTitleWithKeyword($query, $limit, $offset);
                    $totalCount = $audioRepository->countByTitleWithKeyword($query);
                } else {
                    // If the query is empty, return a list of all audio entries
                    $audioList = $audioRepository->findBy([], ['title' => 'ASC'], $limit, $offset);
                    $totalCount = $audioRepository->countAll();
                }

                // Generate the URL prefix for audio files
                $audioUrlPrefix = $request->getSchemeAndHttpHost() . '/';

                $results = [];
                foreach ($audioList as $audio) {
                    $fileId = $audio->getFileId();

                    if ($fileId === NULL) {
                        // Load the file through the absolute path on the server
                        $absoluteFilePath = $this->getParameter('kernel.project_dir') . '/public/' . $audio->getPath();

                        // Convert the audio file to OGG format with the OPUS codec
                        $outputFilePath = $absoluteFilePath . '.ogg';

                        // Check if the file already exists after conversion
						if (!file_exists($outputFilePath)) {
							$process = new Process(['ffmpeg', '-i', $absoluteFilePath, '-c:a', 'libopus', '-y', $outputFilePath]);
							$process->run();

							// Check the success of the conversion
							if (!$process->isSuccessful()) {
								throw new ProcessFailedException($process);
							}
						}

						// Send the voice message and get the file_id
						$sendVoiceResponse = Request::sendVoice([
									'chat_id' => $userId,
									'voice' => Request::encodeFile($outputFilePath),
									'disable_notification' => true,
						]);

						// Save the file_id in the database if the sending is successful
						if ($sendVoiceResponse->isOk()) {
							$fileId = $sendVoiceResponse->getResult()->getVoice()->getFileId();
							$audio->setFileId($fileId);
							$entityManager->persist($audio);
							$entityManager->flush();
						} else {
							$logger->error("Error sending voice message: " . $sendVoiceResponse->getDescription());
							continue;
						}

						// Delete the sent voice message
						Request::deleteMessage([
							'chat_id' => $userId,
							'message_id' => $sendVoiceResponse->getResult()->getMessageId(),
						]);
                    }

                    $results[] = new InlineQueryResultCachedVoice([
                        'id' => $audio->getId(),
                        'voice_file_id' => $fileId,
                        'title' => $audio->getTitle(),
                    ]);
                }

                $nextOffset = ($offset + count($results) < $totalCount) ? $offset + $limit : '';

                $data = [
                    'inline_query_id' => $inlineQuery->getId(),
                    'results' => $results,
                    'next_offset' => (string) ($offset + $limit), // offset for the next page
                ];

                $response = Request::answerInlineQuery($data);

                $logger->info("Answered inline_query with " . count($results) . " results");
            }
        } catch (Longman\TelegramBot\Exception\TelegramException $e) {
            $logger->error("Error: " . $e->getMessage());
        }

        return new JsonResponse(['status' => 'ok']);
    }

}
