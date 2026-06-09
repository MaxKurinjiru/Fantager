<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Auth\User;
use App\Repository\Auth\UserRepository;
use App\Repository\Kingdom\KingdomRepository;
use App\Repository\Team\TeamRepository;
use App\Service\Auth\SlugGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:user:create-test',
    description: 'Create and activate a test user with a team assigned in a given kingdom',
)]
class CreateTestUserCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly KingdomRepository $kingdomRepository,
        private readonly UserRepository $userRepository,
        private readonly TeamRepository $teamRepository,
        private readonly SlugGenerator $slugGenerator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('kingdom', InputArgument::REQUIRED, 'ID or Name of the Kingdom')
            ->addArgument('email', InputArgument::REQUIRED, 'User email address')
            ->addArgument('nickname', InputArgument::REQUIRED, 'User display name / nickname')
            ->addArgument('password', InputArgument::REQUIRED, 'User password');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $kingdomVal = $input->getArgument('kingdom');
        $email = strtolower(trim((string) $input->getArgument('email')));
        $nickname = trim((string) $input->getArgument('nickname'));
        $password = (string) $input->getArgument('password');

        // Look up kingdom by ID or by Name
        $kingdom = null;
        if (is_numeric($kingdomVal)) {
            $kingdom = $this->kingdomRepository->find((int) $kingdomVal);
        }
        if (!$kingdom) {
            $kingdom = $this->kingdomRepository->findOneBy(['name' => $kingdomVal]);
        }

        if (!$kingdom) {
            $io->error(sprintf('Kingdom "%s" not found.', $kingdomVal));

            return Command::FAILURE;
        }

        // Check if user already exists by email
        if ($this->userRepository->findOneBy(['email' => $email])) {
            $io->error(sprintf('User with email "%s" already exists.', $email));

            return Command::FAILURE;
        }

        // Generate and check display name slug
        $slug = $this->slugGenerator->generate($nickname);
        if ($this->userRepository->findOneBy(['displayNameSlug' => $slug])) {
            $io->error(sprintf('User with display name slug "%s" already exists (derived from "%s").', $slug, $nickname));

            return Command::FAILURE;
        }

        // Find available NPC team to assign
        $team = $this->teamRepository->findAvailableNpcTeam((int) $kingdom->getId());
        if (!$team) {
            $io->error(sprintf('No available NPC teams found in Kingdom "%s" (id=%d).', $kingdom->getName(), $kingdom->getId()));

            return Command::FAILURE;
        }

        // Create the user
        $user = new User();
        $user->setEmail($email);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $user->setDisplayName($nickname);
        $user->setDisplayNameSlug($slug);
        $user->setKingdom($kingdom);
        $user->setLocale($kingdom->getLanguage());
        $user->setIsVerified(true);
        $user->setRoles([]);

        $this->entityManager->persist($user);

        // Assign the team
        $team->setUser($user);
        $team->setIsNpc(false); // Once a player takes over, it is no longer an NPC team

        $this->entityManager->flush();

        $io->success(sprintf(
            'User "%s" (%s) successfully created, activated and assigned to team "%s" in Kingdom "%s".',
            $nickname,
            $email,
            $team->getName(),
            $kingdom->getName()
        ));

        return Command::SUCCESS;
    }
}
