<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\Kingdom\KingdomRepository;
use App\Service\Auth\TestUserService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:user:create-test',
    description: 'Create and activate a test user with a team assigned in a given kingdom',
)]
class CreateTestUserCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly KingdomRepository $kingdomRepository,
        private readonly TestUserService $testUserService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('kingdom', InputArgument::REQUIRED, 'ID or Name of the Kingdom')
            ->addOption('default', null, InputOption::VALUE_NONE, 'Create the 3 default test users (player1–3@example.com / password)')
            ->addArgument('email', InputArgument::OPTIONAL, 'User email address')
            ->addArgument('nickname', InputArgument::OPTIONAL, 'User display name / nickname')
            ->addArgument('password', InputArgument::OPTIONAL, 'User password');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $kingdomVal = $input->getArgument('kingdom');
        $isDefault = (bool) $input->getOption('default');

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

        if ($isDefault) {
            try {
                $users = $this->testUserService->createDefaultTestUsers($kingdom);
            } catch (\DomainException $e) {
                $io->error($e->getMessage());

                return Command::FAILURE;
            }

            $io->success(sprintf(
                'Successfully created and activated %d default test users: %s',
                count($users),
                implode(', ', array_map(static fn ($user) => $user->getEmail(), $users)),
            ));

            return Command::SUCCESS;
        }

        $emailOpt = $input->getArgument('email');
        $nicknameOpt = $input->getArgument('nickname');
        $passwordOpt = $input->getArgument('password');

        if (null === $emailOpt || null === $nicknameOpt || null === $passwordOpt) {
            $io->error('To create a custom test user, you must provide email, nickname, and password arguments. Alternatively, use the --default option.');

            return Command::FAILURE;
        }

        $email = strtolower(trim((string) $emailOpt));
        $nickname = trim((string) $nicknameOpt);
        $password = (string) $passwordOpt;

        try {
            $user = $this->testUserService->createTestUser($kingdom, $email, $nickname, $password);
            $this->entityManager->flush();
        } catch (\DomainException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $team = $user->getTeam();
        if (!$team) {
            $io->error('Test user was created but no team was assigned.');

            return Command::FAILURE;
        }

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
