<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\Auth\User;
use App\Entity\Headquarters\Headquarters;
use App\Entity\Hero\Hero;
use App\Entity\Team\Team;
use App\Enum\FacilityType;
use App\Enum\HeroTrait;
use App\Enum\Race;
use App\Repository\Hero\HeroRepository;
use App\Service\Community\CommunityService;
use App\Service\Config\RaceConfig;
use App\Service\Economy\FinancialCrisisService;
use App\Service\Notification\NotificationService;
use App\Service\Team\TeamRosterService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class GameExtension extends AbstractExtension
{
    public function __construct(
        private readonly \App\Service\Headquarters\HeadquartersService $hqService,
        private readonly HeroRepository $heroRepository,
        private readonly CommunityService $communityService,
        private readonly NotificationService $notificationService,
        private readonly RaceConfig $raceConfig,
        private readonly FinancialCrisisService $financialCrisisService,
        private readonly TeamRosterService $teamRosterService,
        private readonly Security $security,
        private readonly \App\Service\Combat\CombatStatCalculator $combatStatCalculator,
        private readonly \App\Service\Hero\HeroRatingCalculator $heroRatingCalculator,
        private readonly \App\Service\Hero\HeroSalaryService $heroSalaryService,
        private readonly \App\Service\Economy\TeamPayrollService $teamPayrollService,
        private readonly TranslatorInterface $translator,
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
            new TwigFunction('unread_notification_count', $this->getUnreadNotificationCount(...)),
            new TwigFunction('hero_mortality_threshold', $this->getHeroMortalityThreshold(...)),
            new TwigFunction('hero_at_mortality_threshold', $this->isHeroAtMortalityThreshold(...)),
            new TwigFunction('team_financial_crisis', $this->getTeamFinancialCrisis(...)),
            new TwigFunction('hq_downgrade_refund', $this->getHqDowngradeRefund(...)),
            new TwigFunction('team_combat_ready_count', $this->getTeamCombatReadyCount(...)),
            new TwigFunction('hero_can_leave_roster', $this->canHeroLeaveRoster(...)),
            new TwigFunction('hero_combat_stats', $this->getHeroCombatStats(...)),
            new TwigFunction('hero_rating', $this->getHeroRating(...)),
            new TwigFunction('hero_gold_value', $this->getHeroGoldValue(...)),
            new TwigFunction('hero_market_price', $this->getHeroMarketPrice(...)),
            new TwigFunction('hero_weekly_salary', $this->getHeroWeeklySalary(...)),
            new TwigFunction('team_weekly_payroll', $this->getTeamWeeklyPayroll(...)),
            new TwigFunction('hero_trait_js_labels', $this->getHeroTraitJsLabels(...)),
        ];
    }

    public function getUnreadMailCount(): int
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return 0;
        }

        return $this->communityService->countUnreadInbox($user);
    }

    public function getUnreadNotificationCount(): int
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return 0;
        }

        return $this->notificationService->countUnread($user);
    }

    public function getHeroMortalityThreshold(Hero $hero): int
    {
        return $this->raceConfig->getMortalityThreshold($hero->getRace());
    }

    public function isHeroAtMortalityThreshold(Hero $hero): bool
    {
        return $this->raceConfig->isAtOrAboveMortalityThreshold($hero->getRace(), $hero->getAge());
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
        return $this->raceConfig->resolveAgePhase($hero->getRace(), $hero->getAge());
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
        return $this->heroRepository->countActiveCombatantsByTeam($team);
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

    public function getHeroCombatStats(Hero $hero): \App\ValueObject\Combat\DerivedCombatStats
    {
        return $this->combatStatCalculator->calculate($hero);
    }

    public function getHeroRating(Hero $hero): \App\ValueObject\Hero\HeroRating
    {
        return $this->heroRatingCalculator->calculate($hero);
    }

    public function getHeroGoldValue(Hero $hero): int
    {
        return $this->heroRatingCalculator->estimateGoldValue($hero);
    }

    public function getHeroMarketPrice(Hero $hero): int
    {
        return $this->heroRatingCalculator->estimateMarketPrice($hero);
    }

    public function getHeroWeeklySalary(Hero $hero): int
    {
        return $this->heroSalaryService->calculateWeeklySalary($hero);
    }

    /**
     * @return array{
     *     heroes_due: int,
     *     trainers_due: int,
     *     total: int,
     *     hero_count: int,
     *     trainer_count: int,
     * }
     */
    public function getTeamWeeklyPayroll(Team $team): array
    {
        return $this->teamPayrollService->calculateWeeklyPayrollBreakdown($team);
    }

    /**
     * Translated trait labels for Stimulus controllers (summoning, marketplace).
     *
     * @return array<string, array{name: string, desc: string, category: string, icon: string}>
     */
    public function getHeroTraitJsLabels(): array
    {
        $labels = [];
        foreach (HeroTrait::cases() as $trait) {
            $key = $trait->value;
            $labels[$key] = [
                'name' => $this->translator->trans('heroes.traits.'.$key.'.name'),
                'desc' => $this->translator->trans('heroes.traits.'.$key.'.desc'),
                'category' => $trait->getCategory(),
                'icon' => $trait->getIcon(),
            ];
        }

        return $labels;
    }
}
