<?php

declare(strict_types=1);

namespace App\Service\Combat;

use App\Enum\FormationApproach;
use App\Enum\FormationPosition;
use App\ValueObject\Combat\CombatantSnapshot;
use App\ValueObject\Combat\CombatMatchRequest;
use App\ValueObject\Combat\CombatSide;
use App\ValueObject\Combat\CombatSimulationResult;
use App\ValueObject\Combat\DerivedCombatStats;

/**
 * Combat engine (Milestone 6.1a).
 *
 * Implements round-by-round turn loops, L0 AI heuristics,
 * combat formula calculations, and conditional traits.
 */
class CombatEngine
{
    public const ENGINE_VERSION = 1;

    public function simulate(CombatMatchRequest $request): CombatSimulationResult
    {
        $runState = $this->initializeRunState($request);
        $round = 1;

        while ('simulating' === $runState['status'] && $round <= 200) {
            $this->simulateRound($runState, $round);
            ++$round;
        }

        if ('simulating' === $runState['status']) {
            // Hard stop at 200 rounds, force completion
            $this->completeBattleInRunState($runState, 200);
        }

        return new CombatSimulationResult(
            (int) $runState['scoreA'],
            (int) $runState['scoreB'],
            (int) $runState['seed'],
            (array) $runState['combatLog']
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function initializeRunState(CombatMatchRequest $request): array
    {
        $seed = $request->getSeed();
        $rngState = $seed > 0 ? $seed : 1;

        return [
            'seed' => $seed,
            'rngState' => $rngState,
            'engineVersion' => $request->getEngineVersion(),
            'matchType' => $request->getMatchType()->value,
            'teams' => [
                'a' => $this->teamMeta($request->getSideA()),
                'b' => $this->teamMeta($request->getSideB()),
            ],
            'lineup' => [
                'a' => $this->lineupMeta($request->getSideA()),
                'b' => $this->lineupMeta($request->getSideB()),
            ],
            'sideA' => $this->serializeSideToArray($request->getSideA()),
            'sideB' => $this->serializeSideToArray($request->getSideB()),
            'events' => [
                ['t' => 0, 'type' => 'match_start'],
            ],
            'scoreA' => 0,
            'scoreB' => 0,
            'status' => 'simulating',
            'currentRound' => 0,
            'combatLog' => [],
        ];
    }

    /**
     * @param array<string, mixed> $runState
     *
     * @return list<array<string, mixed>>
     */
    public function simulateRound(array &$runState, int $round): array
    {
        if ('simulating' !== $runState['status']) {
            return [];
        }

        $runState['currentRound'] = $round;
        $rngState = (int) $runState['rngState'];

        $roundEvents = [];
        $roundEvents[] = [
            't' => count($runState['events']) + count($roundEvents),
            'type' => 'round_start',
            'round' => $round,
        ];

        // Gather active combatants
        $activeCombatants = [];
        foreach (['a', 'b'] as $sideKey) {
            $sideData = 'a' === $sideKey ? $runState['sideA'] : $runState['sideB'];
            foreach ($sideData['combatants'] as $combatant) {
                if ($combatant['currentHp'] > 0) {
                    $initRoll = $this->nextInt($rngState, -3, 3);
                    $initiative = $combatant['derived']['baseInitiative'] + $initRoll;

                    $activeCombatants[] = [
                        'side' => $sideKey,
                        'heroId' => $combatant['heroId'],
                        'slot' => $combatant['slot'],
                        'initiative' => $initiative,
                    ];
                }
            }
        }

        // Sort by initiative descending, ties broken deterministically by heroId
        usort($activeCombatants, function (array $x, array $y) {
            $diff = $y['initiative'] <=> $x['initiative'];
            if (0 !== $diff) {
                return $diff;
            }

            return $x['heroId'] <=> $y['heroId'];
        });

        // Resolve turns
        foreach ($activeCombatants as $actorInfo) {
            $actorSide = $actorInfo['side'];
            $actorSlot = $actorInfo['slot'];
            $actor = $this->getCombatant($runState, $actorSide, $actorSlot);

            if (null === $actor || $actor['currentHp'] <= 0) {
                continue;
            }

            // Check if opposing team has any living heroes
            $opposingSideKey = 'a' === $actorSide ? 'b' : 'a';
            if (!$this->hasLivingCombatants($runState, $opposingSideKey)) {
                break;
            }

            // Turn start
            $roundEvents[] = [
                't' => count($runState['events']) + count($roundEvents),
                'type' => 'turn_start',
                'side' => $actorSide,
                'slot' => $actorSlot,
                'hero_id' => $actor['heroId'],
                'initiative' => $actorInfo['initiative'],
            ];

            // Target selection based on actor's side's approach
            $approachVal = 'a' === $actorSide ? $runState['sideA']['approach'] : $runState['sideB']['approach'];
            $approach = FormationApproach::from($approachVal);

            $targetSlots = $this->getTargetSlotsForApproach($approach);
            $targetSlot = null;
            $target = null;

            foreach ($targetSlots as $slotEnum) {
                $candidate = $this->getCombatant($runState, $opposingSideKey, $slotEnum->value);
                if (null !== $candidate && $candidate['currentHp'] > 0) {
                    $targetSlot = $slotEnum->value;
                    $target = $candidate;
                    break;
                }
            }

            if (null === $target || null === $targetSlot) {
                continue;
            }

            // Perform basic physical attack
            $roundEvents[] = [
                't' => count($runState['events']) + count($roundEvents),
                'type' => 'attack',
                'target_side' => $opposingSideKey,
                'target_slot' => $targetSlot,
            ];

            // 1. Accuracy and dodge check
            $acc = (float) $actor['derived']['accuracyPercent'];
            $dodge = (float) $target['derived']['dodgePercent'];

            // Clutch accuracy bonus
            $actorMaxHp = max(1, (int) ($actor['derived']['maxHp'] ?? 1));
            if (($actor['currentHp'] / $actorMaxHp) <= 0.30 && null !== $actor['derived']['clutchHpThreshold']) {
                $acc += (float) ($actor['derived']['clutchAccuracyBonus'] ?? 0.0);
            }

            // Dodge cap (50%)
            $dodge = min(50.0, $dodge);

            $hitChance = $acc - $dodge;
            $hitRoll = $this->nextInt($rngState, 1, 100);

            if ($hitRoll > $hitChance) {
                $roundEvents[] = [
                    't' => count($runState['events']) + count($roundEvents),
                    'type' => 'miss',
                ];
                continue;
            }

            // 2. Critical hit check
            $critChance = (float) $actor['derived']['critPercent'];
            $critChance = min(50.0, $critChance);

            $critRoll = $this->nextInt($rngState, 1, 100);
            $isCrit = $critRoll <= $critChance;

            // 3. Damage calculation
            $baseAtk = (float) $actor['derived']['physicalAttack'];
            $variance = $actor['derived']['isConsistentDamage'] ? 0 : $this->nextInt($rngState, -10, 10);
            $damage = $baseAtk * (1.0 + $variance / 100.0);

            if ($isCrit) {
                $critMult = (float) ($actor['derived']['critDamageMultiplier'] ?? 1.5);
                $damage *= $critMult;
                $roundEvents[] = [
                    't' => count($runState['events']) + count($roundEvents),
                    'type' => 'crit',
                ];
            } else {
                $roundEvents[] = [
                    't' => count($runState['events']) + count($roundEvents),
                    'type' => 'hit',
                ];
            }

            // 4. Target armor reduction
            $armorVal = (float) $target['derived']['armorValue'];
            $targetMaxHp = max(1, (int) ($target['derived']['maxHp'] ?? 1));
            if (($target['currentHp'] / $targetMaxHp) <= 0.30 && null !== $target['derived']['clutchHpThreshold']) {
                $armorVal *= (float) ($target['derived']['clutchArmorMultiplier'] ?? 1.0);
            }

            $armorReduction = $armorVal / ($armorVal + 100.0);

            // Glass Jaw
            $incomingMult = 1.0;
            if (($target['currentHp'] / $targetMaxHp) <= 0.50 && null !== $target['derived']['glassJawHpThreshold']) {
                $incomingMult = (float) ($target['derived']['incomingDamageMultiplier'] ?? 1.10);
            }

            $netDamage = (int) round($damage * (1.0 - $armorReduction) * $incomingMult);
            $netDamage = max(1, $netDamage);

            // 5. Apply damage
            $target['currentHp'] = max(0, $target['currentHp'] - $netDamage);
            $this->updateCombatant($runState, $opposingSideKey, $targetSlot, $target);

            $roundEvents[] = [
                't' => count($runState['events']) + count($roundEvents),
                'type' => 'damage',
                'target_side' => $opposingSideKey,
                'target_slot' => $targetSlot,
                'amount' => $netDamage,
                'hp_after' => $target['currentHp'],
                'source' => 'physical',
            ];

            // 6. Handle KO
            if ($target['currentHp'] <= 0) {
                if ('a' === $opposingSideKey) {
                    ++$runState['scoreB'];
                } else {
                    ++$runState['scoreA'];
                }

                $roundEvents[] = [
                    't' => count($runState['events']) + count($roundEvents),
                    'type' => 'ko',
                    'side' => $opposingSideKey,
                    'slot' => $targetSlot,
                    'hero_id' => $target['heroId'],
                ];

                $roundEvents[] = [
                    't' => count($runState['events']) + count($roundEvents),
                    'type' => 'kill_score',
                    'score_a' => $runState['scoreA'],
                    'score_b' => $runState['scoreB'],
                ];
            }
        }

        $runState['events'] = array_merge($runState['events'], $roundEvents);
        $runState['rngState'] = $rngState;

        // Check if match is completed
        $aAlive = $this->hasLivingCombatants($runState, 'a');
        $bAlive = $this->hasLivingCombatants($runState, 'b');

        if (!$aAlive || !$bAlive) {
            $runState['status'] = 'completed';
            $this->completeBattleInRunState($runState, $round);
        }

        return $roundEvents;
    }

    /**
     * @param array<string, mixed> $runState
     */
    private function completeBattleInRunState(array &$runState, int $rounds): void
    {
        $runState['status'] = 'completed';

        $runState['events'][] = [
            't' => count($runState['events']),
            'type' => 'match_end',
            'score_a' => $runState['scoreA'],
            'score_b' => $runState['scoreB'],
            'rounds' => $rounds,
        ];

        $runState['combatLog'] = [
            'version' => 1,
            'simulator' => 'combat_engine',
            'seed' => $runState['seed'],
            'engine_version' => $runState['engineVersion'],
            'match_type' => $runState['matchType'],
            'teams' => $runState['teams'],
            'lineup' => $runState['lineup'],
            'events' => $runState['events'],
            'result' => [
                'score_a' => $runState['scoreA'],
                'score_b' => $runState['scoreB'],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $runState
     *
     * @return array<string, mixed>|null
     */
    private function getCombatant(array $runState, string $sideKey, string $slot): ?array
    {
        $sideKeyReal = 'a' === $sideKey ? 'sideA' : 'sideB';
        if (isset($runState[$sideKeyReal]['combatants'])) {
            foreach ($runState[$sideKeyReal]['combatants'] as $combatant) {
                if (null !== $combatant && $combatant['slot'] === $slot) {
                    return $combatant;
                }
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $runState
     * @param array<string, mixed> $combatant
     */
    private function updateCombatant(array &$runState, string $sideKey, string $slot, array $combatant): void
    {
        $sideKeyReal = 'a' === $sideKey ? 'sideA' : 'sideB';
        if (isset($runState[$sideKeyReal]['combatants'])) {
            foreach ($runState[$sideKeyReal]['combatants'] as $key => $existing) {
                if (null !== $existing && $existing['slot'] === $slot) {
                    $runState[$sideKeyReal]['combatants'][$key] = $combatant;

                    return;
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $runState
     */
    private function hasLivingCombatants(array $runState, string $sideKey): bool
    {
        $side = 'a' === $sideKey ? $runState['sideA'] : $runState['sideB'];
        foreach ($side['combatants'] as $combatant) {
            if ($combatant['currentHp'] > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<FormationPosition>
     */
    private function getTargetSlotsForApproach(FormationApproach $approach): array
    {
        if (FormationApproach::Aggressive === $approach) {
            return [
                FormationPosition::Back1,
                FormationPosition::Back2,
                FormationPosition::Back3,
                FormationPosition::Front1,
                FormationPosition::Front2,
                FormationPosition::Front3,
            ];
        }

        return [
            FormationPosition::Front1,
            FormationPosition::Front2,
            FormationPosition::Front3,
            FormationPosition::Back1,
            FormationPosition::Back2,
            FormationPosition::Back3,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeSideToArray(CombatSide $side): array
    {
        return [
            'teamId' => $side->getTeamId(),
            'formationId' => $side->getFormationId(),
            'approach' => $side->getApproach()->value,
            'combatants' => array_map(fn (CombatantSnapshot $c) => $this->serializeCombatantToArray($c), $side->getCombatants()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeCombatantToArray(CombatantSnapshot $c): array
    {
        return [
            'heroId' => $c->getHeroId(),
            'name' => $c->getName(),
            'slot' => $c->getSlot()->value,
            'race' => $c->getRace()->value,
            'level' => $c->getLevel(),
            'form' => $c->getForm(),
            'fatigue' => $c->getFatigue(),
            'morale' => $c->getMorale(),
            'derived' => $this->serializeDerivedStatsToArray($c->getDerived()),
            'strategy' => $c->getStrategy(),
            'spellPriorities' => $c->getSpellPriorities(),
            'spells' => $c->getSpells(),
            'currentHp' => $c->getDerived()->getCurrentHp(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeDerivedStatsToArray(DerivedCombatStats $d): array
    {
        return [
            'maxHp' => $d->getMaxHp(),
            'currentHp' => $d->getCurrentHp(),
            'physicalAttack' => $d->getPhysicalAttack(),
            'spellPower' => $d->getSpellPower(),
            'armorValue' => $d->getArmorValue(),
            'physicalDamageReduction' => $d->getPhysicalDamageReduction(),
            'magicResistance' => $d->getMagicResistance(),
            'magicDamageReduction' => $d->getMagicDamageReduction(),
            'baseInitiative' => $d->getBaseInitiative(),
            'accuracyPercent' => $d->getAccuracyPercent(),
            'dodgePercent' => $d->getDodgePercent(),
            'critPercent' => $d->getCritPercent(),
            'critDamageMultiplier' => $d->getCritDamageMultiplier(),
            'moraleDecayMultiplier' => $d->getMoraleDecayMultiplier(),
            'arenaRevenueBonus' => $d->getArenaRevenueBonus(),
            'clutchHpThreshold' => $d->getClutchHpThreshold(),
            'clutchAccuracyBonus' => $d->getClutchAccuracyBonus(),
            'clutchArmorMultiplier' => $d->getClutchArmorMultiplier(),
            'glassJawHpThreshold' => $d->getGlassJawHpThreshold(),
            'incomingDamageMultiplier' => $d->getIncomingDamageMultiplier(),
            'isConsistentDamage' => $d->isConsistentDamage(),
            'ignoresRaceSynergy' => $d->ignoresRaceSynergy(),
        ];
    }

    /**
     * @return array{team_id: int, formation_id: int|null, approach: string}
     */
    private function teamMeta(CombatSide $side): array
    {
        return [
            'team_id' => $side->getTeamId(),
            'formation_id' => $side->getFormationId(),
            'approach' => $side->getApproach()->value,
        ];
    }

    /**
     * @return array<string, array{hero_id: int, name: string}>
     */
    private function lineupMeta(CombatSide $side): array
    {
        $lineup = [];
        foreach ($side->getCombatants() as $combatant) {
            $lineup[$combatant->getSlot()->value] = [
                'hero_id' => $combatant->getHeroId(),
                'name' => $combatant->getName(),
            ];
        }

        return $lineup;
    }

    private function nextInt(int &$state, int $min, int $max): int
    {
        $state = (int) (($state * 1103515245 + 12345) & 0x7FFFFFFF);

        return $min + ($state % ($max - $min + 1));
    }
}
