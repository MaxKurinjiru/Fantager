<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\Kingdom\KingdomRepository;
use App\Service\Calendar\KingdomTickRunnerService;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:ticks:status',
    description: 'Show tick pipeline status (counts, blocked tick, last completed) without changing state.',
)]
class TickStatusCommand extends Command
{
    public function __construct(
        private readonly KingdomRepository $kingdomRepository,
        private readonly KingdomTickRunnerService $tickRunnerService,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('kingdom-id', null, InputOption::VALUE_REQUIRED, 'Limit to one kingdom')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Print JSON instead of tables');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $kingdomId = $input->getOption('kingdom-id');
        $asJson = (bool) $input->getOption('json');

        if (null !== $kingdomId && '' !== $kingdomId) {
            $kingdom = $this->kingdomRepository->find((int) $kingdomId);
            if (null === $kingdom) {
                $io->error(sprintf('Kingdom %s not found.', (string) $kingdomId));

                return Command::FAILURE;
            }
            $kingdoms = [$kingdom];
        } else {
            $kingdoms = $this->kingdomRepository->findAll();
        }

        $snapshots = [];
        $hadBlocked = false;
        foreach ($kingdoms as $kingdom) {
            $snapshot = $this->tickRunnerService->getStatusSnapshot($kingdom);
            $snapshots[] = $snapshot;
            if (null !== $snapshot['blocked'] || [] !== $snapshot['failed']) {
                $hadBlocked = true;
            }
        }

        $queueDepth = $this->countMessengerMessages();
        $payload = [
            'kingdoms' => $snapshots,
            'messenger_async_count' => $queueDepth,
        ];

        if ($asJson) {
            $output->writeln((string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

            return $hadBlocked ? Command::FAILURE : Command::SUCCESS;
        }

        if ([] === $snapshots) {
            $io->warning('No kingdoms found.');

            return Command::SUCCESS;
        }

        foreach ($snapshots as $snapshot) {
            $io->title(sprintf('%s (ID: %s)', $snapshot['name'], (string) $snapshot['kingdom_id']));
            $counts = $snapshot['counts'];
            $io->definitionList(
                ['Pending' => (string) $counts['pending']],
                ['Dispatched' => (string) $counts['dispatched']],
                ['Processing' => (string) $counts['processing']],
                ['Completed' => (string) $counts['completed']],
                ['Failed' => (string) $counts['failed']],
                ['Latest completed' => null !== $snapshot['latest_completed_at']
                    ? sprintf('%s (%s)', $snapshot['latest_completed_at'], (string) $snapshot['latest_completed_type'])
                    : 'none'],
                ['Oldest pending' => $snapshot['oldest_pending_at'] ?? 'none'],
            );

            if (null !== $snapshot['blocked']) {
                $blocked = $snapshot['blocked'];
                $io->warning(sprintf(
                    'Blocked: tick %s (%s) status=%s scheduled=%s',
                    (string) $blocked['id'],
                    (string) $blocked['type'],
                    (string) $blocked['status'],
                    (string) $blocked['scheduled_at'],
                ));
            } else {
                $io->success('No blocked tick.');
            }
        }

        if (null === $queueDepth) {
            $io->note('Messenger queue table is not available.');
        } else {
            $io->text(sprintf('Messenger async messages: %d', $queueDepth));
        }

        return $hadBlocked ? Command::FAILURE : Command::SUCCESS;
    }

    private function countMessengerMessages(): ?int
    {
        try {
            $count = $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM messenger_messages');

            return false === $count ? 0 : (int) $count;
        } catch (DbalException) {
            return null;
        }
    }
}
