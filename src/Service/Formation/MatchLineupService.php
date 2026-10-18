<?php

declare(strict_types=1);

namespace App\Service\Formation;

use App\Entity\Formation\Formation;
use App\Entity\Formation\FormationSlot;
use App\Entity\Hero\Hero;
use App\Entity\League\LeagueFixture;
use App\Entity\Team\Team;
use App\Enum\FormationApproach;
use App\Enum\FormationPosition;
use App\Service\Team\TeamRosterService;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Kickoff lineup.
 *
 * Player: the formation assigned to the match, or the default when none is assigned.
 * That formation must already be match-ready. An incomplete one forfeits. There is
 * no fall-through to the default and no roster fill.
 *
 * NPC: always the default formation. NpcTacticsSimulator rewrites that formation
 * and does not assign a fixture lineup. If the default is short, empty slots are
 * filled from the combat-ready roster.
 */
class MatchLineupService
{
    public function __construct(
        private readonly FormationService $formationService,
        private readonly TeamRosterService $teamRosterService,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function canField(Team $team, ?Formation $assigned): bool
    {
        return null !== $this->plan($team, $assigned);
    }

    /**
     * Read-only kickoff assessment. Does not persist an auto-filled copy.
     *
     * Player forfeit: the assigned formation, or the default when none is assigned,
     * is not match-ready. NPC forfeit: the default cannot be completed from the roster.
     *
     * @return array{
     *     source: string,
     *     formation_name: string|null,
     *     ready_slots: int,
     *     will_forfeit: bool,
     *     will_autofill: bool,
     *     uses_default_fallback: bool
     * }
     */
    public function preview(Team $team, ?Formation $assigned): array
    {
        if (null !== $assigned && $assigned->getTeam()->getId() !== $team->getId()) {
            $assigned = null;
        }

        if ($team->isNpc()) {
            $default = $this->formationService->findDefaultForTeam($team);
            $ready = null !== $default && $this->isMatchReady($default);
            $canField = null !== $this->plan($team, $assigned);

            return [
                'source' => $ready ? 'default' : 'roster',
                'formation_name' => $default?->getName(),
                'ready_slots' => $ready ? 6 : (null !== $default ? $this->countCombatReadySlots($default) : 0),
                'will_forfeit' => !$canField,
                'will_autofill' => !$ready && $canField,
                'uses_default_fallback' => false,
            ];
        }

        $chosen = $assigned ?? $this->formationService->findDefaultForTeam($team);
        $ready = null !== $chosen && $this->isMatchReady($chosen);

        return [
            'source' => null !== $assigned ? 'assigned' : (null !== $chosen ? 'default' : 'none'),
            'formation_name' => $chosen?->getName(),
            'ready_slots' => $ready ? 6 : (null !== $chosen ? $this->countCombatReadySlots($chosen) : 0),
            'will_forfeit' => !$ready,
            'will_autofill' => false,
            'uses_default_fallback' => false,
        ];
    }

    /**
     * Return a match-ready formation (6 unique combat-ready heroes).
     * Auto-filled copies are persisted as temporary (league: sourceFixture set for cleanup).
     */
    public function resolveForKickoff(Team $team, ?Formation $assigned, ?LeagueFixture $sourceFixture = null): ?Formation
    {
        $plan = $this->plan($team, $assigned);
        if (null === $plan) {
            return null;
        }

        $formation = $plan['formation'];
        if (!$plan['persist']) {
            return $formation;
        }

        $formation->setSourceFixture($sourceFixture);
        $this->em->persist($formation);
        $this->em->flush();

        return $formation;
    }

    /**
     * Delete a kickoff auto-fill that is not the player's assigned formation.
     * League temps with sourceFixture are cleaned by fixture completion.
     */
    public function discardEphemeralKickoff(?Formation $used, ?Formation $assigned): void
    {
        if (!$used instanceof Formation || !$used->isTemporary()) {
            return;
        }

        if (null !== $used->getSourceFixture()) {
            return;
        }

        if (null !== $assigned && null !== $assigned->getId() && $assigned->getId() === $used->getId()) {
            return;
        }

        $this->formationService->deleteTemporary($used);
    }

    public function isMatchReady(Formation $formation): bool
    {
        return null !== $this->uniqueCombatReadyHeroes($formation);
    }

    /**
     * @return array{formation: Formation, persist: bool}|null
     */
    private function plan(Team $team, ?Formation $assigned): ?array
    {
        if (null !== $assigned && $assigned->getTeam()->getId() !== $team->getId()) {
            $assigned = null;
        }

        if ($team->isNpc()) {
            $default = $this->formationService->findDefaultForTeam($team);
            if (null !== $default && $this->isMatchReady($default)) {
                return ['formation' => $default, 'persist' => false];
            }

            $filled = $this->buildFilledClone($team, $default);
            if (null === $filled) {
                return null;
            }

            return ['formation' => $filled, 'persist' => true];
        }

        $chosen = $assigned ?? $this->formationService->findDefaultForTeam($team);
        if (null !== $chosen && $this->isMatchReady($chosen)) {
            return ['formation' => $chosen, 'persist' => false];
        }

        return null;
    }

    /**
     * @return array<int, true>|null hero ids when the formation has 6 unique combat-ready heroes
     */
    private function uniqueCombatReadyHeroes(Formation $formation): ?array
    {
        $team = $formation->getTeam();
        $byPosition = [];
        foreach ($formation->getSlots() as $slot) {
            $byPosition[$slot->getPosition()->value] = $slot->getHero();
        }

        $used = [];
        foreach (FormationPosition::cases() as $position) {
            $hero = $byPosition[$position->value] ?? null;
            if (!$hero instanceof Hero) {
                return null;
            }

            $heroId = $hero->getId();
            if (null === $heroId || isset($used[$heroId]) || !$this->isUsableHero($hero, $team)) {
                return null;
            }

            $used[$heroId] = true;
        }

        return 6 === count($used) ? $used : null;
    }

    private function countCombatReadySlots(Formation $formation): int
    {
        $team = $formation->getTeam();
        $byPosition = [];
        foreach ($formation->getSlots() as $slot) {
            $byPosition[$slot->getPosition()->value] = $slot->getHero();
        }

        $used = [];
        $count = 0;
        foreach (FormationPosition::cases() as $position) {
            $hero = $byPosition[$position->value] ?? null;
            if (!$hero instanceof Hero) {
                continue;
            }

            $heroId = $hero->getId();
            if (null === $heroId || isset($used[$heroId]) || !$this->isUsableHero($hero, $team)) {
                continue;
            }

            $used[$heroId] = true;
            ++$count;
        }

        return $count;
    }

    private function buildFilledClone(Team $team, ?Formation $template): ?Formation
    {
        $bench = $this->teamRosterService->listCombatReadyHeroes($team);
        $slotsByPosition = [];
        if ($template instanceof Formation) {
            foreach ($template->getSlots() as $slot) {
                $slotsByPosition[$slot->getPosition()->value] = $slot;
            }
        }

        $fallbackStrategy = $this->fallbackStrategy($template);
        /** @var array<string, array{hero: Hero|null, strategy: array<string, mixed>, spell_priorities: array<mixed>}> $chosen */
        $chosen = [];
        $used = [];

        foreach (FormationPosition::cases() as $position) {
            $templateSlot = $slotsByPosition[$position->value] ?? null;
            $hero = $templateSlot?->getHero();
            if ($hero instanceof Hero) {
                $heroId = $hero->getId();
                if (null !== $heroId && !isset($used[$heroId]) && $this->isUsableHero($hero, $team)) {
                    $used[$heroId] = true;
                    $chosen[$position->value] = [
                        'hero' => $hero,
                        'strategy' => $templateSlot->getStrategy(),
                        'spell_priorities' => $templateSlot->getSpellPriorities(),
                    ];
                    continue;
                }
            }

            $chosen[$position->value] = [
                'hero' => null,
                'strategy' => $templateSlot?->getStrategy() ?? $fallbackStrategy,
                'spell_priorities' => [],
            ];
        }

        foreach (FormationPosition::cases() as $position) {
            if ($chosen[$position->value]['hero'] instanceof Hero) {
                continue;
            }

            foreach ($bench as $candidate) {
                $candidateId = $candidate->getId();
                if (null === $candidateId || isset($used[$candidateId])) {
                    continue;
                }

                $used[$candidateId] = true;
                $chosen[$position->value]['hero'] = $candidate;
                break;
            }

            if (!$chosen[$position->value]['hero'] instanceof Hero) {
                return null;
            }
        }

        $clone = new Formation();
        $clone->setTeam($team);
        $clone->setName($template instanceof Formation ? $template->getName() : 'Kickoff');
        $clone->setApproach($template?->getApproach() ?? FormationApproach::Balanced);
        $clone->setIsDefault(false);
        $clone->setIsTemporary(true);

        foreach (FormationPosition::cases() as $position) {
            $slot = new FormationSlot();
            $slot->setPosition($position);
            $slot->setHero($chosen[$position->value]['hero']);
            $slot->setStrategy($chosen[$position->value]['strategy']);
            $slot->setSpellPriorities($chosen[$position->value]['spell_priorities']);
            $clone->addSlot($slot);
        }

        return $clone;
    }

    /**
     * @return array<string, mixed>
     */
    private function fallbackStrategy(?Formation $template): array
    {
        if (!$template instanceof Formation) {
            return [];
        }

        foreach ($template->getSlots() as $slot) {
            $goal = $slot->getStrategy()['movement_goal'] ?? null;
            if (is_string($goal) && '' !== $goal) {
                return ['movement_goal' => $goal];
            }
        }

        return [];
    }

    private function isUsableHero(Hero $hero, Team $team): bool
    {
        return $hero->getTeam()->getId() === $team->getId()
            && $this->teamRosterService->isCombatReady($hero);
    }
}
