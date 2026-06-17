<?php

declare(strict_types=1);

namespace App\Entity\Community;

use App\Entity\Auth\User;
use App\Entity\Team\Team;
use App\Repository\Community\MessageRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MessageRepository::class)]
#[ORM\Table(name: 'community_message')]
class Message
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User $senderUser;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User $receiverUser;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?Team $senderTeam = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?Team $receiverTeam = null;

    #[ORM\Column(length: 200)]
    private string $subject;

    #[ORM\Column(type: 'text')]
    private string $body;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $readAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $sentAt;

    #[ORM\Column(options: ['default' => false])]
    private bool $deletedBySender = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $deletedByReceiver = false;

    public function __construct()
    {
        $this->sentAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSenderUser(): User
    {
        return $this->senderUser;
    }

    public function setSenderUser(User $senderUser): static
    {
        $this->senderUser = $senderUser;

        return $this;
    }

    public function getReceiverUser(): User
    {
        return $this->receiverUser;
    }

    public function setReceiverUser(User $receiverUser): static
    {
        $this->receiverUser = $receiverUser;

        return $this;
    }

    public function getSenderTeam(): ?Team
    {
        return $this->senderTeam;
    }

    public function setSenderTeam(?Team $senderTeam): static
    {
        $this->senderTeam = $senderTeam;

        return $this;
    }

    public function getReceiverTeam(): ?Team
    {
        return $this->receiverTeam;
    }

    public function setReceiverTeam(?Team $receiverTeam): static
    {
        $this->receiverTeam = $receiverTeam;

        return $this;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): static
    {
        $this->subject = $subject;

        return $this;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function setBody(string $body): static
    {
        $this->body = $body;

        return $this;
    }

    public function getReadAt(): ?\DateTimeImmutable
    {
        return $this->readAt;
    }

    public function setReadAt(?\DateTimeImmutable $readAt): static
    {
        $this->readAt = $readAt;

        return $this;
    }

    public function getSentAt(): \DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function isDeletedBySender(): bool
    {
        return $this->deletedBySender;
    }

    public function setDeletedBySender(bool $deletedBySender): static
    {
        $this->deletedBySender = $deletedBySender;

        return $this;
    }

    public function isDeletedByReceiver(): bool
    {
        return $this->deletedByReceiver;
    }

    public function setDeletedByReceiver(bool $deletedByReceiver): static
    {
        $this->deletedByReceiver = $deletedByReceiver;

        return $this;
    }
}
