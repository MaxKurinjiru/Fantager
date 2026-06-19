<?php

declare(strict_types=1);

namespace App\Service\Kingdom;

use App\Entity\Formation\Formation;
use App\Entity\Formation\FormationSlot;
use App\Entity\Headquarters\Facility;
use App\Entity\Headquarters\Headquarters;
use App\Entity\Hero\Hero;
use App\Entity\Kingdom\Kingdom;
use App\Entity\League\LeagueGroup;
use App\Entity\League\LeagueSeason;
use App\Entity\League\LeagueStanding;
use App\Entity\League\LeagueTier;
use App\Entity\Team\Team;
use App\Enum\FacilityType;
use App\Enum\FormationApproach;
use App\Enum\FormationPosition;
use App\Enum\LeagueSeasonStatus;
use App\Enum\Race;
use App\Repository\Kingdom\KingdomRepository;
use App\Service\Config\KingdomInitConfig;
use App\Service\Config\RaceConfig;
use App\Service\Hero\HeroGenerator;
use App\Service\League\LeagueFixtureScheduler;
use App\Service\TeamChronicle\TeamChronicleService;
use Doctrine\ORM\EntityManagerInterface;

class KingdomInitializationService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly KingdomRepository $kingdomRepository,
        private readonly KingdomInitConfig $initConfig,
        private readonly HeroGenerator $heroGenerator,
        private readonly RaceConfig $raceConfig,
        private readonly LeagueFixtureScheduler $fixtureScheduler,
        private readonly TeamChronicleService $teamChronicleService,
    ) {
    }

    /**
     * @param int|null $startOffsetDaysOverride When set, overrides config/kingdom/season.defaults.json
     *                                          `start_offset_days` (negative values shift season start into the past).
     *
     * @return array{kingdom: Kingdom, teams: int, heroes: int}
     *
     * @throws \DomainException when kingdom name already exists or config is invalid
     */
    public function initialize(string $name, bool $testMode = false, ?int $startOffsetDaysOverride = null): array
    {
        $name = trim($name);
        if ('' === $name) {
            throw new \DomainException('Kingdom name cannot be empty.');
        }

        if (null !== $this->kingdomRepository->findOneBy(['name' => $name])) {
            throw new \DomainException(sprintf('Kingdom "%s" already exists.', $name));
        }

        $kingdomSettings = $this->initConfig->kingdom();
        $leagueConfig = $this->initConfig->leagueTiers();
        $seasonConfig = $this->initConfig->season();
        $teamConfig = $this->initConfig->team();
        $npcConfig = $this->initConfig->npcTeams();
        $hqConfig = $this->initConfig->headquarters();

        $teamsPerGroup = (int) ($leagueConfig['teams_per_group'] ?? 0);
        if ($teamsPerGroup < 1) {
            throw new \DomainException('league_tiers.defaults.json: teams_per_group must be at least 1.');
        }

        /** @var list<array<string, mixed>> $tiers */
        $tiers = $leagueConfig['tiers'] ?? [];
        if ([] === $tiers) {
            throw new \DomainException('league_tiers.defaults.json: tiers must not be empty.');
        }

        $kingdom = $this->createKingdom($name, $kingdomSettings, $leagueConfig);
        $season = $this->createSeason($kingdom, $kingdomSettings, $seasonConfig, $testMode, $startOffsetDaysOverride);

        $teamIndex = 0;
        $heroTotal = 0;

        /** @var list<LeagueGroup> $groups */
        $groups = [];

        foreach ($tiers as $tierDef) {
            $tier = $this->createTier($season, $tierDef);
            $groupsDef = $tierDef['groups'] ?? 0;
            $groupCount = is_array($groupsDef) ? count($groupsDef) : (int) $groupsDef;
            $tierName = (string) ($tierDef['name'] ?? 'Tier');

            for ($g = 1; $g <= $groupCount; ++$g) {
                $group = new LeagueGroup();
                $group->setTier($tier);
                if (is_array($groupsDef) && isset($groupsDef[$g - 1])) {
                    $group->setGroupName((string) $groupsDef[$g - 1]);
                } else {
                    $group->setGroupName(sprintf('%s-G%d', $tierName, $g));
                }
                $tier->addGroup($group);
                $this->em->persist($group);
                $groups[] = $group;

                for ($slot = 0; $slot < $teamsPerGroup; ++$slot) {
                    $teamRace = $this->pickTeamRace($npcConfig);
                    $team = $this->createNpcTeam($kingdom, $teamConfig, $npcConfig, $teamIndex);
                    $this->teamChronicleService->recordTeamEstablished($team, $kingdom, $season->getSeasonNumber());
                    ++$teamIndex;

                    $hq = $this->createHeadquarters($team, $hqConfig, $teamRace);
                    $chamberBonuses = $this->extractChamberBonuses($hq);
                    $heroes = $this->createStartingRoster($team, $teamConfig, $teamRace, $chamberBonuses);
                    $heroTotal += count($heroes);
                    $this->createDefaultFormation($team, $teamConfig, $heroes);

                    $standing = new LeagueStanding();
                    $standing->setGroup($group);
                    $standing->setTeam($team);
                    $group->getStandings()->add($standing);
                    $this->em->persist($standing);
                }
            }
        }

        // Schedule fixtures for each group
        foreach ($groups as $group) {
            $this->fixtureScheduler->scheduleFixturesForGroup($group, $season->getStartDate(), $kingdom->getTimezone());
        }

        $this->em->flush();

        return [
            'kingdom' => $kingdom,
            'teams' => $teamIndex,
            'heroes' => $heroTotal,
        ];
    }

    /** @param array<string, mixed> $settings @param array<string, mixed> $leagueConfig */
    private function createKingdom(string $name, array $settings, array $leagueConfig): Kingdom
    {
        $kingdom = new Kingdom();
        $kingdom->setName($name);
        $kingdom->setLanguage((string) $settings['language']);
        $kingdom->setTimezone((string) $settings['timezone']);
        $kingdom->setGameSpeed((string) $settings['game_speed']);
        $kingdom->setMarketplaceTaxRate((string) $settings['marketplace_tax_rate']);
        $kingdom->setSeasonLength((int) $settings['season_length']);
        $kingdom->setLevelCap((int) $settings['level_cap']);
        $kingdom->setXpModifier((string) $settings['xp_modifier']);
        $kingdom->setLeagueTiersConfig($leagueConfig);

        $this->em->persist($kingdom);

        return $kingdom;
    }

    /** @param array<string, mixed> $kingdomSettings @param array<string, mixed> $seasonConfig */
    private function createSeason(
        Kingdom $kingdom,
        array $kingdomSettings,
        array $seasonConfig,
        bool $testMode,
        ?int $startOffsetDaysOverride = null,
    ): LeagueSeason {
        $offsetDays = $startOffsetDaysOverride ?? (int) ($seasonConfig['start_offset_days'] ?? 0);
        $length = (int) $kingdomSettings['season_length'];

        // Align season start to the next Monday (or last Monday in test mode), modified by config offset
        $baseDay = $testMode
            ? new \DateTimeImmutable('today')->modify('last monday')
            : new \DateTimeImmutable('today')->modify('next monday');
        $start = $baseDay->modify(sprintf('%+d days', $offsetDays));

        // Align end date to the Sunday of Week 11 (based on first Monday of the season)
        $prepMonday = (1 === (int) $start->format('N'))
            ? $start
            : $start->modify('next monday');
        $end = $prepMonday->modify(sprintf('+%d days -1 day', max(1, $length)));

        $today = new \DateTimeImmutable('today');
        $status = LeagueSeasonStatus::Scheduled;
        if ($start <= $today && $today <= $end) {
            $active = (string) ($seasonConfig['status_when_started'] ?? 'active');
            $status = LeagueSeasonStatus::tryFrom($active) ?? LeagueSeasonStatus::Active;
        } elseif ($start > $today) {
            $future = (string) ($seasonConfig['status_when_future'] ?? 'scheduled');
            $status = LeagueSeasonStatus::tryFrom($future) ?? LeagueSeasonStatus::Scheduled;
        } else {
            $status = LeagueSeasonStatus::Completed;
        }

        $season = new LeagueSeason();
        $season->setKingdom($kingdom);
        $season->setSeasonNumber((int) ($seasonConfig['season_number'] ?? 1));
        $season->setStartDate($start);
        $season->setEndDate($end);
        $season->setStatus($status);

        $this->em->persist($season);

        return $season;
    }

    /** @param array<string, mixed> $tierDef */
    private function createTier(LeagueSeason $season, array $tierDef): LeagueTier
    {
        $tier = new LeagueTier();
        $tier->setSeason($season);
        $tier->setTierName((string) ($tierDef['name'] ?? 'Tier'));
        $tier->setPromotionSlots((int) ($tierDef['promotion_slots'] ?? 0));
        $tier->setRelegationSlots((int) ($tierDef['relegation_slots'] ?? 0));
        /** @var array<string, mixed> $rewards */
        $rewards = $tierDef['rewards'] ?? [];
        $tier->setRewards($rewards);
        $season->addTier($tier);
        $this->em->persist($tier);

        return $tier;
    }

    /**
     * @param array<string, mixed> $teamConfig
     * @param array<string, mixed> $npcConfig
     */
    private function createNpcTeam(Kingdom $kingdom, array $teamConfig, array $npcConfig, int $index): Team
    {
        /** @var list<string> $prefixes */
        $prefixes = $npcConfig['name_prefixes'] ?? ['Team'];
        /** @var list<string> $suffixes */
        $suffixes = $npcConfig['name_suffixes'] ?? [''];
        /** @var list<array<string, string>> $colorsPool */
        $colorsPool = $npcConfig['colors_pool'] ?? [[]];

        $teamName = sprintf(
            '#%d %s%s',
            $index + 1,
            $prefixes[$index % count($prefixes)],
            $suffixes[$index % count($suffixes)],
        );
        $colors = $colorsPool[$index % count($colorsPool)];

        /** @var array<string, int> $resources */
        $resources = $teamConfig['starting_resources'] ?? [];

        $team = new Team();
        $team->setKingdom($kingdom);
        $team->setName($teamName);
        $emblem = $npcConfig['emblem'] ?? null;
        $team->setEmblem(is_string($emblem) && '' !== $emblem ? $emblem : null);
        $team->setColors([] !== $colors ? $colors : null);
        $team->setMorale((int) ($teamConfig['morale'] ?? 50));
        $team->setReputation((int) ($teamConfig['reputation'] ?? 0));
        $team->setChemistry((int) ($teamConfig['chemistry'] ?? 0));
        $team->setFanBase((int) ($teamConfig['fan_base'] ?? 350));
        $team->setGold((int) ($resources['gold'] ?? 0));
        $team->setEssenceCommon((int) ($resources['essence_common'] ?? 0));
        $team->setEssenceUncommon((int) ($resources['essence_uncommon'] ?? 0));
        $team->setEssenceRare((int) ($resources['essence_rare'] ?? 0));
        $team->setEssenceEpic((int) ($resources['essence_epic'] ?? 0));
        $team->setEssenceLegendary((int) ($resources['essence_legendary'] ?? 0));
        $team->setEssenceMythic((int) ($resources['essence_mythic'] ?? 0));
        $team->setIsNpc(true);
        $team->setUser(null);

        $this->em->persist($team);

        return $team;
    }

    /**
     * Picks a random team race from the npc config pool, or falls back to any race.
     *
     * @param array<string, mixed> $npcConfig
     */
    private function pickTeamRace(array $npcConfig): Race
    {
        /** @var list<string> $pool */
        $pool = $npcConfig['team_races'] ?? [];

        if ([] !== $pool) {
            $value = $pool[array_rand($pool)];
            $race = Race::tryFrom((string) $value);
            if (null !== $race) {
                return $race;
            }
        }

        $all = Race::cases();

        return $all[array_rand($all)];
    }

    /**
     * @param array<string, mixed>                                                                                   $teamConfig
     * @param array{summon_base_stat_bonus?: float, summon_stat_random_bonus?: float, summon_stat_total_cap?: float} $chamberBonuses
     *
     * @return list<Hero>
     */
    private function createStartingRoster(Team $team, array $teamConfig, Race $teamRace, array $chamberBonuses = []): array
    {
        $count = (int) ($teamConfig['heroes_per_team'] ?? 10);

        // Limit hero races to those with at least neutral (>=50) affinity to the team's race.
        $compatibleRaces = $this->raceConfig->getCompatibleRaces($teamRace, 50);

        $heroes = [];
        for ($i = 0; $i < $count; ++$i) {
            $race = $compatibleRaces[array_rand($compatibleRaces)];
            $hero = $this->heroGenerator->createForTeam($team, $race, $chamberBonuses);
            $this->em->persist($hero);
            $heroes[] = $hero;
        }

        return $heroes;
    }

    /**
     * @param array<string, mixed> $hqConfig
     */
    private function createHeadquarters(Team $team, array $hqConfig, Race $teamRace): Headquarters
    {
        $hq = new Headquarters();
        $hq->setTeam($team);
        // Initial arena adaptation (`race_optimization`) from the team's assigned race (overrides config default).
        $hq->setRaceOptimization($teamRace->value);
        $this->em->persist($hq);

        /** @var list<array<string, mixed>> $facilities */
        $facilities = $hqConfig['facilities'] ?? [];
        foreach ($facilities as $facilityDef) {
            $typeValue = (string) ($facilityDef['type'] ?? '');
            $type = FacilityType::tryFrom($typeValue);
            if (null === $type) {
                continue;
            }

            $facility = new Facility();
            $facility->setHeadquarters($hq);
            $facility->setType($type);
            $facility->setLevel((int) ($facilityDef['level'] ?? 1));
            $hq->addFacility($facility);
            $this->em->persist($facility);
        }

        $hq->syncTotalLevel();

        return $hq;
    }

    /**
     * Reads the Summoning Chamber passive bonuses from an in-memory (not yet flushed) HQ entity.
     *
     * @return array{summon_base_stat_bonus?: float, summon_stat_random_bonus?: float, summon_stat_total_cap?: float}
     */
    private function extractChamberBonuses(Headquarters $hq): array
    {
        foreach ($hq->getFacilities() as $facility) {
            if (FacilityType::SummoningChamber === $facility->getType()) {
                /* @var array{summon_base_stat_bonus?: float, summon_stat_random_bonus?: float, summon_stat_total_cap?: float} */
                return $facility->getPassiveBonuses();
            }
        }

        return [];
    }

    /**
     * @param array<string, mixed> $teamConfig
     * @param list<Hero>           $heroes
     */
    private function createDefaultFormation(Team $team, array $teamConfig, array $heroes): void
    {
        /** @var array<string, mixed> $formationDef */
        $formationDef = $teamConfig['default_formation'] ?? [];
        $approachValue = (string) ($formationDef['approach'] ?? 'balanced');
        $approach = FormationApproach::tryFrom($approachValue) ?? FormationApproach::Balanced;

        $formation = new Formation();
        $formation->setTeam($team);
        $formation->setName((string) ($formationDef['name'] ?? 'Default'));
        $formation->setApproach($approach);
        $formation->setIsDefault(true);
        $this->em->persist($formation);

        $lineupCount = (int) ($formationDef['lineup_slot_count'] ?? 6);
        $positions = FormationPosition::cases();

        for ($i = 0; $i < min($lineupCount, count($positions)); ++$i) {
            $slot = new FormationSlot();
            $slot->setFormation($formation);
            $slot->setPosition($positions[$i]);
            $slot->setHero($heroes[$i] ?? null);
            $slot->setStrategy([]);
            $slot->setSpellPriorities([]);
            $this->em->persist($slot);
        }
    }
}
