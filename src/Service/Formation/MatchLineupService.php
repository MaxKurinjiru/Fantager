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
use App\Exception\UserFacingException;
use App\Service\Team\TeamRosterService;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Kickoff lineup.
 *
 * Player: the formation assigned to the match, or the default when none is assigned.
 * That formation must already be match-ready. An incomplete one forfeits. There is
 * no fall-through to the default and no kickoff roster fill. Players may explicitly
 * fill gaps before kickoff via proposeRosterFill() (writes a match-specific formation).
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
        private readonly FormationLineupScorer $lineupScorer,
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
     *     can_fill: bool,
     *     uses_default_fallback: bool,
     *     slots: list<array{position: string, hero_id: int|null, hero_name: string|null, status: string|null, issue: string}>,
     *     fill_preview: list<array{position: string, hero_id: int, hero_name: string, action: string}>
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
                'can_fill' => false,
                'uses_default_fallback' => false,
                'slots' => $this->describeSlots($team, $default),
                'fill_preview' => [],
            ];
        }

        $chosen = $assigned ?? $this->formationService->findDefaultForTeam($team);
        $ready = null !== $chosen && $this->isMatchReady($chosen);
        $filled = $ready ? null : $this->buildFilledClone($team, $chosen);
        $canFill = !$ready && null !== $filled;

        return [
            'source' => null !== $assigned ? 'assigned' : (null !== $chosen ? 'default' : 'none'),
            'formation_name' => $chosen?->getName(),
            'ready_slots' => $ready ? 6 : (null !== $chosen ? $this->countCombatReadySlots($chosen) : 0),
            'will_forfeit' => !$ready,
            'will_autofill' => false,
            'can_fill' => $canFill,
            'uses_default_fallback' => false,
            'slots' => $this->describeSlots($team, $chosen),
            'fill_preview' => $this->describeFillPreview($team, $chosen, $filled),
        ];
    }

    /**
     * Build a 6-slot payload that fills empty, duplicate, and unready slots from the combat-ready roster.
     * Does not persist. Player-only — kickoff still never auto-fills a player lineup.
     *
     * @return array{
     *     name: string,
     *     approach: FormationApproach,
     *     tactics_defaults: array<string, mixed>,
     *     slots: list<array{position: string, hero_id: int, strategy: array<string, mixed>, spell_priorities: array<mixed>}>
     * }
     */
    public function proposeRosterFill(Team $team, ?Formation $template): array
    {
        if ($team->isNpc()) {
            throw new UserFacingException('error.formation_fill_player_only');
        }

        if (null !== $template && $template->getTeam()->getId() !== $team->getId()) {
            $template = null;
        }

        if (null !== $template && $this->isMatchReady($template)) {
            throw new UserFacingException('error.formation_fill_already_ready');
        }

        $filled = $this->buildFilledClone($team, $template);
        if (null === $filled) {
            throw new UserFacingException('error.formation_fill_cannot_complete');
        }

        $slots = [];
        foreach ($filled->getSlots() as $slot) {
            $hero = $slot->getHero();
            if (!$hero instanceof Hero || null === $hero->getId()) {
                throw new UserFacingException('error.formation_fill_cannot_complete');
            }

            $slots[] = [
                'position' => $slot->getPosition()->value,
                'hero_id' => $hero->getId(),
                'strategy' => $slot->getStrategy(),
                'spell_priorities' => $slot->getSpellPriorities(),
            ];
        }

        return [
            'name' => $filled->getName(),
            'approach' => $filled->getApproach(),
            'tactics_defaults' => $filled->getTacticsDefaults(),
            'slots' => $slots,
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
        $count = 0;
        foreach ($this->describeSlots($formation->getTeam(), $formation) as $slot) {
            if ('ok' === $slot['issue']) {
                ++$count;
            }
        }

        return $count;
    }

    /**
     * @return list<array{position: string, hero_id: int|null, hero_name: string|null, status: string|null, issue: string}>
     */
    private function describeSlots(Team $team, ?Formation $formation): array
    {
        $byPosition = [];
        if ($formation instanceof Formation) {
            foreach ($formation->getSlots() as $slot) {
                $byPosition[$slot->getPosition()->value] = $slot->getHero();
            }
        }

        $used = [];
        $rows = [];
        foreach (FormationPosition::cases() as $position) {
            $hero = $byPosition[$position->value] ?? null;
            if (!$hero instanceof Hero) {
                $rows[] = [
                    'position' => $position->value,
                    'hero_id' => null,
                    'hero_name' => null,
                    'status' => null,
                    'issue' => 'empty',
                ];
                continue;
            }

            $heroId = $hero->getId();
            $issue = 'ok';
            if (null === $heroId) {
                $issue = 'empty';
            } elseif (isset($used[$heroId])) {
                $issue = 'duplicate';
            } elseif (!$hero->isCombatant()) {
                $issue = 'trainer';
            } elseif (!$this->isUsableHero($hero, $team)) {
                $issue = 'unready';
            }

            if (null !== $heroId) {
                $used[$heroId] = true;
            }

            $rows[] = [
                'position' => $position->value,
                'hero_id' => $heroId,
                'hero_name' => $hero->getName(),
                'status' => $hero->getStatus()->value,
                'issue' => $issue,
            ];
        }

        return $rows;
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

        /** @var array<string, array{hero: Hero|null, strategy: array<string, mixed>, spell_priorities: array<mixed>}> $chosen */
        $chosen = [];
        $used = [];
        $emptyFront = [];
        $emptyBack = [];

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
                'strategy' => $templateSlot?->getStrategy() ?? [],
                'spell_priorities' => [],
            ];
            if ($this->lineupScorer->isFrontRow($position)) {
                $emptyFront[] = $position;
            } else {
                $emptyBack[] = $position;
            }
        }

        $remaining = [];
        foreach ($bench as $candidate) {
            $candidateId = $candidate->getId();
            if (null === $candidateId || isset($used[$candidateId])) {
                continue;
            }
            $remaining[] = $candidate;
        }

        $chosen = $this->placeByScore($chosen, $used, $remaining, $emptyFront, true);
        $chosen = $this->placeByScore($chosen, $used, $remaining, $emptyBack, false);

        foreach (FormationPosition::cases() as $position) {
            if (!$chosen[$position->value]['hero'] instanceof Hero) {
                return null;
            }
        }

        $clone = new Formation();
        $clone->setTeam($team);
        $clone->setName($template instanceof Formation ? $template->getName() : 'Kickoff');
        $clone->setApproach($template?->getApproach() ?? FormationApproach::Balanced);
        $clone->setTacticsDefaults($template?->getTacticsDefaults() ?? []);
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
     * @param array<string, array{hero: Hero|null, strategy: array<string, mixed>, spell_priorities: array<mixed>}> $chosen
     * @param array<int, true>                                                                                      $used
     * @param list<Hero>                                                                                            $remaining
     * @param list<FormationPosition>                                                                               $positions
     *
     * @return array<string, array{hero: Hero|null, strategy: array<string, mixed>, spell_priorities: array<mixed>}>
     */
    private function placeByScore(
        array $chosen,
        array &$used,
        array &$remaining,
        array $positions,
        bool $front,
    ): array {
        $pool = [];
        foreach ($remaining as $candidate) {
            $candidateId = $candidate->getId();
            if (null === $candidateId || isset($used[$candidateId])) {
                continue;
            }
            $pool[] = $candidate;
        }
        $pool = $this->lineupScorer->sortForRow($pool, $front);

        foreach ($positions as $index => $position) {
            $hero = $pool[$index] ?? null;
            if (!$hero instanceof Hero) {
                return $chosen;
            }
            $heroId = $hero->getId();
            if (null === $heroId) {
                return $chosen;
            }
            $used[$heroId] = true;
            $chosen[$position->value] = [
                'hero' => $hero,
                'strategy' => $chosen[$position->value]['strategy'] ?? [],
                'spell_priorities' => $chosen[$position->value]['spell_priorities'] ?? [],
            ];
        }

        $remaining = array_values(array_filter(
            $remaining,
            static fn (Hero $hero): bool => null !== $hero->getId() && !isset($used[$hero->getId()]),
        ));

        return $chosen;
    }

    /**
     * @return list<array{position: string, hero_id: int, hero_name: string, action: string}>
     */
    private function describeFillPreview(Team $team, ?Formation $template, ?Formation $filled): array
    {
        if (!$filled instanceof Formation) {
            return [];
        }

        $before = [];
        foreach ($this->describeSlots($team, $template) as $row) {
            $before[$row['position']] = 'ok' === $row['issue'] ? $row['hero_id'] : null;
        }

        $byPosition = [];
        foreach ($filled->getSlots() as $slot) {
            $byPosition[$slot->getPosition()->value] = $slot;
        }

        $preview = [];
        foreach (FormationPosition::cases() as $positionEnum) {
            $slot = $byPosition[$positionEnum->value] ?? null;
            $hero = $slot?->getHero();
            if (!$hero instanceof Hero || null === $hero->getId()) {
                continue;
            }
            $position = $positionEnum->value;
            $preview[] = [
                'position' => $position,
                'hero_id' => $hero->getId(),
                'hero_name' => $hero->getName(),
                'action' => ($before[$position] ?? null) === $hero->getId() ? 'kept' : 'filled',
            ];
        }

        return $preview;
    }

    private function isUsableHero(Hero $hero, Team $team): bool
    {
        return $hero->getTeam()->getId() === $team->getId()
            && $this->teamRosterService->isCombatReady($hero);
    }
}
