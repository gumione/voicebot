<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table(name="audio", indexes={@ORM\Index(columns={"title"}, flags={"fulltext"})})
 * @ORM\Entity(repositoryClass="App\Repository\AudioRepository")
 */
class Audio
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $title;

    /**
     * @ORM\Column(type="text")
     */
    private $path;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $tags;
    
    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $file_id;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function setPath(string $path): self
    {
        $this->path = $path;

        return $this;
    }

    public function getTags(): ?string
    {
        return $this->tags;
    }

    public function setTags(?string $tags): self
    {
        $this->tags = $tags;

        return $this;
    }
    
    public function getFileId(): ?string
    {
        return $this->file_id;
    }

    public function setFileId(?string $file_id): self
    {
        $this->file_id = $file_id;

        return $this;
    }
}
