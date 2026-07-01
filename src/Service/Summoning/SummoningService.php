<?php

declare(strict_types=1);

namespace App\Service\Summoning;

use App\Entity\Hero\Hero;
use App\Entity\Team\Team;
use App\Entity\Team\TeamSummonHistory;
use App\Enum\Race;
use App\Enum\RoyalTreasuryContributionSource;
use App\Exception\UserFacingException;
use App\Service\Config\RaceConfig;
use App\Service\Economy\EconomyService;
use App\Service\Economy\FinancialCrisisService;
use App\Service\Economy\RoyalTreasuryService;
use App\Service\Headquarters\HeadquartersService;
use App\Service\Hero\HeroChronicleService;
use App\Service\Hero\HeroGenerator;
use App\Service\Team\TeamChemistryService;
use App\Service\TeamChronicle\TeamChronicleService;
use Doctrine\ORM\EntityManagerInterface;

class SummoningService
{
    private const BASE_GOLD_COST = 500;
    private const MAX_SUMMONS_PER_CYCLE = 1;

    public function __construct(
        private readonly HeroGenerator $heroGenerator,
        private readonly EconomyService $economyService,
        private readonly FinancialCrisisService $financialCrisisService,
        private readonly RoyalTreasuryService $royalTreasuryService,
        private readonly HeadquartersService $hqService,
        private readonly EntityManagerInterface $em,
        private readonly RaceConfig $raceConfig,
        private readonly TeamChronicleService $teamChronicleService,
        private readonly TeamChemistryService $teamChemistryService,
        private readonly HeroChronicleService $heroChronicleService,
    ) {
    }

    public function getGoldCost(Team $team): int
    {
        $chamberLevel = 1;
        $hq = $this->em->getRepository(\App\Entity\Headquarters\Headquarters::class)->findOneBy(['team' => $team]);
        if ($hq instanceof \App\Entity\Headquarters\Headquarters) {
            foreach ($hq->getFacilities() as $facility) {
                if (\App\Enum\FacilityType::SummoningChamber === $facility->getType()) {
                    $chamberLevel = $facility->getLevel();
                    break;
                }
            }
        }

        // Base gold cost scaled by facility level (+20% per level above 1)
        $baseCost = self::BASE_GOLD_COST * (1.2 ** ($chamberLevel - 1));

        // Inflation per summon in this cycle (+50% per previous summon in this cycle)
        $inflationMultiplier = 1.0 + 0.5 * $team->getSummonsThisCycle();

        return (int) round($baseCost * $inflationMultiplier);
    }

    /**
     * Returns summoning availability status for the team.
     *
     * @return array{
     *     available: bool,
     *     reason: string|null,
     *     gold_cost: int,
     *     summons_used: int,
     *     summons_max: int,
     *     reason_parameters?: array<string, int|string|float>
     * }
     */
    public function getStatus(Team $team): array
    {
        $cost = $this->getGoldCost($team);
        $used = $team->getSummonsThisCycle();
        $maxSummons = (int) max(1, round(self::MAX_SUMMONS_PER_CYCLE * (float) $team->getKingdom()->getGameSpeed()));

        $rosterLimit = $this->hqService->getRosterLimit($team);
        $heroCount = $this->em->getRepository(Hero::class)->countActiveCombatantsByTeam($team);
        if ($heroCount >= $rosterLimit) {
            return [
                'available' => false,
                'reason' => 'error.summoning_roster_full',
                'gold_cost' => $cost,
                'summons_used' => $used,
                'summons_max' => $maxSummons,
            ];
        }

        if ($used >= $maxSummons) {
            return [
                'available' => false,
                'reason' => 'error.summoning_limit_reached',
                'gold_cost' => $cost,
                'summons_used' => $used,
                'summons_max' => $maxSummons,
            ];
        }

        try {
            $this->financialCrisisService->assertSpendingAllowed($team, 'summon');
        } catch (UserFacingException $e) {
            return [
                'available' => false,
                'reason' => $e->getTranslationKey(),
                'reason_parameters' => $e->getParameters(),
                'gold_cost' => $cost,
                'summons_used' => $used,
                'summons_max' => $maxSummons,
            ];
        } catch (\DomainException $e) {
            return [
                'available' => false,
                'reason' => $e->getMessage(),
                'gold_cost' => $cost,
                'summons_used' => $used,
                'summons_max' => $maxSummons,
            ];
        }

        if ($team->getGold() < $cost) {
            return [
                'available' => false,
                'reason' => 'error.summoning_insufficient_gold',
                'gold_cost' => $cost,
                'summons_used' => $used,
                'summons_max' => $maxSummons,
            ];
        }

        return [
            'available' => true,
            'reason' => null,
            'gold_cost' => $cost,
            'summons_used' => $used,
            'summons_max' => $maxSummons,
        ];
    }

    /** Arena adaptation for summoning — reads HQ `race_optimization`. */
    public function getArenaRaceTheme(Team $team): ?Race
    {
        $hq = $this->em->getRepository(\App\Entity\Headquarters\Headquarters::class)->findOneBy(['team' => $team]);
        if (!$hq instanceof \App\Entity\Headquarters\Headquarters) {
            return null;
        }

        // Active arena adaptation stored in HQ `race_optimization`.
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
     * Summon a new hero for the team, picking their race based on arena adaptation and relationships.
     *
     * @throws \DomainException when gold is insufficient, cycle limit is reached, or no compatible race is available
     */
    public function summon(Team $team): Hero
    {
        $rosterLimit = $this->hqService->getRosterLimit($team);
        $heroCount = $this->em->getRepository(Hero::class)->countActiveCombatantsByTeam($team);
        if ($heroCount >= $rosterLimit) {
            throw new UserFacingException('error.summoning_roster_full');
        }

        $maxSummons = (int) max(1, round(self::MAX_SUMMONS_PER_CYCLE * (float) $team->getKingdom()->getGameSpeed()));
        if ($team->getSummonsThisCycle() >= $maxSummons) {
            throw new UserFacingException('error.summoning_limit_reached');
        }

        $this->financialCrisisService->assertSpendingAllowed($team, 'summon');

        $cost = $this->getGoldCost($team);
        $this->economyService->deductGold(
            $team,
            $cost,
            \App\Enum\FinancialRecordType::SummonFee,
            \App\Enum\FinancialRecordActor::Active
        );
        $this->royalTreasuryService->collectFee(
            $team->getKingdom(),
            $cost,
            RoyalTreasuryContributionSource::SummonFee,
        );

        $chamberBonuses = $this->hqService->getSummoningChamberBonuses($team);

        $compatibleRaces = $this->getCompatibleRacesForTeam($team);
        if ([] === $compatibleRaces) {
            throw new UserFacingException('error.summoning_no_compatible_races');
        }

        $race = $compatibleRaces[array_rand($compatibleRaces)];
        $hero = $this->heroGenerator->createForTeam($team, $race, $chamberBonuses);

        $history = new TeamSummonHistory();
        $history->setTeam($team);
        $history->setRaceSelected($race);
        $history->setHero($hero);
        $history->setGoldCost($cost);

        $team->setSummonsThisCycle($team->getSummonsThisCycle() + 1);
        $team->setLastSummonAt(new \DateTimeImmutable());

        $this->em->persist($hero);
        $this->em->persist($history);
        $this->teamChronicleService->recordSummonCompleted($team, $hero, $race, $cost);
        $this->heroChronicleService->recordSummoned($hero, $cost);
        $this->em->flush();

        $this->teamChemistryService->recalculate($team);

        return $hero;
    }
}
