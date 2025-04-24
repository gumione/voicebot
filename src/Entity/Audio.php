<?php

// src/Entity/Audio.php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: 'App\Repository\AudioRepository')]
#[ORM\Table(name: 'audio')]
class Audio {

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(length: 255)]
    private string $artist;

    #[ORM\Column(type: 'text')]
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
}
