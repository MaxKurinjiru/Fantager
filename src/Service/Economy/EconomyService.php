<?php

declare(strict_types=1);

namespace App\Service\Economy;

use App\Entity\Team\FinancialRecord;
use App\Entity\Team\Team;
use App\Enum\FinancialRecordActor;
use App\Enum\FinancialRecordType;
use App\Exception\UserFacingException;
use App\Service\Calendar\TickClock;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Handles gold transactions for a team.
 * All deductions validate that the team has sufficient funds before mutating state.
 */
class EconomyService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TickClock $tickClock,
    ) {
    }

    /**
     * Deduct gold from team. Throws if insufficient funds.
     *
     * @param array<string, mixed> $context
     *
     * @throws \DomainException
     */
    public function deductGold(
        Team $team,
        int $amount,
        FinancialRecordType $type,
        FinancialRecordActor $actor,
        array $context = [],
    ): void {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Deduction amount must be positive.');
        }

        if ($team->getGold() < $amount) {
            throw new UserFacingException('error.insufficient_gold', ['%required%' => $amount, '%available%' => $team->getGold()]);
        }

        $team->setGold($team->getGold() - $amount);
        $this->recordTransaction($team, $type, $actor, -$amount, $context);
    }

    /**
     * Add gold to team.
     *
     * @param array<string, mixed> $context
     */
    public function addGold(
        Team $team,
        int $amount,
        FinancialRecordType $type,
        FinancialRecordActor $actor,
        array $context = [],
    ): void {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Addition amount must be positive.');
        }

        $team->setGold($team->getGold() + $amount);
        $this->recordTransaction($team, $type, $actor, $amount, $context);
    }

    /**
     * Record a ledger entry without mutating team balances.
     *
     * @param array<string, mixed> $context
     */
    public function recordLedgerEntry(
        Team $team,
        FinancialRecordType $type,
        FinancialRecordActor $actor,
        array $context = [],
    ): void {
        $this->recordTransaction($team, $type, $actor, 0, $context);
    }

    /**
     * Flush pending changes. Should be called after all mutations are applied together.
     */
    public function flush(): void
    {
        $this->em->flush();
    }

    /**
     * @param array<string, mixed> $context
     */
    private function recordTransaction(
        Team $team,
        FinancialRecordType $type,
        FinancialRecordActor $actor,
        int $goldChange = 0,
        array $context = [],
    ): void {
        $record = new FinancialRecord();
        $record->setTeam($team);
        $record->setType($type);
        $record->setActor($actor);
        $record->setGoldChange($goldChange);
        $record->setContext($context);
        $record->setCreatedAt($this->tickClock->getCurrentTime());
        $record->setProcessedAt(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));

        $this->em->persist($record);
    }
}
