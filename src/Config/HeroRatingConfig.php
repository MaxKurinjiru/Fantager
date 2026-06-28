<?php

declare(strict_types=1);

namespace App\Config;

use App\Enum\HeroTrait;
use Symfony\Component\Yaml\Yaml;

class HeroRatingConfig
{
    /** @var array<string, float> */
    private array $physicalWeights;

    /** @var array<string, float> */
    private array $magicWeights;

    /** @var array<string, float> */
    private array $referenceCeilings;

    private int $baseOvrMax;
    private int $complexRatingMax;
    private int $masteryPointsPerTier;

    /** @var array<string, int> */
    private array $traitBonuses;

    /** @var array<string, float> */
    private array $ageMultipliers;

    private float $goldPerComplexPoint;
    private float $dismissCompensationRatio;
    private float $trainerDismissCompensationRatio;
    private float $heroSalaryFactor;
    private float $trainerSalaryFactor;
    private float $trainerMarketMultiplier;

    public function __construct(string $projectDir)
    {
        /** @var array<string, mixed> $parsed */
        $parsed = Yaml::parseFile($projectDir.'/config/game/hero-rating.yaml');

        $this->physicalWeights = $this->floatMap($parsed['physical_weights'] ?? []);
        $this->magicWeights = $this->floatMap($parsed['magic_weights'] ?? []);
        $this->referenceCeilings = $this->floatMap($parsed['reference_ceilings'] ?? []);

        $scale = $parsed['scale'] ?? [];
        $this->baseOvrMax = (int) ($scale['base_ovr_max'] ?? 100);
        $this->complexRatingMax = (int) ($scale['complex_rating_max'] ?? 9999);

        $mastery = $parsed['mastery'] ?? [];
        $this->masteryPointsPerTier = (int) ($mastery['points_per_tier'] ?? 25);

        $this->traitBonuses = [];
        foreach ($parsed['trait'] ?? [] as $key => $value) {
            $this->traitBonuses[(string) $key] = (int) $value;
        }

        $this->ageMultipliers = $this->floatMap($parsed['age_multiplier'] ?? []);

        $economy = $parsed['economy'] ?? [];
        $this->goldPerComplexPoint = (float) ($economy['gold_per_complex_point'] ?? 1.0);
        $this->dismissCompensationRatio = (float) ($economy['dismiss_compensation_ratio'] ?? 0.4);
        $this->trainerDismissCompensationRatio = (float) ($economy['trainer_dismiss_compensation_ratio'] ?? 0.3);
        $this->heroSalaryFactor = (float) ($economy['hero_salary_factor'] ?? 1.0);
        $this->trainerSalaryFactor = (float) ($economy['trainer_salary_factor'] ?? 1.2);
        $this->trainerMarketMultiplier = (float) ($economy['trainer_market_multiplier'] ?? 1.5);
    }

    /** @return array<string, float> */
    public function getPhysicalWeights(): array
    {
        return $this->physicalWeights;
    }

    /** @return array<string, float> */
    public function getMagicWeights(): array
    {
        return $this->magicWeights;
    }

    /** @return array<string, float> */
    public function getReferenceCeilings(): array
    {
        return $this->referenceCeilings;
    }

    public function getBaseOvrMax(): int
    {
        return $this->baseOvrMax;
    }

    public function getComplexRatingMax(): int
    {
        return $this->complexRatingMax;
    }

    public function getMasteryPointsPerTier(): int
    {
        return $this->masteryPointsPerTier;
    }

    public function getTraitBonus(?HeroTrait $trait): int
    {
        if (null === $trait) {
            return 0;
        }

        return $this->traitBonuses[$trait->value] ?? 0;
    }

    public function getAgeMultiplier(string $agePhase): float
    {
        $key = strtolower($agePhase);

        return $this->ageMultipliers[$key] ?? 1.0;
    }

    public function getGoldPerComplexPoint(): float
    {
        return $this->goldPerComplexPoint;
    }

    public function getDismissCompensationRatio(): float
    {
        return $this->dismissCompensationRatio;
    }

    public function getTrainerDismissCompensationRatio(): float
    {
        return $this->trainerDismissCompensationRatio;
    }

    public function getHeroSalaryFactor(): float
    {
        return $this->heroSalaryFactor;
    }

    public function getTrainerSalaryFactor(): float
    {
        return $this->trainerSalaryFactor;
    }

    public function getTrainerMarketMultiplier(): float
    {
        return $this->trainerMarketMultiplier;
    }

    /**
     * @param array<string, mixed> $map
     *
     * @return array<string, float>
     */
    private function floatMap(array $map): array
    {
        $result = [];
        foreach ($map as $key => $value) {
            $result[(string) $key] = (float) $value;
        }

        return $result;
    }
}
