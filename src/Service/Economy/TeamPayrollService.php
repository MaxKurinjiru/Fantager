<?php

declare(strict_types=1);

namespace App\Service\Economy;

use App\Entity\Kingdom\Kingdom;
use App\Entity\Team\Team;
use App\Enum\FinancialRecordActor;
use App\Enum\FinancialRecordType;
use App\Repository\Hero\HeroRepository;
use App\Service\Hero\HeroSalaryService;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Weekly hero and trainer payroll charged on the weekly_reset tick.
 */
class TeamPayrollService
{
    public function __construct(
        private readonly HeroRepository $heroRepository,
        private readonly HeroSalaryService $heroSalaryService,
        private readonly EconomyService $economyService,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * @return array{
     *     heroes_due: int,
     *     trainers_due: int,
     *     total: int,
     *     hero_count: int,
     *     trainer_count: int,
     * }
     */
    public function calculateWeeklyPayrollBreakdown(Team $team): array
    {
        $heroesDue = 0;
        $trainersDue = 0;
        $heroCount = 0;
        $trainerCount = 0;

        foreach ($this->heroRepository->findPayrollEligibleByTeam($team) as $hero) {
            $salary = $this->heroSalaryService->calculateWeeklySalary($hero);
            if ($salary <= 0) {
                continue;
            }

            if ($hero->isTrainer()) {
                $trainersDue += $salary;
                ++$trainerCount;
            } else {
                $heroesDue += $salary;
                ++$heroCount;
            }
        }

        return [
            'heroes_due' => $heroesDue,
            'trainers_due' => $trainersDue,
            'total' => $heroesDue + $trainersDue,
            'hero_count' => $heroCount,
            'trainer_count' => $trainerCount,
        ];
    }

    public function calculateWeeklyPayrollFee(Team $team): int
    {
        return $this->calculateWeeklyPayrollBreakdown($team)['total'];
    }

    public function processPayrollTick(Kingdom $kingdom, ?Team $team = null): void
    {
        if (null !== $team) {
            $this->processPayrollForTeam($team);

            $this->em->flush();

            return;
        }

        $teams = $this->em->getRepository(Team::class)->findBy(['kingdom' => $kingdom]);
        foreach ($teams as $kingdomTeam) {
            $this->processPayrollForTeam($kingdomTeam);
        }

        $this->em->flush();
    }

    private function processPayrollForTeam(Team $team): void
    {
        $breakdown = $this->calculateWeeklyPayrollBreakdown($team);
        if ($breakdown['total'] <= 0) {
            return;
        }

        $this->settlePayrollPortion(
            $team,
            $breakdown['heroes_due'],
            FinancialRecordType::HeroSalary,
            $breakdown['hero_count'],
            'hero',
        );

        $this->settlePayrollPortion(
            $team,
            $breakdown['trainers_due'],
            FinancialRecordType::TrainerSalary,
            $breakdown['trainer_count'],
            'trainer',
        );
    }

    private function settlePayrollPortion(
        Team $team,
        int $due,
        FinancialRecordType $type,
        int $headcount,
        string $roleKey,
    ): void {
        if ($due <= 0) {
            return;
        }

        $deducted = min($team->getGold(), $due);
        $unpaid = $due - $deducted;

        if ($deducted > 0) {
            $this->economyService->deductGold(
                $team,
                $deducted,
                $type,
                FinancialRecordActor::System,
                [
                    'payroll_due' => $due,
                    'unpaid' => $unpaid,
                    sprintf('%s_count', $roleKey) => $headcount,
                ],
            );
        }

        if ($unpaid > 0) {
            $team->setUnpaidDebt($team->getUnpaidDebt() + $unpaid);

            if (0 === $deducted) {
                $this->economyService->recordLedgerEntry(
                    $team,
                    $type,
                    FinancialRecordActor::System,
                    [
                        'payroll_due' => $due,
                        'unpaid' => $unpaid,
                        sprintf('%s_count', $roleKey) => $headcount,
                        'fully_unpaid' => true,
                    ],
                );
            }
        }
    }
}
