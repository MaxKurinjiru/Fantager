<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\Hero\Hero;
use App\Entity\Team\Team;
use App\Enum\FacilityType;
use App\Enum\Race;
use App\Repository\Hero\HeroRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class GameExtension extends AbstractExtension
{
    public function __construct(
        private readonly \App\Service\Headquarters\HeadquartersService $hqService,
        private readonly HeroRepository $heroRepository,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('race_icon', $this->getRaceIcon(...)),
            new TwigFunction('hero_age_phase', $this->getAgePhase(...)),
            new TwigFunction('hero_age_phase_icon', $this->getAgePhaseIcon(...)),
            new TwigFunction('hq_upgrade_cost', $this->getHqUpgradeCost(...)),
            new TwigFunction('team_roster_limit', $this->getTeamRosterLimit(...)),
            new TwigFunction('team_hero_count', $this->getTeamHeroCount(...)),
        ];
    }

    public function getRaceIcon(Race|string|null $race): string
    {
        if (null === $race) {
            return '👤';
        }

        $resolvedRace = is_string($race) ? Race::tryFrom($race) : $race;

        if (null === $resolvedRace) {
            return '👤';
        }

        return match ($resolvedRace) {
            Race::Human => '👨',
            Race::Elf => '🧝',
            Race::Dwarf => '🧔',
            Race::Orc => '👹',
            Race::Undead => '💀',
            Race::Giant => '🧱',
            Race::Ent => '🌳',
            Race::Genie => '🧞',
        };
    }

    public function getAgePhase(Hero $hero): string
    {
        $age = $hero->getAge();

        // Age milestones by race
        $milestones = match ($hero->getRace()) {
            Race::Human => ['junior' => 20, 'prime' => 50, 'elder' => 80],
            Race::Elf => ['junior' => 80, 'prime' => 300, 'elder' => 800],
            Race::Dwarf => ['junior' => 30, 'prime' => 100, 'elder' => 250],
            Race::Orc => ['junior' => 16, 'prime' => 35, 'elder' => 60],
            Race::Undead => ['junior' => 80, 'prime' => 300, 'elder' => 800],
            Race::Giant => ['junior' => 25, 'prime' => 60, 'elder' => 150],
            Race::Ent => ['junior' => 50, 'prime' => 200, 'elder' => 1000],
            Race::Genie => ['junior' => 150, 'prime' => 500, 'elder' => 2000],
        };

        if ($age <= $milestones['junior']) {
            return 'Junior';
        }
        if ($age <= $milestones['prime']) {
            return 'Prime';
        }
        if ($age < $milestones['elder']) {
            return 'Veteran';
        }

        return 'Elder';
    }

    public function getAgePhaseIcon(Hero $hero): string
    {
        return match ($this->getAgePhase($hero)) {
            'Junior' => '🌱',
            'Prime' => '⚡',
            'Veteran' => '⚔️',
            'Elder' => '👴',
            default => '👤',
        };
    }

    public function getHqUpgradeCost(FacilityType $type, int $currentLevel): int
    {
        return $this->hqService->calculateUpgradeCost($type, $currentLevel);
    }

    public function getTeamRosterLimit(Team $team): int
    {
        return $this->hqService->getRosterLimit($team);
    }

    public function getTeamHeroCount(Team $team): int
    {
        return $this->heroRepository->count(['team' => $team]);
    }
}
