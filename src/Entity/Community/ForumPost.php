<?php

declare(strict_types=1);

namespace App\Entity\Community;

use App\Entity\Team\Team;
use App\Repository\Community\ForumPostRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ForumPostRepository::class)]
#[ORM\Table(name: 'community_forum_post')]
class ForumPost
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'posts')]
    #[ORM\JoinColumn(nullable: false)]
    private ForumThread $thread;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Team $authorTeam;

    #[ORM\Column(type: 'text')]
    private string $body;

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

    public function getThread(): ForumThread
    {
        return $this->thread;
    }

    public function setThread(ForumThread $thread): static
    {
        $this->thread = $thread;

        return $this;
    }

    public function getAuthorTeam(): Team
    {
        return $this->authorTeam;
    }

    public function setAuthorTeam(Team $authorTeam): static
    {
        $this->authorTeam = $authorTeam;

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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
