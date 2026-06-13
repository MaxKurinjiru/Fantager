<?php

declare(strict_types=1);

namespace App\Entity\Community;

use App\Entity\Kingdom\Kingdom;
use App\Entity\Team\Team;
use App\Repository\Community\ForumThreadRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ForumThreadRepository::class)]
#[ORM\Table(name: 'community_forum_thread')]
class ForumThread
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Kingdom $kingdom;

    #[ORM\Column(length: 100)]
    private string $category;

    #[ORM\Column(length: 200)]
    private string $title;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Team $authorTeam;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(options: ['default' => false])]
    private bool $isPinned = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $isLocked = false;

    /** @var Collection<int, ForumPost> */
    #[ORM\OneToMany(targetEntity: ForumPost::class, mappedBy: 'thread', cascade: ['persist'])]
    private Collection $posts;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->posts = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getKingdom(): Kingdom
    {
        return $this->kingdom;
    }

    public function setKingdom(Kingdom $kingdom): static
    {
        $this->kingdom = $kingdom;

        return $this;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function setCategory(string $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isPinned(): bool
    {
        return $this->isPinned;
    }

    public function setIsPinned(bool $isPinned): static
    {
        $this->isPinned = $isPinned;

        return $this;
    }

    /** @return Collection<int, ForumPost> */
    public function getPosts(): Collection
    {
        return $this->posts;
    }

    public function isLocked(): bool
    {
        return $this->isLocked;
    }

    public function setIsLocked(bool $isLocked): static
    {
        $this->isLocked = $isLocked;

        return $this;
    }
}
