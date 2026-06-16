<?php

declare(strict_types=1);

namespace App\Entity\Auth;

use App\Entity\Team\Team;
use App\Repository\Auth\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'auth_user')]
#[ORM\UniqueConstraint(name: 'UNIQ_EMAIL', columns: ['email'])]
#[ORM\UniqueConstraint(name: 'UNIQ_DISPLAY_NAME_SLUG', columns: ['display_name_slug'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private string $email;

    #[ORM\Column(name: 'password_hash')]
    private string $password;

    #[ORM\Column]
    private bool $isVerified = false;

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $roles = [];

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?\App\Entity\Kingdom\Kingdom $kingdom = null;

    #[ORM\Column(length: 5, options: ['default' => 'cs'])]
    private string $locale = 'cs';

    #[ORM\Column(length: 50)]
    private string $displayName;

    #[ORM\Column(length: 60)]
    private string $displayNameSlug;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\OneToOne(mappedBy: 'user')]
    private ?Team $team = null;

    #[ORM\OneToOne(mappedBy: 'user', cascade: ['persist', 'remove'])]
    private ?UserSettings $settings = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $teamReassignmentAvailableAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastActivityAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $inactiveWarningSentAt = null;

    public function __construct()
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->createdAt = $now;
        $this->lastActivityAt = $now;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_PLAYER';

        return array_unique($roles);
    }

    /** @param list<string> $roles */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function eraseCredentials(): void
    {
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): static
    {
        $this->isVerified = $isVerified;

        return $this;
    }

    public function getKingdom(): ?\App\Entity\Kingdom\Kingdom
    {
        return $this->kingdom;
    }

    public function setKingdom(?\App\Entity\Kingdom\Kingdom $kingdom): static
    {
        $this->kingdom = $kingdom;

        return $this;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    public function setDisplayName(string $displayName): static
    {
        $this->displayName = $displayName;

        return $this;
    }

    public function getDisplayNameSlug(): string
    {
        return $this->displayNameSlug;
    }

    public function setDisplayNameSlug(string $displayNameSlug): static
    {
        $this->displayNameSlug = $displayNameSlug;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getTeam(): ?Team
    {
        return $this->team;
    }

    public function setTeam(?Team $team): static
    {
        $this->team = $team;

        return $this;
    }

    public function getTeamReassignmentAvailableAt(): ?\DateTimeImmutable
    {
        return $this->teamReassignmentAvailableAt;
    }

    public function setTeamReassignmentAvailableAt(?\DateTimeImmutable $teamReassignmentAvailableAt): static
    {
        $this->teamReassignmentAvailableAt = $teamReassignmentAvailableAt;

        return $this;
    }

    public function getLastActivityAt(): ?\DateTimeImmutable
    {
        return $this->lastActivityAt;
    }

    public function setLastActivityAt(?\DateTimeImmutable $lastActivityAt): static
    {
        $this->lastActivityAt = $lastActivityAt;

        return $this;
    }

    public function getInactiveWarningSentAt(): ?\DateTimeImmutable
    {
        return $this->inactiveWarningSentAt;
    }

    public function setInactiveWarningSentAt(?\DateTimeImmutable $inactiveWarningSentAt): static
    {
        $this->inactiveWarningSentAt = $inactiveWarningSentAt;

        return $this;
    }

    public function getSettings(): ?UserSettings
    {
        return $this->settings;
    }

    public function setSettings(?UserSettings $settings): static
    {
        $this->settings = $settings;

        return $this;
    }

    public function isCloseModalOnBackdrop(): bool
    {
        return $this->settings?->isCloseModalOnBackdrop() ?? false;
    }
}
