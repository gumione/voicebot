<?php
namespace App\Service\Telegram;

use App\Entity\Audio;
use Doctrine\ORM\EntityManagerInterface;
use Longman\TelegramBot\Request;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

final class VoiceSender
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly string                 $publicDir,
        private readonly TelegramService        $telegram,   // ⬅️ получаем Telegram-инстанс
        private readonly LoggerInterface        $ffmpegLogger,
    ) {
        /** регистрируем Telegram-объект один раз  */
        Request::initialize($this->telegram);
    }

    /** Возвращает file_id (из кеша или после загрузки через sendAudio) */
    public function uploadAndGetFileId(Audio $audio, int $chatId): string
    {
        $src = $this->publicDir.'/'.ltrim($audio->getPath(), '/');
        $dst = $src.'.ogg';

        if (!file_exists($dst)) {
            $this->convertToOpus($src, $dst);
        }

        /* Загружаем как AUDIO, чтобы в списке было две строки
           (OGG/opus ≤60 сек Telegram всё-равно отрисует кружком) */
        $resp = Request::sendAudio([
            'chat_id'   => $chatId,
            'audio'     => Request::encodeFile($dst),
            'title'     => $audio->getTitle(),
            'performer' => $audio->getArtist(),
            'disable_notification' => true,
        ]);
        
        if (!$resp->isOk()) {
            throw new \RuntimeException('Telegram error: '.$resp->getDescription());
        }
        
        $result = $resp->getResult();
        
        /** -------------- главная правка ---------------- */
        $fileId = null;
        
        if ($result->getAudio()) {               // если Telegram вернул «audio»
            $fileId = $result->getAudio()->getFileId();
        } elseif ($result->getVoice()) {         // если превратил в «voice»
            $fileId = $result->getVoice()->getFileId();
        }
        
        if (!$fileId) {
            throw new \RuntimeException('Cannot obtain file_id from sendAudio result');
        }
        /** ---------------------------------------------- */
        
        $audio->setFileId($fileId);
        $this->em->flush();
        
        /* удаляем тех-сообщение */
        Request::deleteMessage([
            'chat_id'    => $chatId,
            'message_id' => $result->getMessageId(),
        ]);
        
        return $fileId;
    }

    private function convertToOpus(string $src, string $dst): void
    {
        $cmd = ['ffmpeg', '-i', $src, '-c:a', 'libopus', '-y', $dst];
        $proc = new Process($cmd);
        $proc->setTimeout(30)->run();

        $this->ffmpegLogger->debug('ffmpeg', [
            'cmd'  => implode(' ', $cmd),
            'exit' => $proc->getExitCode(),
            'err'  => $proc->getErrorOutput(),
        ]);

        if (!$proc->isSuccessful()) {
            throw new ProcessFailedException($proc);
        }
    }
}
