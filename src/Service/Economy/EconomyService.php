<?php

declare(strict_types=1);

namespace App\Service\Economy;

use App\Entity\Team\FinancialRecord;
use App\Entity\Team\Team;
use App\Enum\FinancialRecordActor;
use App\Enum\FinancialRecordType;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Handles gold and resource transactions for a team.
 * All deductions validate that the team has sufficient funds before mutating state.
 */
class EconomyService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
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
            throw new \DomainException(sprintf('Insufficient gold. Required: %d, available: %d.', $amount, $team->getGold()));
        }

        $team->setGold($team->getGold() - $amount);
        $this->recordTransaction($team, $type, $actor, -$amount, 0, 0, 0, 0, 0, 0, $context);
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
        $this->recordTransaction($team, $type, $actor, $amount, 0, 0, 0, 0, 0, 0, $context);
    }

    /**
     * Add essence of the given rarity.
     *
     * @param array<string, mixed> $context
     */
    public function addEssence(
        Team $team,
        string $rarity,
        int $amount,
        FinancialRecordType $type,
        FinancialRecordActor $actor,
        array $context = [],
    ): void {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Addition amount must be positive.');
        }

        $common = 0;
        $uncommon = 0;
        $rare = 0;
        $epic = 0;
        $legendary = 0;
        $mythic = 0;
        switch (strtolower($rarity)) {
            case 'common':
                $team->setEssenceCommon($team->getEssenceCommon() + $amount);
                $common = $amount;
                break;
            case 'uncommon':
                $team->setEssenceUncommon($team->getEssenceUncommon() + $amount);
                $uncommon = $amount;
                break;
            case 'rare':
                $team->setEssenceRare($team->getEssenceRare() + $amount);
                $rare = $amount;
                break;
            case 'epic':
                $team->setEssenceEpic($team->getEssenceEpic() + $amount);
                $epic = $amount;
                break;
            case 'legendary':
                $team->setEssenceLegendary($team->getEssenceLegendary() + $amount);
                $legendary = $amount;
                break;
            case 'mythic':
                $team->setEssenceMythic($team->getEssenceMythic() + $amount);
                $mythic = $amount;
                break;
            default:
                throw new \InvalidArgumentException(sprintf('Unknown essence rarity "%s".', $rarity));
        }

        $this->recordTransaction($team, $type, $actor, 0, $common, $uncommon, $rare, $epic, $legendary, $mythic, $context);
    }

    /**
     * Deduct essence of the given rarity. Throws if insufficient funds.
     *
     * @param array<string, mixed> $context
     *
     * @throws \DomainException
     */
    public function deductEssence(
        Team $team,
        string $rarity,
        int $amount,
        FinancialRecordType $type,
        FinancialRecordActor $actor,
        array $context = [],
    ): void {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Deduction amount must be positive.');
        }

        $common = 0;
        $uncommon = 0;
        $rare = 0;
        $epic = 0;
        $legendary = 0;
        $mythic = 0;
        switch (strtolower($rarity)) {
            case 'common':
                if ($team->getEssenceCommon() < $amount) {
                    throw new \DomainException('Insufficient common essence.');
                }
                $team->setEssenceCommon($team->getEssenceCommon() - $amount);
                $common = -$amount;
                break;
            case 'uncommon':
                if ($team->getEssenceUncommon() < $amount) {
                    throw new \DomainException('Insufficient uncommon essence.');
                }
                $team->setEssenceUncommon($team->getEssenceUncommon() - $amount);
                $uncommon = -$amount;
                break;
            case 'rare':
                if ($team->getEssenceRare() < $amount) {
                    throw new \DomainException('Insufficient rare essence.');
                }
                $team->setEssenceRare($team->getEssenceRare() - $amount);
                $rare = -$amount;
                break;
            case 'epic':
                if ($team->getEssenceEpic() < $amount) {
                    throw new \DomainException('Insufficient epic essence.');
                }
                $team->setEssenceEpic($team->getEssenceEpic() - $amount);
                $epic = -$amount;
                break;
            case 'legendary':
                if ($team->getEssenceLegendary() < $amount) {
                    throw new \DomainException('Insufficient legendary essence.');
                }
                $team->setEssenceLegendary($team->getEssenceLegendary() - $amount);
                $legendary = -$amount;
                break;
            case 'mythic':
                if ($team->getEssenceMythic() < $amount) {
                    throw new \DomainException('Insufficient mythic essence.');
                }
                $team->setEssenceMythic($team->getEssenceMythic() - $amount);
                $mythic = -$amount;
                break;
            default:
                throw new \InvalidArgumentException(sprintf('Unknown essence rarity "%s".', $rarity));
        }

        $this->recordTransaction($team, $type, $actor, 0, $common, $uncommon, $rare, $epic, $legendary, $mythic, $context);
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
        int $essenceCommonChange = 0,
        int $essenceUncommonChange = 0,
        int $essenceRareChange = 0,
        int $essenceEpicChange = 0,
        int $essenceLegendaryChange = 0,
        int $essenceMythicChange = 0,
        array $context = [],
    ): void {
        $record = new FinancialRecord();
        $record->setTeam($team);
        $record->setType($type);
        $record->setActor($actor);
        $record->setGoldChange($goldChange);
        $record->setEssenceCommonChange($essenceCommonChange);
        $record->setEssenceUncommonChange($essenceUncommonChange);
        $record->setEssenceRareChange($essenceRareChange);
        $record->setEssenceEpicChange($essenceEpicChange);
        $record->setEssenceLegendaryChange($essenceLegendaryChange);
        $record->setEssenceMythicChange($essenceMythicChange);
        $record->setContext($context);

        $this->em->persist($record);
    }
}
