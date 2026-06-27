<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Entity\Auth\User;
use App\Entity\Kingdom\Kingdom;
use App\Repository\Auth\UserRepository;
use App\Repository\Team\TeamRepository;
use App\Service\TeamChronicle\TeamChronicleService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class TestUserService
{
    /** @var list<array{email: string, nickname: string, password: string}> */
    public const DEFAULT_TEST_USERS = [
        ['email' => 'player1@example.com', 'nickname' => 'Test Player 1', 'password' => 'password'],
        ['email' => 'player2@example.com', 'nickname' => 'Test Player 2', 'password' => 'password'],
        ['email' => 'player3@example.com', 'nickname' => 'Test Player 3', 'password' => 'password'],
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly UserRepository $userRepository,
        private readonly TeamRepository $teamRepository,
        private readonly SlugGenerator $slugGenerator,
        private readonly TeamChronicleService $teamChronicleService,
        private readonly UserSettingsService $userSettingsService,
    ) {
    }

    /**
     * @return list<User>
     *
     * @throws \DomainException
     */
    public function createDefaultTestUsers(Kingdom $kingdom): array
    {
        $users = [];
        foreach (self::DEFAULT_TEST_USERS as $definition) {
            $users[] = $this->createTestUser(
                $kingdom,
                $definition['email'],
                $definition['nickname'],
                $definition['password'],
            );
            $this->entityManager->flush();
        }

        return $users;
    }

    /**
     * @throws \DomainException
     */
    public function createTestUser(Kingdom $kingdom, string $email, string $nickname, string $password): User
    {
        $email = strtolower(trim($email));
        $nickname = trim($nickname);

        if ($this->userRepository->findOneBy(['email' => $email])) {
            throw new \DomainException(sprintf('User with email "%s" already exists.', $email));
        }

        $slug = $this->slugGenerator->generate($nickname);
        if ($this->userRepository->findOneBy(['displayNameSlug' => $slug])) {
            throw new \DomainException(sprintf('User with display name slug "%s" already exists (derived from "%s").', $slug, $nickname));
        }

        $team = $this->teamRepository->findAvailableNpcTeam((int) $kingdom->getId());
        if (!$team) {
            throw new \DomainException(sprintf('No available NPC teams found in Kingdom "%s" (id=%d).', $kingdom->getName(), $kingdom->getId()));
        }

        $user = new User();
        $user->setEmail($email);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $user->setDisplayName($nickname);
        $user->setDisplayNameSlug($slug);
        $user->setKingdom($kingdom);
        $user->setLocale($kingdom->getLanguage());
        $user->setIsVerified(true);
        $user->setRoles(['ROLE_TEST']);

        $this->entityManager->persist($user);
        $this->userSettingsService->getOrCreate($user);

        $team->setUser($user);
        $team->setIsNpc(false);
        $this->teamChronicleService->recordPlayerJoined($team, $user);

        return $user;
    }
}
