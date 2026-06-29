<?php

declare(strict_types=1);

namespace App\Service\Economy;

use App\Entity\Auth\User;
use App\Entity\Headquarters\Headquarters;
use App\Entity\Kingdom\Kingdom;
use App\Entity\Team\Team;
use App\Enum\ChronicleReleaseReason;
use App\Enum\FinancialCrisisLevel;
use App\Enum\FinancialRecordActor;
use App\Enum\FinancialRecordType;
use App\Enum\NotificationType;
use App\Exception\UserFacingException;
use App\Repository\Headquarters\HeadquartersRepository;
use App\Service\Headquarters\HqMaintenanceCalculator;
use App\Service\Notification\NotificationHelper;
use App\Service\TeamChronicle\TeamChronicleService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class FinancialCrisisService
{
    public const RESTRICTED_WEEKS = 2;
    public const BANKRUPTCY_WEEKS = 6;
    public const BANKRUPTCY_DEBT_MULTIPLIER = 4;
    public const REASSIGNMENT_COOLDOWN_DAYS = 7;
    public const RECOVERY_STALE_WEEKS = 2;

    public function __construct(
        private readonly HeadquartersRepository $hqRepository,
        private readonly EconomyService $economyService,
        private readonly TeamPayrollService $teamPayrollService,
        private readonly NotificationHelper $notificationHelper,
        private readonly TeamChronicleService $teamChronicleService,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getStatus(Team $team): array
    {
        $hq = $this->hqRepository->findOneBy(['team' => $team]);
        $weeklyMaintenance = $hq instanceof Headquarters
            ? HqMaintenanceCalculator::calculateWeeklyMaintenanceFee($hq)
            : 0;
        $weeklyPayroll = $this->teamPayrollService->calculateWeeklyPayrollFee($team);
        $weeklyExpenses = $weeklyMaintenance + $weeklyPayroll;

        $level = $this->resolveCrisisLevel($team, $weeklyExpenses);
        $weeksUntilBankruptcy = $this->calculateWeeksUntilBankruptcy($team, $weeklyExpenses, $level);

        return [
            'level' => $level->value,
            'unpaid_debt' => $team->getUnpaidDebt(),
            'crisis_weeks' => $team->getCrisisWeeks(),
            'weekly_maintenance' => $weeklyMaintenance,
            'weekly_payroll' => $weeklyPayroll,
            'weekly_expenses' => $weeklyExpenses,
            'gold' => $team->getGold(),
            'bankruptcy_debt_threshold' => $weeklyExpenses * self::BANKRUPTCY_DEBT_MULTIPLIER,
            'weeks_until_bankruptcy' => $weeksUntilBankruptcy,
            'hq_bonuses_active' => $this->areHqBonusesActive($team, $level),
            'restricted_actions' => $this->getRestrictedActions($level),
            'recovery_actions_available' => [
                'dismiss_hero',
                'sell_on_marketplace',
                'downgrade_facility',
            ],
            'last_recovery_action_at' => $team->getLastRecoveryActionAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    public function resolveCrisisLevel(Team $team, int $weeklyReferenceExpenses): FinancialCrisisLevel
    {
        if ($team->isNpc() || $weeklyReferenceExpenses <= 0) {
            return FinancialCrisisLevel::None;
        }

        $debt = $team->getUnpaidDebt();
        $crisisWeeks = $team->getCrisisWeeks();

        if ($debt <= 0) {
            return FinancialCrisisLevel::None;
        }

        if ($this->isBankruptcyPending($team, $weeklyReferenceExpenses)) {
            return FinancialCrisisLevel::BankruptcyPending;
        }

        if ($crisisWeeks >= self::RESTRICTED_WEEKS) {
            return FinancialCrisisLevel::Restricted;
        }

        return FinancialCrisisLevel::Warning;
    }

    public function areHqBonusesActive(Team $team, ?FinancialCrisisLevel $level = null): bool
    {
        $hq = $this->hqRepository->findOneBy(['team' => $team]);
        $weeklyReferenceExpenses = $this->resolveWeeklyReferenceExpenses($team, $hq);

        $level ??= $this->resolveCrisisLevel($team, $weeklyReferenceExpenses);

        if (FinancialCrisisLevel::None === $level || FinancialCrisisLevel::Warning === $level) {
            return true;
        }

        return $team->getUnpaidDebt() <= 0;
    }

    /**
     * @throws \DomainException when spending is blocked during financial restrictions
     */
    public function assertSpendingAllowed(Team $team, string $action): void
    {
        if ($team->isNpc()) {
            return;
        }

        $weeklyReferenceExpenses = $this->resolveWeeklyReferenceExpenses($team);

        $level = $this->resolveCrisisLevel($team, $weeklyReferenceExpenses);
        if (!in_array($level, [FinancialCrisisLevel::Restricted, FinancialCrisisLevel::BankruptcyPending], true)) {
            return;
        }

        $blockedActions = [
            'hq_upgrade',
            'hq_arena_adaptation',
            'summon',
            'marketplace_purchase',
            'marketplace_bid',
        ];

        if (in_array($action, $blockedActions, true)) {
            throw new UserFacingException('error.financial_crisis_blocked', ['%level%' => $level->value]);
        }
    }

    public function recordRecoveryAction(Team $team, ?\DateTimeImmutable $at = null): void
    {
        $team->setLastRecoveryActionAt($at ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
    }

    public function addUnpaidDebt(Team $team, int $amount): void
    {
        if ($amount <= 0) {
            return;
        }

        $team->setUnpaidDebt($team->getUnpaidDebt() + $amount);
    }

    public function applyGoldToDebt(Team $team): int
    {
        $debt = $team->getUnpaidDebt();
        if ($debt <= 0 || $team->getGold() <= 0) {
            return 0;
        }

        $payment = min($team->getGold(), $debt);
        $this->economyService->deductGold(
            $team,
            $payment,
            FinancialRecordType::DebtRepayment,
            FinancialRecordActor::System,
            ['debt_before' => $debt, 'debt_after' => $debt - $payment]
        );
        $team->setUnpaidDebt($debt - $payment);

        if (0 === $team->getUnpaidDebt()) {
            $this->recordRecoveryAction($team);
        }

        return $payment;
    }

    public function processWeeklyCrisisTick(Kingdom $kingdom, ?Team $team = null): void
    {
        if (null !== $team) {
            $hq = $this->hqRepository->findOneBy(['team' => $team]);
            $hqs = null !== $hq ? [$hq] : [];
        } else {
            $hqs = $this->hqRepository->findByKingdom($kingdom);
        }

        foreach ($hqs as $hq) {
            /** @var Headquarters $hq */
            $team = $hq->getTeam();
            if ($team->isNpc() || null === $team->getUser()) {
                continue;
            }

            $weeklyReferenceExpenses = $this->resolveWeeklyReferenceExpenses($team, $hq);
            $this->applyGoldToDebt($team);
            $this->evaluateCrisisProgress($team, $weeklyReferenceExpenses);

            if ($this->isBankruptcyPending($team, $weeklyReferenceExpenses)) {
                $user = $team->getUser();
                if ($user instanceof User) {
                    $this->executeBankruptcy($team, $user);
                }
            }
        }

        $this->em->flush();
    }

    public function executeBankruptcy(Team $team, User $user): void
    {
        $this->logger->warning(sprintf(
            'Financial bankruptcy: team ID %d (%s), user ID %d (%s)',
            $team->getId(),
            $team->getName(),
            $user->getId(),
            $user->getEmail()
        ));

        $team->setUser(null);
        $team->setIsNpc(true);
        $this->teamChronicleService->recordPlayerReleased($team, $user, ChronicleReleaseReason::Bankruptcy);
        $team->setUnpaidDebt(0);
        $team->setCrisisWeeks(0);
        $team->setLastRecoveryActionAt(null);
        $team->setGold(0);

        $user->setTeam(null);
        $user->setTeamReassignmentAvailableAt(
            (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
                ->modify(sprintf('+%d days', self::REASSIGNMENT_COOLDOWN_DAYS))
        );

        $this->notificationHelper->sendNotification(
            $user,
            NotificationType::System,
            'Team bankruptcy',
            sprintf(
                'Your team "%s" was released due to prolonged financial insolvency. You may claim a new team after %d days.',
                $team->getName(),
                self::REASSIGNMENT_COOLDOWN_DAYS
            )
        );
    }

    private function evaluateCrisisProgress(Team $team, int $weeklyReferenceExpenses): void
    {
        $debt = $team->getUnpaidDebt();

        if (0 === $debt) {
            $team->setCrisisWeeks(0);

            return;
        }

        $team->setCrisisWeeks($team->getCrisisWeeks() + 1);
    }

    private function isBankruptcyPending(Team $team, int $weeklyReferenceExpenses): bool
    {
        if ($weeklyReferenceExpenses <= 0 || $team->isNpc()) {
            return false;
        }

        if ($team->getCrisisWeeks() < self::BANKRUPTCY_WEEKS) {
            return false;
        }

        if ($team->getUnpaidDebt() < $weeklyReferenceExpenses * self::BANKRUPTCY_DEBT_MULTIPLIER) {
            return false;
        }

        if ($team->getGold() > 0) {
            return false;
        }

        return !$this->hasRecentRecoveryAction($team);
    }

    private function hasRecentRecoveryAction(Team $team): bool
    {
        $lastRecovery = $team->getLastRecoveryActionAt();
        if (null === $lastRecovery) {
            return false;
        }

        $threshold = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->modify(sprintf('-%d weeks', self::RECOVERY_STALE_WEEKS));

        return $lastRecovery >= $threshold;
    }

    private function calculateWeeksUntilBankruptcy(
        Team $team,
        int $weeklyReferenceExpenses,
        FinancialCrisisLevel $level,
    ): ?int {
        if (FinancialCrisisLevel::BankruptcyPending === $level) {
            return 0;
        }

        if ($weeklyReferenceExpenses <= 0 || $team->isNpc()) {
            return null;
        }

        $remaining = self::BANKRUPTCY_WEEKS - $team->getCrisisWeeks();

        return max(0, $remaining);
    }

    /**
     * @return list<string>
     */
    private function getRestrictedActions(FinancialCrisisLevel $level): array
    {
        if (!in_array($level, [FinancialCrisisLevel::Restricted, FinancialCrisisLevel::BankruptcyPending], true)) {
            return [];
        }

        return [
            'hq_upgrade',
            'hq_arena_adaptation',
            'summon',
            'marketplace_purchase',
            'marketplace_bid',
        ];
    }

    private function resolveWeeklyReferenceExpenses(Team $team, ?Headquarters $hq = null): int
    {
        $hq ??= $this->hqRepository->findOneBy(['team' => $team]);
        $weeklyMaintenance = $hq instanceof Headquarters
            ? HqMaintenanceCalculator::calculateWeeklyMaintenanceFee($hq)
            : 0;

        return $weeklyMaintenance + $this->teamPayrollService->calculateWeeklyPayrollFee($team);
    }
}
