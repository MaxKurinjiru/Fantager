<?php

declare(strict_types=1);

namespace App\Entity\Notification;

use App\Entity\Auth\User;
use App\Enum\NotificationType;
use App\Repository\Notification\NotificationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NotificationRepository::class)]
#[ORM\Table(name: 'notification')]
#[ORM\Index(name: 'IDX_USER_READ', columns: ['user_id', 'is_read'])]
class Notification
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\Column(length: 25, enumType: NotificationType::class)]
    private NotificationType $type;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $titleKey = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $bodyKey = null;

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    private array $titleParams = [];

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    private array $bodyParams = [];

    /** Legacy write-time string; null on rows written with keys (A6.4). */
    #[ORM\Column(length: 200, nullable: true)]
    private ?string $title = null;

    /** Legacy write-time string; null on rows written with keys (A6.4). */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $body = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $isRead = false;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getType(): NotificationType
    {
        return $this->type;
    }

    public function setType(NotificationType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getTitleKey(): ?string
    {
        return $this->titleKey;
    }

    public function setTitleKey(?string $titleKey): static
    {
        $this->titleKey = $titleKey;

        return $this;
    }

    public function getBodyKey(): ?string
    {
        return $this->bodyKey;
    }

    public function setBodyKey(?string $bodyKey): static
    {
        $this->bodyKey = $bodyKey;

        return $this;
    }

    /** @return array<string, mixed> */
    public function getTitleParams(): array
    {
        return $this->titleParams;
    }

    /** @param array<string, mixed> $titleParams */
    public function setTitleParams(array $titleParams): static
    {
        $this->titleParams = $titleParams;

        return $this;
    }

    /** @return array<string, mixed> */
    public function getBodyParams(): array
    {
        return $this->bodyParams;
    }

    /** @param array<string, mixed> $bodyParams */
    public function setBodyParams(array $bodyParams): static
    {
        $this->bodyParams = $bodyParams;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getBody(): ?string
    {
        return $this->body;
    }

    public function setBody(?string $body): static
    {
        $this->body = $body;

        return $this;
    }

    public function isRead(): bool
    {
        return $this->isRead;
    }

    public function setIsRead(bool $isRead): static
    {
        $this->isRead = $isRead;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
