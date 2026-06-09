<?php

declare(strict_types=1);

namespace App\Service\Summoning;

use App\Entity\Hero\Hero;
use App\Entity\Summoning\SummonHistory;
use App\Entity\Team\Team;
use App\Enum\Race;
use App\Service\Config\RaceConfig;
use App\Service\Economy\EconomyService;
use App\Service\Headquarters\HeadquartersService;
use App\Service\Hero\HeroGenerator;
use Doctrine\ORM\EntityManagerInterface;

class SummoningService
{
    private const BASE_GOLD_COST = 500;
    private const MAX_SUMMONS_PER_CYCLE = 3;

    public function __construct(
        private readonly HeroGenerator $heroGenerator,
        private readonly EconomyService $economyService,
        private readonly HeadquartersService $hqService,
        private readonly EntityManagerInterface $em,
        private readonly RaceConfig $raceConfig,
    ) {
    }

    public function getGoldCost(): int
    {
        return self::BASE_GOLD_COST;
    }

    /**
     * Returns summoning availability status for the team.
     *
     * @return array{available: bool, reason: string|null, gold_cost: int, summons_used: int, summons_max: int}
     */
    public function getStatus(Team $team): array
    {
        $cost = $this->getGoldCost();
        $used = $team->getSummonsThisCycle();

        $rosterLimit = $this->hqService->getRosterLimit($team);
        $heroCount = $this->em->getRepository(Hero::class)->count(['team' => $team]);
        if ($heroCount >= $rosterLimit) {
            return [
                'available' => false,
                'reason' => 'Roster is full.',
                'gold_cost' => $cost,
                'summons_used' => $used,
                'summons_max' => self::MAX_SUMMONS_PER_CYCLE,
            ];
        }

        if ($used >= self::MAX_SUMMONS_PER_CYCLE) {
            return [
                'available' => false,
                'reason' => 'Summoning limit reached for this cycle.',
                'gold_cost' => $cost,
                'summons_used' => $used,
                'summons_max' => self::MAX_SUMMONS_PER_CYCLE,
            ];
        }

        if ($team->getGold() < $cost) {
            return [
                'available' => false,
                'reason' => sprintf('Insufficient gold. Required: %d, available: %d.', $cost, $team->getGold()),
                'gold_cost' => $cost,
                'summons_used' => $used,
                'summons_max' => self::MAX_SUMMONS_PER_CYCLE,
            ];
        }

        return [
            'available' => true,
            'reason' => null,
            'gold_cost' => $cost,
            'summons_used' => $used,
            'summons_max' => self::MAX_SUMMONS_PER_CYCLE,
        ];
    }

    public function getArenaRaceTheme(Team $team): ?Race
    {
        $hq = $this->em->getRepository(\App\Entity\Headquarters\Headquarters::class)->findOneBy(['team' => $team]);
        if (!$hq instanceof \App\Entity\Headquarters\Headquarters) {
            return null;
        }

        // Read HQ raceOptimization directly
        $opt = $hq->getRaceOptimization();
        if (is_string($opt)) {
            return Race::tryFrom($opt);
        }

        return null;
    }

    /**
     * @return list<Race>
     */
    public function getCompatibleRacesForTeam(Team $team): array
    {
        $theme = $this->getArenaRaceTheme($team);
        if (null === $theme) {
            return Race::cases();
        }

        return $this->raceConfig->getCompatibleRaces($theme, 50);
    }

    /**
     * @return list<array{race: Race, name: string, combat_bonus: string}>
     */
    public function getCompatibleRacesDetails(Team $team): array
    {
        $races = $this->getCompatibleRacesForTeam($team);
        $details = [];
        foreach ($races as $race) {
            $config = $this->raceConfig->get($race);
            $details[] = [
                'race' => $race,
                'name' => $config['name'] ?? ucfirst($race->value),
                'combat_bonus' => $config['combat_bonus'] ?? '',
            ];
        }

        return $details;
    }

    /**
     * Summon a new hero for the team, picking their race based on Arena optimization and relationships.
     *
     * @throws \DomainException when gold is insufficient, cycle limit is reached, or no compatible race is available
     */
    public function summon(Team $team): Hero
    {
        $rosterLimit = $this->hqService->getRosterLimit($team);
        $heroCount = $this->em->getRepository(Hero::class)->count(['team' => $team]);
        if ($heroCount >= $rosterLimit) {
            throw new \DomainException('Roster is full.');
        }

        if ($team->getSummonsThisCycle() >= self::MAX_SUMMONS_PER_CYCLE) {
            throw new \DomainException('Summoning limit reached for this cycle.');
        }

        $cost = $this->getGoldCost();
        $this->economyService->deductGold(
            $team,
            $cost,
            \App\Enum\FinancialRecordType::SummonFee,
            \App\Enum\FinancialRecordActor::Active
        );

        $chamberBonuses = $this->hqService->getSummoningChamberBonuses($team);

        $compatibleRaces = $this->getCompatibleRacesForTeam($team);
        if ([] === $compatibleRaces) {
            throw new \DomainException('No compatible races found for summoning.');
        }

        $race = $compatibleRaces[array_rand($compatibleRaces)];
        $hero = $this->heroGenerator->createForTeam($team, $race, $chamberBonuses);

        $history = new SummonHistory();
        $history->setTeam($team);
        $history->setRaceSelected($race);
        $history->setHero($hero);
        $history->setGoldCost($cost);

        $team->setSummonsThisCycle($team->getSummonsThisCycle() + 1);
        $team->setLastSummonAt(new \DateTimeImmutable());

        $this->em->persist($hero);
        $this->em->persist($history);
        $this->em->flush();

        return $hero;
    }
}
