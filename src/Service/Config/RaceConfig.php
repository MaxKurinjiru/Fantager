<?php

declare(strict_types=1);

namespace App\Service\Config;

use App\Enum\Race;
use Symfony\Component\Yaml\Yaml;

/**
 * Loads race definitions from config/game/races.yaml and race_relations.yaml.
 */
class RaceConfig
{
    /** @var array<string, array<string, mixed>> */
    private array $data;

    /** @var array<string, int> Flat map of "raceA-raceB" => value (canonical order: alphabetical) */
    private array $relations;

    public function __construct(string $projectDir)
    {
        /** @var array{races: array<string, array<string, mixed>>} $parsed */
        $parsed = Yaml::parseFile($projectDir.'/config/game/races.yaml');
        $this->data = $parsed['races'];

        /** @var array{relationships: array<string, int>} $relParsed */
        $relParsed = Yaml::parseFile($projectDir.'/config/game/race_relations.yaml');
        $this->relations = $relParsed['relationships'];
    }

    /** @return array<string, mixed> */
    public function get(Race $race): array
    {
        return $this->data[$race->value] ?? [];
    }

    /** @return array<string, int> */
    public function getStatBonuses(Race $race): array
    {
        /* @var array<string, int> */
        return $this->get($race)['stat_bonuses'] ?? [];
    }

    /** @return array{min: int, max_junior: int, prime_limit: int, death_expectation: int} */
    public function getAge(Race $race): array
    {
        /* @var array{min: int, max_junior: int, prime_limit: int, death_expectation: int} */
        return $this->get($race)['age'] ?? ['min' => 16, 'max_junior' => 20, 'prime_limit' => 35, 'death_expectation' => 70];
    }

    public function getTrainingSpeedModifier(Race $race): float
    {
        return (float) ($this->get($race)['training_speed_modifier'] ?? 1.0);
    }

    /**
     * Returns the relationship value (0–100) between two races.
     * A race always has a perfect relationship (100) with itself.
     * The matrix is symmetric — order of arguments does not matter.
     */
    public function getRelationship(Race $a, Race $b): int
    {
        if ($a === $b) {
            return 100;
        }

        // Canonical key: alphabetical order of the two race values
        $pair = [$a->value, $b->value];
        sort($pair);
        $key = implode('-', $pair);

        return $this->relations[$key] ?? 50;
    }

    /**
     * Returns all Race cases whose relationship with $teamRace is >= $minValue.
     * Always includes $teamRace itself (self-relation = 100).
     *
     * @return list<Race>
     */
    public function getCompatibleRaces(Race $teamRace, int $minValue = 50): array
    {
        $compatible = [];
        foreach (Race::cases() as $candidate) {
            if ($this->getRelationship($teamRace, $candidate) >= $minValue) {
                $compatible[] = $candidate;
            }
        }

        return $compatible;
    }
}
