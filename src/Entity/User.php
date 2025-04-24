<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: 'App\Repository\UserRepository')]
#[ORM\Table(name: 'user')]
#[ORM\UniqueConstraint(name: 'uniq_telegram', columns: ['telegram_id'])]
class User {

    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)] private ?string $username = null;

    #[ORM\Column(length: 255, nullable: true)] private ?string $firstName = null;

    #[ORM\Column(length: 255, nullable: true)] private ?string $lastName = null;

    #[ORM\Column(type: 'integer', nullable: true)] private ?int $telegramId = null;

    /* getters / setters */

    public function getId(): ?int {
        return $id;
    }

    public function getUsername(): ?string {
        return $this->username;
    }

    public function setUsername(?string $u): self {
        $this->username = $u;
        return $this;
    }

    public function getFirstName(): ?string {
        return $this->firstName;
    }

    public function setFirstName(?string $f): self {
        $this->firstName = $f;
        return $this;
    }

    public function getLastName(): ?string {
        return $this->lastName;
    }

    public function setLastName(?string $l): self {
        $this->lastName = $l;
        return $this;
    }

    public function getTelegramId(): ?int {
        return $this->telegramId;
    }

    public function setTelegramId(?int $id): self {
        $this->telegramId = $id;
        return $this;
    }
}
