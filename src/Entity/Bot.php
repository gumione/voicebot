<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\BotRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A tenant: one Telegram bot with its own credentials and isolated catalog.
 */
#[ORM\Entity(repositoryClass: BotRepository::class)]
#[ORM\Table(name: 'bot')]
#[ORM\UniqueConstraint(name: 'uniq_bot_webhook_token', columns: ['webhook_token'])]
#[ORM\UniqueConstraint(name: 'uniq_bot_username', columns: ['username'])]
class Bot
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name = '';

    /** Bot username without @ (e.g. "vlogkntntbot"). */
    #[ORM\Column(length: 255)]
    private string $username = '';

    /** Telegram Bot API token (sensitive — DB only, never in VCS). */
    #[ORM\Column(length: 255)]
    private string $token = '';

    /** Chat/channel id this bot uploads to once, to mint reusable file_ids. */
    #[ORM\Column(type: Types::BIGINT, nullable: true)]
    private ?string $storageChatId = null;

    /** Opaque random token: routes the webhook (/bot/{token}/webhook) AND authenticates it. */
    #[ORM\Column(length: 64)]
    private string $webhookToken = '';

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, Audio> */
    #[ORM\OneToMany(targetEntity: Audio::class, mappedBy: 'bot')]
    private Collection $audios;

    public function __construct()
    {
        $this->audios = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getName(): string { return $this->name; }
    public function setName(string $v): self { $this->name = $v; return $this; }

    public function getUsername(): string { return $this->username; }
    public function setUsername(string $v): self { $this->username = ltrim($v, '@'); return $this; }

    public function getToken(): string { return $this->token; }
    public function setToken(string $v): self { $this->token = $v; return $this; }

    public function getStorageChatId(): ?string { return $this->storageChatId; }
    public function setStorageChatId(int|string|null $v): self
    {
        $this->storageChatId = $v === null || $v === '' ? null : (string) $v;
        return $this;
    }

    public function getWebhookToken(): string { return $this->webhookToken; }
    public function setWebhookToken(string $v): self { $this->webhookToken = $v; return $this; }

    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $v): self { $this->isActive = $v; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    /** @return Collection<int, Audio> */
    public function getAudios(): Collection { return $this->audios; }
}
