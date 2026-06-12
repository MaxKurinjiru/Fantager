<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Kingdom\KingdomTickLog;
use App\Enum\TickType;
use App\Message\ProcessKingdomTicksMessage;
use App\Repository\Kingdom\KingdomRepository;
use App\Repository\Kingdom\KingdomTickLogRepository;
use App\Repository\League\LeagueSeasonRepository;
use App\Service\Calendar\TickScheduleCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:ticks:run',
    description: 'Check and schedule pending server ticks for all active kingdoms',
)]
class ProcessTicksCommand extends Command
{
    public function __construct(
        private readonly KingdomRepository $kingdomRepository,
        private readonly KingdomTickLogRepository $tickLogRepository,
        private readonly LeagueSeasonRepository $seasonRepository,
        private readonly TickScheduleCalculator $scheduleCalculator,
        private readonly EntityManagerInterface $em,
        private readonly MessageBusInterface $messageBus,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('time', null, InputOption::VALUE_OPTIONAL, 'Override check execution time (e.g. "2026-06-08 19:30:00")')
             ->addOption('kingdom-id', null, InputOption::VALUE_OPTIONAL, 'Run only for a specific Kingdom ID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $timeStr = $input->getOption('time');
        $kingdomIdOpt = $input->getOption('kingdom-id');

        if (null !== $timeStr) {
            try {
                $now = new \DateTimeImmutable((string) $timeStr, new \DateTimeZone('UTC'));
            } catch (\Exception $e) {
                $io->error(sprintf('Invalid time format: "%s". Please use standard Y-m-d H:i:s.', $timeStr));

                return Command::FAILURE;
            }
        } else {
            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        }

        // Fetch Kingdoms
        if (null !== $kingdomIdOpt) {
            $kingdoms = $this->kingdomRepository->findBy(['id' => (int) $kingdomIdOpt]);
            if (empty($kingdoms)) {
                $io->error(sprintf('Kingdom with ID %s not found.', $kingdomIdOpt));

                return Command::FAILURE;
            }
        } else {
            $kingdoms = $this->kingdomRepository->findAll();
        }

        $io->section(sprintf('Processing ticks at %s UTC...', $now->format('Y-m-d H:i:s')));

        foreach ($kingdoms as $kingdom) {
            /** @var \App\Entity\Kingdom\Kingdom $kingdom */
            $kingdomId = $kingdom->getId();
            if (null === $kingdomId) {
                continue;
            }

            $io->text(sprintf('Checking Kingdom: %s (ID: %d)', $kingdom->getName(), $kingdomId));

            // Check if this kingdom has any blocked ticks (processing or failed)
            $blocked = $this->tickLogRepository->findOneBy([
                'kingdom' => $kingdom,
                'status' => ['processing', 'failed'],
            ]);

            if (null !== $blocked) {
                $io->warning(sprintf('Kingdom ID %d has a blocked tick (ID: %d, type: %s, status: %s). Skipping scheduling.', $kingdomId, $blocked->getId(), $blocked->getTickType()->value, $blocked->getStatus()));
                continue;
            }

            $ticksDispatched = 0;

            /** @var \App\Entity\League\LeagueSeason|null $season */
            $season = $this->seasonRepository->findOneBy([
                'kingdom' => $kingdom,
                'status' => \App\Enum\LeagueSeasonStatus::Active,
            ]);
            if (null === $season) {
                /** @var \App\Entity\League\LeagueSeason|null $season */
                $season = $this->seasonRepository->findOneBy(['kingdom' => $kingdom], ['seasonNumber' => 'DESC']);
            }
            $seasonStartDate = $season?->getStartDate();

            // Generate occurrences for each TickType
            foreach (TickType::cases() as $tickType) {
                $latestTime = $this->tickLogRepository->getLatestCompletedTickTime($kingdom, $tickType);

                if (null === $latestTime) {
                    // Fallback to season start date
                    if (null !== $seasonStartDate) {
                        // Get the season start date at 00:00:00 local time
                        try {
                            $tz = new \DateTimeZone($kingdom->getTimezone());
                        } catch (\Exception) {
                            $tz = new \DateTimeZone('UTC');
                        }
                        $latestTime = new \DateTimeImmutable($seasonStartDate->format('Y-m-d').' 00:00:00', $tz);
                        $latestTime = $latestTime->setTimezone(new \DateTimeZone('UTC'));
                    } else {
                        // Final fallback to 24 hours ago
                        $latestTime = $now->modify('-24 hours');
                    }
                }

                // Calculate occurrences between $latestTime (exclusive) and $now (inclusive)
                $occurrences = $this->scheduleCalculator->generateOccurrences($latestTime, $now, $kingdom->getTimezone(), $seasonStartDate);

                foreach ($occurrences as $occurrence) {
                    $scheduledAt = $occurrence['time'];

                    // Double check if log already exists
                    $existingLog = $this->tickLogRepository->findOneBy([
                        'kingdom' => $kingdom,
                        'tickType' => $tickType,
                        'scheduledAt' => $scheduledAt,
                    ]);

                    if (null === $existingLog) {
                        $log = new KingdomTickLog();
                        $log->setKingdom($kingdom);
                        $log->setTickType($tickType);
                        $log->setScheduledAt($scheduledAt);
                        $log->setStatus('processing');

                        $this->em->persist($log);
                        ++$ticksDispatched;
                    }
                }
            }

            if ($ticksDispatched > 0) {
                $this->em->flush();
                $io->success(sprintf('Dispatched %d new ticks for Kingdom %s.', $ticksDispatched, $kingdom->getName()));

                // Dispatch single cron processor message for the Kingdom
                $this->messageBus->dispatch(new ProcessKingdomTicksMessage($kingdomId));
            } else {
                $io->text(sprintf('Kingdom %s is up-to-date.', $kingdom->getName()));
            }
        }

        return Command::SUCCESS;
    }
}
