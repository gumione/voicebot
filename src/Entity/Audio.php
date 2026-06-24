<?php

// src/Entity/Audio.php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: 'App\Repository\AudioRepository')]
#[ORM\Table(name: 'audio')]
#[ORM\Index(name: 'idx_audio_title_artist', columns: ['title', 'artist'], flags: ['fulltext'])]
class Audio {

    public const STATUS_PENDING = 'pending';
    public const STATUS_READY   = 'ready';
    public const STATUS_FAILED  = 'failed';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    /** Owning tenant. Nullable only so pre-multitenancy rows stay valid until migrated. */
    #[ORM\ManyToOne(targetEntity: Bot::class, inversedBy: 'audios')]
    #[ORM\JoinColumn(name: 'bot_id', nullable: true, onDelete: 'CASCADE')]
    private ?Bot $bot = null;

    /** Warming state: pending → ready (file_id set) | failed. */
    #[ORM\Column(length: 16, options: ['default' => 'pending'])]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(length: 255)]
    private string $artist;

    #[ORM\Column(length: 1024)]
    private string $path;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $tags = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $fileId = null;

    public function getId(): ?int {
        return $this->id;
    }

    public function getTitle(): string {
        return $this->title;
    }

    public function getArtist(): string {
        return $this->artist;
    }

    public function setTitle(string $t): self {
        $this->title = $t;
        return $this;
    }

    public function setArtist(string $a): self {
        $this->artist = $a;
        return $this;
    }

    public function getPath(): string {
        return $this->path;
    }

    public function setPath(string $p): self {
        $this->path = $p;
        return $this;
    }

    public function getTags(): ?string {
        return $this->tags;
    }

    public function setTags(?string $t): self {
        $this->tags = $t;
        return $this;
    }

    public function getFileId(): ?string {
        return $this->fileId;
    }

    public function setFileId(?string $id): self {
        $this->fileId = $id;
        return $this;
    }

    public function getBot(): ?Bot {
        return $this->bot;
    }

    public function setBot(?Bot $bot): self {
        $this->bot = $bot;
        return $this;
    }

    public function getStatus(): string {
        return $this->status;
    }

    public function setStatus(string $status): self {
        $this->status = $status;
        return $this;
    }
}
