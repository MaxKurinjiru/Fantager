<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\Auth\User;
use App\Entity\Headquarters\Headquarters;
use App\Entity\Hero\Hero;
use App\Entity\Team\Team;
use App\Enum\FacilityType;
use App\Enum\Race;
use App\Repository\Hero\HeroRepository;
use App\Service\Community\CommunityService;
use App\Service\Economy\FinancialCrisisService;
use App\Service\Team\TeamRosterService;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class GameExtension extends AbstractExtension
{
    public function __construct(
        private readonly \App\Service\Headquarters\HeadquartersService $hqService,
        private readonly HeroRepository $heroRepository,
        private readonly CommunityService $communityService,
        private readonly FinancialCrisisService $financialCrisisService,
        private readonly TeamRosterService $teamRosterService,
        private readonly Security $security,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('race_icon', $this->getRaceIcon(...)),
            new TwigFunction('hero_age_phase', $this->getAgePhase(...)),
            new TwigFunction('hero_age_phase_icon', $this->getAgePhaseIcon(...)),
            new TwigFunction('hq_upgrade_cost', $this->getHqUpgradeCost(...)),
            new TwigFunction('hq_weekly_maintenance', $this->getHqWeeklyMaintenance(...)),
            new TwigFunction('team_roster_limit', $this->getTeamRosterLimit(...)),
            new TwigFunction('team_hero_count', $this->getTeamHeroCount(...)),
            new TwigFunction('unread_mail_count', $this->getUnreadMailCount(...)),
            new TwigFunction('team_financial_crisis', $this->getTeamFinancialCrisis(...)),
            new TwigFunction('hq_downgrade_refund', $this->getHqDowngradeRefund(...)),
            new TwigFunction('team_combat_ready_count', $this->getTeamCombatReadyCount(...)),
            new TwigFunction('hero_can_leave_roster', $this->canHeroLeaveRoster(...)),
        ];
    }

    public function getUnreadMailCount(): int
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return 0;
        }

        $team = $user->getTeam();
        if (!$team) {
            return 0;
        }

        return $this->communityService->countUnreadInbox($team);
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

    public function getHqUpgradeCost(FacilityType $type, int $currentLevel, int $totalLevel): int
    {
        return $this->hqService->calculateUpgradeCost($type, $currentLevel, $totalLevel);
    }

    /**
     * @return array{total: int, hq: int, facilities: int}
     */
    public function getHqWeeklyMaintenance(?Headquarters $hq): array
    {
        if (null === $hq) {
            return ['total' => 0, 'hq' => 0, 'facilities' => 0];
        }

        return $this->hqService->calculateWeeklyMaintenanceBreakdown($hq);
    }

    public function getTeamRosterLimit(Team $team): int
    {
        return $this->hqService->getRosterLimit($team);
    }

    public function getTeamHeroCount(Team $team): int
    {
        return $this->heroRepository->count(['team' => $team]);
    }

    /**
     * @return array<string, mixed>
     */
    public function getTeamFinancialCrisis(Team $team): array
    {
        return $this->financialCrisisService->getStatus($team);
    }

    public function getHqDowngradeRefund(FacilityType $type, int $currentLevel, int $totalLevel): int
    {
        return $this->hqService->calculateDowngradeRefund($type, $currentLevel, $totalLevel);
    }

    public function getTeamCombatReadyCount(Team $team): int
    {
        return $this->teamRosterService->countCombatReadyHeroes($team);
    }

    public function canHeroLeaveRoster(Team $team, Hero $hero): bool
    {
        return $this->teamRosterService->canRemoveCombatReadyHero($team, $hero);
    }
}
