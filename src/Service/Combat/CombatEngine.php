<?php

declare(strict_types=1);

namespace App\Service\Combat;

use App\Enum\FormationApproach;
use App\Enum\FormationPosition;
use App\Enum\Race;
use App\Enum\StatusEffect;
use App\Service\Config\CombatAiL0Config;
use App\Service\Config\StatusEffectConfig;
use App\ValueObject\Combat\CombatantSnapshot;
use App\ValueObject\Combat\CombatMatchRequest;
use App\ValueObject\Combat\CombatSide;
use App\ValueObject\Combat\CombatSimulationResult;
use App\ValueObject\Combat\DerivedCombatStats;
use App\ValueObject\Combat\HexCoordinate;

/**
 * Combat engine (Milestone 6.1a).
 *
 * Implements round-by-round turn loops, L0 AI heuristics,
 * combat formula calculations, and conditional traits.
 */
class CombatEngine
{
    public const ENGINE_VERSION = 9;

    /** Hard stop for one-shot and wave/resume combat paths. */
    public const MAX_ROUNDS = 200;

    private HexGridService $hexGridService;

    private CombatAiL0Config $l0Config;

    private StatusEffectConfig $statusEffectConfig;

    public function __construct(
        ?HexGridService $hexGridService = null,
        ?CombatAiL0Config $l0Config = null,
        ?StatusEffectConfig $statusEffectConfig = null,
    ) {
        $projectDir = \dirname(__DIR__, 3);
        $this->hexGridService = $hexGridService ?? new HexGridService();
        $this->l0Config = $l0Config ?? new CombatAiL0Config($projectDir);
        $this->statusEffectConfig = $statusEffectConfig ?? new StatusEffectConfig($projectDir);
    }

    public function simulate(CombatMatchRequest $request): CombatSimulationResult
    {
        $runState = $this->initializeRunState($request);
        $round = 1;

        while ('simulating' === $runState['status'] && $round <= self::MAX_ROUNDS) {
            $this->simulateRound($runState, $round);
            ++$round;
        }

        $this->forceCompleteAtRoundCap($runState);

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
            'sideA' => $this->serializeSideToArray($request->getSideA(), 'a'),
            'sideB' => $this->serializeSideToArray($request->getSideB(), 'b'),
            'events' => [
                ['t' => 0, 'type' => 'match_start'],
            ],
            'scoreA' => 0,
            'scoreB' => 0,
            'status' => 'simulating',
            'currentRound' => 0,
            'combatLog' => [],
            'killedHeroIds' => [],
            'killsByHeroId' => [],
            'contributionByHeroId' => [],
            'itemHitCounts' => [],
            'grid' => [
                'width' => HexGridService::GRID_WIDTH,
                'height' => HexGridService::GRID_HEIGHT,
            ],
        ];
    }

    /**
     * @param array<mixed> $runState
     *
     * @return array<array<string, mixed>>
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

        // 0. Process Status Effect Ticks
        $this->processStatusEffectTicks($runState, $roundEvents);

        // Gather active combatants
        $activeCombatants = [];
        foreach (['a', 'b'] as $sideKey) {
            $sideData = 'a' === $sideKey ? $runState['sideA'] : $runState['sideB'];
            foreach ($sideData['combatants'] as $combatant) {
                if ($combatant['currentHp'] > 0) {
                    $initRoll = $this->nextInt($rngState, -3, 3);
                    $initiative = $this->initiativeForSort($combatant, $initRoll);

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

            // 1. Decrement active cooldowns, then regen mana (still runs if the turn is skipped)
            foreach ($actor['cooldowns'] as $spellId => $cd) {
                if ($cd > 0) {
                    $actor['cooldowns'][$spellId] = $cd - 1;
                }
            }
            $this->regenMana($actor);
            $this->updateCombatant($runState, $actorSide, $actorSlot, $actor);

            $roundEvents[] = [
                't' => count($runState['events']) + count($roundEvents),
                'type' => 'turn_start',
                'side' => $actorSide,
                'slot' => $actorSlot,
                'hero_id' => $actor['heroId'],
                'initiative' => $actorInfo['initiative'],
                'mana' => (int) ($actor['currentMana'] ?? 0),
            ];

            // 2. Check for freeze/stun (skip turn & interrupt)
            $hasSkipTurnEffect = false;
            foreach ($actor['statusEffects'] as $eff) {
                if ('freeze' === $eff['effect'] || 'stun' === $eff['effect']) {
                    $hasSkipTurnEffect = true;
                    break;
                }
            }
            if ($hasSkipTurnEffect) {
                if (null !== ($actor['queuedAction'] ?? null)) {
                    $roundEvents[] = [
                        't' => count($runState['events']) + count($roundEvents),
                        'type' => 'action_interrupted',
                        'side' => $actorSide,
                        'slot' => $actorSlot,
                        'hero_id' => $actor['heroId'],
                        'reason' => 'stun_or_freeze',
                    ];
                    $actor['queuedAction'] = null;
                    $actor['preparationRemaining'] = 0;
                    $this->updateCombatant($runState, $actorSide, $actorSlot, $actor);
                }
                $roundEvents[] = [
                    't' => count($runState['events']) + count($roundEvents),
                    'type' => 'skip_turn',
                    'side' => $actorSide,
                    'slot' => $actorSlot,
                    'hero_id' => $actor['heroId'],
                ];
                continue;
            }

            // Check for silence (interrupts queued spells)
            $isSilenced = false;
            foreach ($actor['statusEffects'] as $eff) {
                if ('silence' === $eff['effect']) {
                    $isSilenced = true;
                    break;
                }
            }
            if ($isSilenced && null !== ($actor['queuedAction'] ?? null) && 'spell' === $actor['queuedAction']['type']) {
                $roundEvents[] = [
                    't' => count($runState['events']) + count($roundEvents),
                    'type' => 'action_interrupted',
                    'side' => $actorSide,
                    'slot' => $actorSlot,
                    'hero_id' => $actor['heroId'],
                    'reason' => 'silence',
                ];
                $actor['queuedAction'] = null;
                $actor['preparationRemaining'] = 0;
                $this->updateCombatant($runState, $actorSide, $actorSlot, $actor);
            }

            // 3. Process Queued Actions (Execution Phase)
            if (null !== ($actor['queuedAction'] ?? null)) {
                --$actor['preparationRemaining'];

                if ($actor['preparationRemaining'] > 0) {
                    $roundEvents[] = [
                        't' => count($runState['events']) + count($roundEvents),
                        'type' => 'preparing_action_tick',
                        'side' => $actorSide,
                        'slot' => $actorSlot,
                        'hero_id' => $actor['heroId'],
                        'action_type' => $actor['queuedAction']['type'],
                        'rounds_remaining' => $actor['preparationRemaining'],
                    ];
                    $this->updateCombatant($runState, $actorSide, $actorSlot, $actor);
                    continue;
                }

                $queuedAction = $actor['queuedAction'];
                $actor['queuedAction'] = null;
                $this->updateCombatant($runState, $actorSide, $actorSlot, $actor);

                $targetSide = $queuedAction['target_side'];
                $targetSlot = $queuedAction['target_slot'];
                $target = $this->getCombatant($runState, $targetSide, $targetSlot);

                // Option B: Fumble if target is dead/KO'd!
                if (null === $target || $target['currentHp'] <= 0) {
                    $roundEvents[] = [
                        't' => count($runState['events']) + count($roundEvents),
                        'type' => 'action_fumble',
                        'side' => $actorSide,
                        'slot' => $actorSlot,
                        'hero_id' => $actor['heroId'],
                        'target_side' => $targetSide,
                        'target_slot' => $targetSlot,
                    ];
                    continue;
                }

                if ('spell' === $queuedAction['type']) {
                    $spell = null;
                    foreach ($actor['spells'] as $s) {
                        if ($s['id'] === $queuedAction['spell_id']) {
                            $spell = $s;
                            break;
                        }
                    }

                    if (null !== $spell) {
                        $this->executeSpell($runState, $actorSide, $actorSlot, $actor, $targetSlot, $target, $spell, $roundEvents, $rngState);
                        $cdKey = (string) $spell['id'];
                        $actor = $this->getCombatant($runState, $actorSide, $actorSlot);
                        $actor['cooldowns'][$cdKey] = $spell['cooldown'];
                        $this->updateCombatant($runState, $actorSide, $actorSlot, $actor);
                    }
                } else {
                    $this->executePhysicalAttack($runState, $actorSide, $actorSlot, $actor, $targetSide, $targetSlot, $target, $roundEvents, $rngState);
                }
                continue;
            }

            // 4. Plan New Actions (Planning Phase)
            $approachVal = 'a' === $actorSide ? $runState['sideA']['approach'] : $runState['sideB']['approach'];
            $approach = FormationApproach::from($approachVal);

            [$targetSlot, $target] = $this->resolveTarget($runState, $opposingSideKey, $actor, $approach);

            if (null === $target || null === $targetSlot) {
                continue;
            }

            // Movement only happens while planning. A queued action (preparationRemaining > 0)
            // already continued above, so a charging unit stays still.
            $this->spendMovementAp($runState, $actor, $actorSide, $actorSlot, $target, $roundEvents);

            $castSpell = null;
            $spellPriorityTarget = 'enemy';

            if (!$isSilenced && !empty($actor['spellPriorities']) && !empty($actor['spells'])) {
                foreach ($actor['spellPriorities'] as $priority) {
                    $spellId = $priority['spell_id'] ?? null;
                    $spell = null;
                    foreach ($actor['spells'] as $s) {
                        if ($s['id'] === $spellId) {
                            $spell = $s;
                            break;
                        }
                    }

                    if (null === $spell) {
                        continue;
                    }

                    if (!$this->spellIsReady($actor, $spell)) {
                        continue;
                    }

                    $when = $priority['when'] ?? 'always';
                    $threshold = $priority['threshold'] ?? 100;
                    $conditionMet = false;

                    if ('always' === $when) {
                        $conditionMet = true;
                    } elseif ('self_hp_below' === $when) {
                        $maxHp = $actor['derived']['maxHp'];
                        $pct = ($actor['currentHp'] / $maxHp) * 100;
                        if ($pct < $threshold) {
                            $conditionMet = true;
                        }
                    } elseif ('ally_hp_below' === $when) {
                        $allies = 'a' === $actorSide ? $runState['sideA']['combatants'] : $runState['sideB']['combatants'];
                        foreach ($allies as $ally) {
                            if ($ally['currentHp'] > 0) {
                                $allyMaxHp = $ally['derived']['maxHp'];
                                $allyPct = ($ally['currentHp'] / $allyMaxHp) * 100;
                                if ($allyPct < $threshold) {
                                    $conditionMet = true;
                                    break;
                                }
                            }
                        }
                    }
                    // Unknown when values (enemy_status, …) stay unmet — skip this priority entry.

                    if ($conditionMet) {
                        $castSpell = $spell;
                        $spellPriorityTarget = $priority['target'] ?? 'enemy';
                        break;
                    }
                }
            } elseif (!$isSilenced && [] === ($actor['spellPriorities'] ?? []) && !empty($actor['spells'])) {
                $picked = $this->pickL0Spell($runState, $actorSide, $actor, $approach);
                if (null !== $picked) {
                    $castSpell = $picked['spell'];
                    $spellPriorityTarget = $picked['target'];
                }
            }

            if (null !== $castSpell) {
                $spellTarget = null;
                $spellTargetSlot = null;

                if ('lowest_hp_ally' === $spellPriorityTarget) {
                    $allies = 'a' === $actorSide ? $runState['sideA']['combatants'] : $runState['sideB']['combatants'];
                    $lowestHpAlly = null;
                    foreach ($allies as $ally) {
                        if ($ally['currentHp'] > 0) {
                            if (null === $lowestHpAlly || $ally['currentHp'] < $lowestHpAlly['currentHp']) {
                                $lowestHpAlly = $ally;
                            }
                        }
                    }
                    if (null !== $lowestHpAlly) {
                        $spellTarget = $lowestHpAlly;
                        $spellTargetSlot = $lowestHpAlly['slot'];
                    }
                } elseif ('self' === $spellPriorityTarget) {
                    $spellTarget = $actor;
                    $spellTargetSlot = $actorSlot;
                }

                if (null === $spellTarget) {
                    $spellTarget = $target;
                    $spellTargetSlot = $targetSlot;
                }

                $duration = 1 + ($castSpell['tier'] ?? 1);
                $targetSide = ('lowest_hp_ally' === $spellPriorityTarget || 'self' === $spellPriorityTarget) ? $actorSide : $opposingSideKey;
                $manaCost = (int) ($castSpell['mana_cost'] ?? 0);
                $actor['currentMana'] = max(0, ((int) ($actor['currentMana'] ?? 0)) - $manaCost);

                $actor['queuedAction'] = [
                    'type' => 'spell',
                    'spell_id' => $castSpell['id'],
                    'spell_name' => $castSpell['name'],
                    'target_side' => $targetSide,
                    'target_slot' => $spellTargetSlot,
                ];
                $actor['preparationRemaining'] = $duration;

                $roundEvents[] = [
                    't' => count($runState['events']) + count($roundEvents),
                    'type' => 'plan_action',
                    'side' => $actorSide,
                    'slot' => $actorSlot,
                    'hero_id' => $actor['heroId'],
                    'action_type' => 'spell',
                    'spell_name' => $castSpell['name'],
                    'target_side' => $targetSide,
                    'target_slot' => $spellTargetSlot,
                    'duration' => $duration,
                    'mana_cost' => $manaCost,
                    'mana_after' => (int) $actor['currentMana'],
                ];

                $this->updateCombatant($runState, $actorSide, $actorSlot, $actor);
                continue;
            }

            $weaponType = $actor['weaponSubType'] ?? null;
            $actorHexAfterMove = new HexCoordinate((int) ($actor['q'] ?? 0), (int) ($actor['r'] ?? 0));
            $targetHexLive = new HexCoordinate((int) ($target['q'] ?? 0), (int) ($target['r'] ?? 0));
            $attackReach = $this->hexGridService->getWeaponReach($weaponType);
            $attackDistance = $this->hexGridService->engagementDistance(
                $actorHexAfterMove,
                $this->combatantRadius($actor),
                $targetHexLive,
                $this->combatantRadius($target),
            );
            $isRangedWeapon = $this->hexGridService->isRangedWeapon($weaponType);

            $canEngage = $attackDistance <= $attackReach
                && (!$isRangedWeapon || $attackDistance >= 2)
                && (!$isRangedWeapon || $this->hexGridService->hasLineOfSight(
                    $actorHexAfterMove,
                    $targetHexLive,
                    $this->collectBlockingHexes($runState, $actorSide, $actorSlot, $opposingSideKey, $targetSlot)
                ));

            if (!$canEngage) {
                // Still closing distance / seeking LoS — no attack queued this turn
                continue;
            }

            if ('bow' === $weaponType) {
                $duration = 2;
            } elseif ('crossbow' === $weaponType) {
                $duration = 3;
            } else {
                $duration = 1;
            }

            $actor['queuedAction'] = [
                'type' => 'attack',
                'target_side' => $opposingSideKey,
                'target_slot' => $targetSlot,
            ];
            $actor['preparationRemaining'] = $duration;

            $roundEvents[] = [
                't' => count($runState['events']) + count($roundEvents),
                'type' => 'plan_action',
                'side' => $actorSide,
                'slot' => $actorSlot,
                'hero_id' => $actor['heroId'],
                'action_type' => 'attack',
                'target_side' => $opposingSideKey,
                'target_slot' => $targetSlot,
                'duration' => $duration,
                'weapon_type' => $weaponType,
            ];

            $this->updateCombatant($runState, $actorSide, $actorSlot, $actor);
            continue;
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
     * Force match_end with current kill scores when the round cap is hit.
     *
     * @param array<string, mixed> $runState
     */
    public function forceCompleteAtRoundCap(array &$runState): void
    {
        if ('simulating' !== ($runState['status'] ?? null)) {
            return;
        }

        $this->completeBattleInRunState($runState, self::MAX_ROUNDS);
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
            'grid' => $runState['grid'] ?? [
                'width' => HexGridService::GRID_WIDTH,
                'height' => HexGridService::GRID_HEIGHT,
            ],
            'teams' => $runState['teams'],
            'lineup' => $runState['lineup'],
            'events' => $runState['events'],
            'result' => [
                'score_a' => $runState['scoreA'],
                'score_b' => $runState['scoreB'],
                'killed_hero_ids' => $runState['killedHeroIds'],
                'kills_by_hero_id' => $runState['killsByHeroId'] ?? [],
                'contribution_by_hero_id' => $runState['contributionByHeroId'] ?? [],
                'item_hit_counts' => $runState['itemHitCounts'],
                'rng_state' => $runState['rngState'],
            ],
        ];
    }

    /**
     * Spend the whole AP budget on movement, 2 AP per hex. Stops early when the goal is reached.
     *
     * @param array<string, mixed>        $runState
     * @param array<string, mixed>        $actor
     * @param array<string, mixed>        $target
     * @param array<array<string, mixed>> $roundEvents
     */
    private function spendMovementAp(
        array &$runState,
        array &$actor,
        string $actorSide,
        string $actorSlot,
        array $target,
        array &$roundEvents,
    ): void {
        $movementGoal = (string) ($actor['strategy']['movement_goal'] ?? 'advance_to_melee');
        $reach = $this->hexGridService->getWeaponReach($actor['weaponSubType'] ?? null);
        $apLeft = $this->hexGridService->calculateActionPoints((int) ($actor['derived']['baseInitiative'] ?? 0));
        $stepCost = $this->hexGridService->calculateMovementCost(1, $actor['armorSubType'] ?? null);

        while ($apLeft >= $stepCost['apCost']) {
            $actorHex = new HexCoordinate((int) ($actor['q'] ?? 0), (int) ($actor['r'] ?? 0));
            $targetHex = new HexCoordinate((int) ($target['q'] ?? 0), (int) ($target['r'] ?? 0));
            $nextHex = $this->chooseMoveHex(
                $runState,
                $actorHex,
                $targetHex,
                $movementGoal,
                $actorSide,
                $actorSlot,
                $this->combatantRadius($actor),
                $this->combatantRadius($target),
                $reach,
            );
            if (null === $nextHex || $actorHex->equals($nextHex)) {
                break;
            }

            $roundEvents[] = [
                't' => count($runState['events']) + count($roundEvents),
                'type' => 'move',
                'side' => $actorSide,
                'slot' => $actorSlot,
                'hero_id' => $actor['heroId'],
                'from_q' => $actorHex->q,
                'from_r' => $actorHex->r,
                'to_q' => $nextHex->q,
                'to_r' => $nextHex->r,
                'q' => $nextHex->q,
                'r' => $nextHex->r,
                'ap_cost' => $stepCost['apCost'],
                'fatigue_generated' => $stepCost['fatigueGenerated'],
            ];
            $actor['q'] = $nextHex->q;
            $actor['r'] = $nextHex->r;
            $actor['fatigue'] = (int) ($actor['fatigue'] ?? 0) + $stepCost['fatigueGenerated'];
            $this->updateCombatant($runState, $actorSide, $actorSlot, $actor);
            $apLeft -= $stepCost['apCost'];
        }
    }

    /**
     * @param array<string, mixed> $runState
     */
    private function chooseMoveHex(
        array $runState,
        HexCoordinate $actorHex,
        HexCoordinate $targetHex,
        string $movementGoal,
        string $actorSide,
        string $actorSlot,
        int $actorRadius,
        int $targetRadius,
        int $reach,
    ): ?HexCoordinate {
        if ('seek_cover_ranged' === $movementGoal) {
            $engagement = $this->hexGridService->engagementDistance($actorHex, $actorRadius, $targetHex, $targetRadius);
            if ($engagement < $reach) {
                return $this->retreatHex($runState, $actorHex, $targetHex, $actorSide, $actorSlot, $actorRadius, $targetRadius, $reach);
            }
            if ($engagement > $reach) {
                $path = $this->hexGridService->findPath(
                    $actorHex,
                    $targetHex,
                    $this->collectOccupiedHexes($runState, $actorSide, $actorSlot),
                    $actorRadius,
                    $targetRadius,
                    $reach,
                );

                return $path[0] ?? null;
            }

            return null;
        }

        $goalHex = $this->resolveMovementGoalHex($runState, $targetHex, $movementGoal, $actorSide, $actorSlot, $targetRadius);
        $isTacticalGoal = in_array($movementGoal, ['flank_rear', 'protect_backline'], true);
        $distanceToGoal = $isTacticalGoal
            ? $actorHex->distance($goalHex)
            : $this->hexGridService->engagementDistance($actorHex, $actorRadius, $targetHex, $targetRadius);
        if ($distanceToGoal <= $reach) {
            return null;
        }

        $path = $this->hexGridService->findPath(
            $actorHex,
            $goalHex,
            $this->collectOccupiedHexes($runState, $actorSide, $actorSlot),
            $actorRadius,
            $isTacticalGoal ? 0 : $targetRadius,
            $isTacticalGoal ? 0 : $reach,
        );

        return $path[0] ?? null;
    }

    /**
     * One step that increases engagement, preferring a landing spot at weapon range.
     *
     * @param array<string, mixed> $runState
     */
    private function retreatHex(
        array $runState,
        HexCoordinate $actorHex,
        HexCoordinate $targetHex,
        string $actorSide,
        string $actorSlot,
        int $actorRadius,
        int $targetRadius,
        int $reach,
    ): ?HexCoordinate {
        $occupiedMap = [];
        foreach ($this->collectOccupiedHexes($runState, $actorSide, $actorSlot) as $hex) {
            $occupiedMap[$hex->key()] = true;
        }

        $current = $this->hexGridService->engagementDistance($actorHex, $actorRadius, $targetHex, $targetRadius);
        $best = null;
        $bestScore = \PHP_INT_MAX;

        foreach ($actorHex->getNeighbors() as $neighbor) {
            if (!$this->footprintIsFree($neighbor, $actorRadius, $occupiedMap)) {
                continue;
            }
            $next = $this->hexGridService->engagementDistance($neighbor, $actorRadius, $targetHex, $targetRadius);
            if ($next <= $current) {
                continue;
            }
            $overshoot = $next > $reach ? 1000 + ($next - $reach) : ($reach - $next);
            $towardHome = 'a' === $actorSide ? $neighbor->q : -$neighbor->q;
            $score = ($overshoot * 1000) + $towardHome;
            if ($score < $bestScore) {
                $bestScore = $score;
                $best = $neighbor;
            }
        }

        return $best;
    }

    /**
     * @param array<string, true> $occupiedMap
     */
    private function footprintIsFree(HexCoordinate $origin, int $radius, array $occupiedMap): bool
    {
        if (!$this->hexGridService->footprintFits($origin, $radius)) {
            return false;
        }
        foreach ($this->hexGridService->getFootprint($origin, $radius) as $hex) {
            if (isset($occupiedMap[$hex->key()])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Resolve target hex based on spatial movement goal (advance_to_melee, flank_rear, protect_backline).
     * seek_cover_ranged is handled in chooseMoveHex: it holds weapon range instead of a fixed hex.
     *
     * @param array<string, mixed> $runState
     */
    private function resolveMovementGoalHex(
        array $runState,
        HexCoordinate $targetHex,
        string $movementGoal,
        string $actorSide,
        string $actorSlot,
        int $targetRadius = 0,
    ): HexCoordinate {
        $maxQ = HexGridService::GRID_WIDTH - 1;

        if ('flank_rear' === $movementGoal) {
            $rearQ = 'a' === $actorSide ? $targetHex->q + 1 + $targetRadius : $targetHex->q - 1 - $targetRadius;

            return new HexCoordinate(max(0, min($maxQ, $rearQ)), $targetHex->r);
        }
        if ('protect_backline' === $movementGoal) {
            $ally = $this->findLowestHpAlly($runState, $actorSide, $actorSlot);
            if (null !== $ally) {
                $allyHex = new HexCoordinate((int) ($ally['q'] ?? 0), (int) ($ally['r'] ?? 0));
                $allyRadius = $this->combatantRadius($ally);
                $guardQ = 'a' === $actorSide ? $allyHex->q + 1 + $allyRadius : $allyHex->q - 1 - $allyRadius;

                return new HexCoordinate(max(0, min($maxQ, $guardQ)), $allyHex->r);
            }
        }

        return $targetHex;
    }

    /**
     * @param array<string, mixed> $runState
     *
     * @return list<HexCoordinate>
     */
    private function collectOccupiedHexes(array $runState, string $excludeSide, string $excludeSlot): array
    {
        $occupied = [];
        foreach (['a', 'b'] as $sideKey) {
            $sideData = 'a' === $sideKey ? ($runState['sideA'] ?? []) : ($runState['sideB'] ?? []);
            foreach ($sideData['combatants'] ?? [] as $combatant) {
                if (($combatant['currentHp'] ?? 0) <= 0) {
                    continue;
                }
                if ($sideKey === $excludeSide && ($combatant['slot'] ?? null) === $excludeSlot) {
                    continue;
                }
                foreach ($this->combatantFootprint($combatant) as $hex) {
                    $occupied[] = $hex;
                }
            }
        }

        return $occupied;
    }

    /**
     * @param array<string, mixed> $runState
     *
     * @return array<string, mixed>|null
     */
    private function findLowestHpAlly(array $runState, string $actorSide, string $actorSlot): ?array
    {
        $sideData = 'a' === $actorSide ? ($runState['sideA'] ?? []) : ($runState['sideB'] ?? []);
        $lowestHp = \PHP_INT_MAX;
        $best = null;

        foreach ($sideData['combatants'] ?? [] as $combatant) {
            if (($combatant['slot'] ?? null) === $actorSlot) {
                continue;
            }
            $hp = (int) ($combatant['currentHp'] ?? 0);
            if ($hp <= 0 || $hp >= $lowestHp) {
                continue;
            }
            $lowestHp = $hp;
            $best = $combatant;
        }

        return $best;
    }

    /**
     * @param array<string, mixed> $runState
     *
     * @return list<HexCoordinate>
     */
    private function collectBlockingHexes(
        array $runState,
        ?string $excludeSide = null,
        ?string $excludeSlot = null,
        ?string $excludeSide2 = null,
        ?string $excludeSlot2 = null,
    ): array {
        $blocking = [];
        foreach (['a', 'b'] as $sideKey) {
            $sideData = 'a' === $sideKey ? ($runState['sideA'] ?? []) : ($runState['sideB'] ?? []);
            foreach ($sideData['combatants'] ?? [] as $combatant) {
                if (($combatant['currentHp'] ?? 0) <= 0) {
                    continue;
                }
                $slot = $combatant['slot'] ?? null;
                if (null !== $excludeSide && $sideKey === $excludeSide && $slot === $excludeSlot) {
                    continue;
                }
                if (null !== $excludeSide2 && $sideKey === $excludeSide2 && $slot === $excludeSlot2) {
                    continue;
                }
                foreach ($this->combatantFootprint($combatant) as $hex) {
                    $blocking[] = $hex;
                }
            }
        }

        return $blocking;
    }

    /**
     * @param array<string, mixed> $combatant
     */
    private function combatantRadius(array $combatant): int
    {
        $race = Race::tryFrom((string) ($combatant['race'] ?? ''));

        return $race?->hexFootprintRadius() ?? 0;
    }

    /**
     * @param array<string, mixed> $combatant
     *
     * @return list<HexCoordinate>
     */
    private function combatantFootprint(array $combatant): array
    {
        $origin = new HexCoordinate((int) ($combatant['q'] ?? 0), (int) ($combatant['r'] ?? 0));
        $hexes = [];
        foreach ($this->hexGridService->getFootprint($origin, $this->combatantRadius($combatant)) as $hex) {
            if ($this->hexGridService->isWithinGrid($hex)) {
                $hexes[] = $hex;
            }
        }

        return $hexes;
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
     * L0 spell when the slot has no spell_priorities.
     *
     * Heal with the strongest ready restore spell when any living ally
     * (including the caster) is under the approach HP threshold. Otherwise
     * the lowest-tier ready enhance whose status is not already on the
     * caster (self). Otherwise the highest-tier ready harm spell. If none
     * of those is ready, the highest-tier ready hinder spell.
     *
     * @param array<string, mixed> $runState
     * @param array<string, mixed> $actor
     *
     * @return array{spell: array<string, mixed>, target: string}|null
     */
    private function pickL0Spell(array $runState, string $actorSide, array $actor, FormationApproach $approach): ?array
    {
        $ready = [];
        foreach ($actor['spells'] as $spell) {
            if ($this->spellIsReady($actor, $spell)) {
                $ready[] = $spell;
            }
        }

        if ([] === $ready) {
            return null;
        }

        $threshold = $this->l0Config->healThresholdPercent($approach);
        if ($this->anyAllyBelowHpPercent($runState, $actorSide, $threshold)) {
            $heal = $this->strongestReadySpell($ready, 'restore');
            if (null !== $heal) {
                return ['spell' => $heal, 'target' => 'lowest_hp_ally'];
            }
        }

        $buff = $this->lowestTierReadyMissingBuff($actor, $ready);
        if (null !== $buff) {
            return ['spell' => $buff, 'target' => 'self'];
        }

        $offense = $this->strongestReadySpell($ready, 'harm');
        if (null !== $offense) {
            return ['spell' => $offense, 'target' => 'enemy'];
        }

        $hinder = $this->strongestReadySpell($ready, 'hinder');
        if (null !== $hinder) {
            return ['spell' => $hinder, 'target' => 'enemy'];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $actor
     * @param array<string, mixed> $spell
     */
    private function spellIsReady(array $actor, array $spell): bool
    {
        if (!empty($spell['requires_magical_weapon'])) {
            $weaponType = $actor['weaponSubType'] ?? null;
            $isEnt = isset($actor['race']) && 'ent' === $actor['race'];
            if (!$isEnt && 'wand' !== $weaponType && 'staff' !== $weaponType) {
                return false;
            }
        }

        $cdKey = (string) ($spell['id'] ?? '');
        $currentCd = $actor['cooldowns'][$cdKey] ?? 0;
        if ($currentCd > 0) {
            return false;
        }

        $cost = (int) ($spell['mana_cost'] ?? 0);
        $mana = (int) ($actor['currentMana'] ?? $actor['derived']['maxMana'] ?? 0);

        return $mana >= $cost;
    }

    /**
     * @param array<string, mixed> $combatant
     */
    private function regenMana(array &$combatant): void
    {
        $maxMana = (int) ($combatant['derived']['maxMana'] ?? 0);
        if ($maxMana <= 0) {
            return;
        }

        $regen = max(0, (int) ($combatant['derived']['manaRegen'] ?? 0));
        $current = (int) ($combatant['currentMana'] ?? $maxMana);
        $combatant['currentMana'] = min($maxMana, $current + $regen);
    }

    /**
     * @param list<array<string, mixed>> $spells
     *
     * @return array<string, mixed>|null
     */
    private function strongestReadySpell(array $spells, string $type): ?array
    {
        $best = null;
        foreach ($spells as $spell) {
            if (($spell['type'] ?? '') !== $type) {
                continue;
            }
            if (null === $best) {
                $best = $spell;
                continue;
            }
            $tierDiff = ((int) ($spell['tier'] ?? 1)) <=> ((int) ($best['tier'] ?? 1));
            if ($tierDiff > 0 || (0 === $tierDiff && ((int) $spell['id']) < ((int) $best['id']))) {
                $best = $spell;
            }
        }

        return $best;
    }

    /**
     * Lowest-tier ready enhance whose apply_effect is not already on the caster.
     * Fast T1 buffs win over long T5 casts. Enhance with no status is eligible.
     *
     * @param array<string, mixed>       $actor
     * @param list<array<string, mixed>> $spells
     *
     * @return array<string, mixed>|null
     */
    private function lowestTierReadyMissingBuff(array $actor, array $spells): ?array
    {
        $best = null;
        foreach ($spells as $spell) {
            if ('enhance' !== ($spell['type'] ?? '')) {
                continue;
            }
            $effect = SpellPayload::fromEffects(\is_array($spell['effects'] ?? null) ? $spell['effects'] : [])->applyEffect;
            if (null !== $effect && $this->combatantHasStatus($actor, $effect->value)) {
                continue;
            }
            if (null === $best) {
                $best = $spell;
                continue;
            }
            $tierDiff = ((int) ($spell['tier'] ?? 1)) <=> ((int) ($best['tier'] ?? 1));
            if ($tierDiff < 0 || (0 === $tierDiff && ((int) $spell['id']) < ((int) $best['id']))) {
                $best = $spell;
            }
        }

        return $best;
    }

    /**
     * @param array<string, mixed> $combatant
     */
    private function combatantHasStatus(array $combatant, string $effect): bool
    {
        foreach ($combatant['statusEffects'] ?? [] as $status) {
            if ($effect === ($status['effect'] ?? '')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $runState
     */
    private function anyAllyBelowHpPercent(array $runState, string $actorSide, int $threshold): bool
    {
        $allies = 'a' === $actorSide ? $runState['sideA']['combatants'] : $runState['sideB']['combatants'];
        foreach ($allies as $ally) {
            if (($ally['currentHp'] ?? 0) <= 0) {
                continue;
            }
            $maxHp = (int) ($ally['derived']['maxHp'] ?? 0);
            if ($maxHp <= 0) {
                continue;
            }
            $pct = ($ally['currentHp'] / $maxHp) * 100;
            if ($pct < $threshold) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve the target for the current actor.
     *
     * Priority:
     *   L1 — `strategy.target_order` (per-hero ordered slot list)
     *   L1 fallback — `strategy.fallback` (`lowest_hp` | `highest_threat` | default)
     *   L0 — approach-based slot order
     *
     * @param array<string, mixed> $runState
     * @param array<string, mixed> $actor
     *
     * @return array{0: string|null, 1: array<string, mixed>|null}
     */
    private function resolveTarget(array $runState, string $opposingSideKey, array $actor, FormationApproach $approach): array
    {
        $strategy = $actor['strategy'] ?? [];
        $targetOrder = $strategy['target_order'] ?? [];

        if (!empty($targetOrder)) {
            // L1: iterate player-defined slot order
            foreach ($targetOrder as $slotValue) {
                $candidate = $this->getCombatant($runState, $opposingSideKey, $slotValue);
                if (null !== $candidate && $candidate['currentHp'] > 0) {
                    return [$slotValue, $candidate];
                }
            }

            // All target_order slots are KO'd — apply fallback
            $fallback = $strategy['fallback'] ?? 'approach';

            return $this->resolveTargetByFallback($runState, $opposingSideKey, $fallback, $approach);
        }

        return $this->resolveByApproach($runState, $opposingSideKey, $approach);
    }

    /**
     * Resolve fallback target when all target_order slots are exhausted.
     *
     * @param array<string, mixed> $runState
     *
     * @return array{0: string|null, 1: array<string, mixed>|null}
     */
    private function resolveTargetByFallback(array $runState, string $sideKey, string $fallback, FormationApproach $approach): array
    {
        $sideData = 'a' === $sideKey ? $runState['sideA'] : $runState['sideB'];
        $live = array_values(array_filter($sideData['combatants'], fn (array $c): bool => $c['currentHp'] > 0));

        if (empty($live)) {
            return [null, null];
        }

        if ('lowest_hp' === $fallback) {
            usort($live, fn (array $a, array $b): int => $a['currentHp'] <=> $b['currentHp']);

            return [$live[0]['slot'], $live[0]];
        }

        if ('highest_threat' === $fallback) {
            return $this->pickHighestPhysicalAttack($live);
        }

        return $this->resolveByApproach($runState, $sideKey, $approach);
    }

    /**
     * L0 target from formation approach.
     *
     * Aggressive: back row, then front. Balanced: front row, then back.
     * Defensive: living front row; once it is gone, the survivor with the highest physical attack.
     *
     * @param array<string, mixed> $runState
     *
     * @return array{0: string|null, 1: array<string, mixed>|null}
     */
    private function resolveByApproach(array $runState, string $sideKey, FormationApproach $approach): array
    {
        if (FormationApproach::Defensive === $approach) {
            foreach ([FormationPosition::Front1, FormationPosition::Front2, FormationPosition::Front3] as $slotEnum) {
                $candidate = $this->getCombatant($runState, $sideKey, $slotEnum->value);
                if (null !== $candidate && $candidate['currentHp'] > 0) {
                    return [$slotEnum->value, $candidate];
                }
            }

            $sideData = 'a' === $sideKey ? $runState['sideA'] : $runState['sideB'];
            $live = array_values(array_filter($sideData['combatants'], fn (array $c): bool => $c['currentHp'] > 0));

            return [] === $live ? [null, null] : $this->pickHighestPhysicalAttack($live);
        }

        foreach ($this->getTargetSlotsForApproach($approach) as $slotEnum) {
            $candidate = $this->getCombatant($runState, $sideKey, $slotEnum->value);
            if (null !== $candidate && $candidate['currentHp'] > 0) {
                return [$slotEnum->value, $candidate];
            }
        }

        return [null, null];
    }

    /**
     * @param list<array<string, mixed>> $live
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function pickHighestPhysicalAttack(array $live): array
    {
        usort($live, function (array $a, array $b): int {
            $byAttack = ((int) $b['derived']['physicalAttack']) <=> ((int) $a['derived']['physicalAttack']);
            if (0 !== $byAttack) {
                return $byAttack;
            }

            return strcmp((string) $a['slot'], (string) $b['slot']);
        });

        return [$live[0]['slot'], $live[0]];
    }

    /**
     * Slot order for aggressive and balanced. Defensive is resolved in {@see resolveByApproach()}.
     *
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
    private function serializeSideToArray(CombatSide $side, string $sideKey): array
    {
        return [
            'teamId' => $side->getTeamId(),
            'formationId' => $side->getFormationId(),
            'approach' => $side->getApproach()->value,
            'combatants' => array_map(fn (CombatantSnapshot $c) => $this->serializeCombatantToArray($c, $sideKey), $side->getCombatants()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeCombatantToArray(CombatantSnapshot $c, string $sideKey): array
    {
        $slot = $c->getSlot()->value;
        $initialHex = $this->hexGridService->getInitialHexForSlot($slot, $sideKey);

        return [
            'heroId' => $c->getHeroId(),
            'name' => $c->getName(),
            'slot' => $slot,
            'q' => $initialHex['q'],
            'r' => $initialHex['r'],
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
            'currentMana' => $c->getDerived()->getMaxMana(),
            'cooldowns' => [],
            'statusEffects' => [],
            'weaponSubType' => $c->getWeaponSubType()?->value,
            'armorSubType' => $c->getArmorSubType()?->value,
            'queuedAction' => null,
            'preparationRemaining' => 0,
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
            // Unused (#9 Cut): kept on the snapshot so replay JSON stays stable; CombatEngine does not read it.
            'moraleDecayMultiplier' => $d->getMoraleDecayMultiplier(),
            'arenaRevenueBonus' => $d->getArenaRevenueBonus(),
            'clutchHpThreshold' => $d->getClutchHpThreshold(),
            'clutchAccuracyBonus' => $d->getClutchAccuracyBonus(),
            'clutchArmorMultiplier' => $d->getClutchArmorMultiplier(),
            'glassJawHpThreshold' => $d->getGlassJawHpThreshold(),
            'incomingDamageMultiplier' => $d->getIncomingDamageMultiplier(),
            'isConsistentDamage' => $d->isConsistentDamage(),
            // Unused (#9 Cut): serialized unused; CombatEngine does not read it.
            'ignoresRaceSynergy' => $d->ignoresRaceSynergy(),
            'outgoingDamageMultiplier' => $d->getOutgoingDamageMultiplier(),
            'maxMana' => $d->getMaxMana(),
            'manaRegen' => $d->getManaRegen(),
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
        return self::drawInt($state, $min, $max);
    }

    /**
     * Same LCG the turn engine uses. Post-match mortality continues from the saved state.
     */
    public static function drawInt(int &$state, int $min, int $max): int
    {
        $state = (int) (($state * 1103515245 + 12345) & 0x7FFFFFFF);

        return $min + ($state % ($max - $min + 1));
    }

    /**
     * @param array<string, mixed> $runState
     */
    private function recordKill(array &$runState, int $killerHeroId, int $victimLevel): void
    {
        $key = (string) $killerHeroId;
        $counts = $runState['killsByHeroId'] ?? [];
        $counts[$key] = ((int) ($counts[$key] ?? 0)) + 1;
        $runState['killsByHeroId'] = $counts;
        $this->addContribution($runState, $killerHeroId, 'ko_levels', max(1, $victimLevel));
    }

    /**
     * @param array<string, mixed> $runState
     */
    private function addContribution(array &$runState, int $heroId, string $field, int $amount): void
    {
        if ($heroId <= 0 || $amount <= 0) {
            return;
        }

        if (!isset($runState['contributionByHeroId']) || !is_array($runState['contributionByHeroId'])) {
            $runState['contributionByHeroId'] = [];
        }

        $key = (string) $heroId;
        $row = $runState['contributionByHeroId'][$key] ?? [
            'damage_hp' => 0,
            'heal_hp' => 0,
            'status_hp' => 0,
            'status_levels' => 0,
            'ko_levels' => 0,
            'utility_casts' => 0,
        ];
        $row[$field] = (int) ($row[$field] ?? 0) + $amount;
        $runState['contributionByHeroId'][$key] = $row;
    }

    /**
     * @param array<mixed> $runState
     * @param array<mixed> $roundEvents
     */
    private function processStatusEffectTicks(array &$runState, array &$roundEvents): void
    {
        foreach (['a', 'b'] as $sideKey) {
            $sideData = 'a' === $sideKey ? $runState['sideA'] : $runState['sideB'];
            foreach ($sideData['combatants'] as $index => $combatant) {
                if ($combatant['currentHp'] <= 0) {
                    continue;
                }

                $activeEffects = [];
                $expiredEffects = [];
                $killerHeroId = null;
                $killerEffect = null;
                $killerSide = null;
                $killerSlot = null;

                foreach ($combatant['statusEffects'] as $eff) {
                    $effect = $eff['effect'];
                    $duration = $eff['duration'] - 1;
                    $sourceHeroId = (int) ($eff['sourceHeroId'] ?? 0);

                    // Tick actions (DOT/HOT percents from status_effects.yaml)
                    $tickDamagePct = $this->getTickDamagePercent($effect);
                    if ($tickDamagePct > 0) {
                        $hpBefore = (int) $combatant['currentHp'];
                        $damage = (int) max(1, floor($combatant['derived']['maxHp'] * ($tickDamagePct / 100.0)));
                        $combatant['currentHp'] = max(0, $hpBefore - $damage);

                        if ($hpBefore > 0 && $combatant['currentHp'] <= 0 && null === $killerHeroId) {
                            $killerHeroId = $sourceHeroId;
                            $killerEffect = $effect;
                            $killerSide = isset($eff['sourceSide']) ? (string) $eff['sourceSide'] : null;
                            $killerSlot = isset($eff['sourceSlot']) ? (string) $eff['sourceSlot'] : null;
                        }

                        $tickEvent = [
                            't' => count($runState['events']) + count($roundEvents),
                            'type' => 'status_tick',
                            'side' => $sideKey,
                            'slot' => $combatant['slot'],
                            'effect' => $effect,
                            'action' => 'damage',
                            'amount' => $damage,
                            'hp_after' => $combatant['currentHp'],
                        ];
                        if ($sourceHeroId > 0) {
                            $tickEvent['source_hero_id'] = $sourceHeroId;
                        }
                        $roundEvents[] = $tickEvent;
                    } elseif ('regeneration' === $effect) {
                        if ('undead' !== $combatant['race']) {
                            $healPct = $this->getTickHealPercent($effect);
                            $heal = (int) max(1, floor($combatant['derived']['maxHp'] * ($healPct / 100.0)));
                            $combatant['currentHp'] = min($combatant['derived']['maxHp'], $combatant['currentHp'] + $heal);

                            $tickEvent = [
                                't' => count($runState['events']) + count($roundEvents),
                                'type' => 'status_tick',
                                'side' => $sideKey,
                                'slot' => $combatant['slot'],
                                'effect' => $effect,
                                'action' => 'heal',
                                'amount' => $heal,
                                'hp_after' => $combatant['currentHp'],
                            ];
                            if ($sourceHeroId > 0) {
                                $tickEvent['source_hero_id'] = $sourceHeroId;
                            }
                            $roundEvents[] = $tickEvent;
                        }
                    }

                    // Check expiration
                    if ($duration > 0) {
                        $kept = [
                            'effect' => $effect,
                            'duration' => $duration,
                        ];
                        if ($sourceHeroId > 0) {
                            $kept['sourceHeroId'] = $sourceHeroId;
                        }
                        if (isset($eff['sourceSide'], $eff['sourceSlot'])) {
                            $kept['sourceSide'] = (string) $eff['sourceSide'];
                            $kept['sourceSlot'] = (string) $eff['sourceSlot'];
                        }
                        $activeEffects[] = $kept;
                    } else {
                        $expiredEffects[] = $effect;
                    }
                }

                $combatant['statusEffects'] = $activeEffects;
                $runState['side'.strtoupper($sideKey)]['combatants'][$index] = $combatant;

                foreach ($expiredEffects as $exp) {
                    $roundEvents[] = [
                        't' => count($runState['events']) + count($roundEvents),
                        'type' => 'status_expired',
                        'side' => $sideKey,
                        'slot' => $combatant['slot'],
                        'effect' => $exp,
                    ];
                }

                // Check KO from status tick damage (e.g. burn, poison)
                if ($combatant['currentHp'] <= 0) {
                    $opposingSideKey = 'a' === $sideKey ? 'b' : 'a';
                    if ('a' === $opposingSideKey) {
                        ++$runState['scoreA'];
                    } else {
                        ++$runState['scoreB'];
                    }

                    $koEvent = [
                        't' => count($runState['events']) + count($roundEvents),
                        'type' => 'ko',
                        'side' => $sideKey,
                        'slot' => $combatant['slot'],
                        'hero_id' => $combatant['heroId'],
                    ];
                    if (null !== $killerHeroId && $killerHeroId > 0) {
                        $koEvent['killer_hero_id'] = $killerHeroId;
                        $koEvent['effect'] = $killerEffect;
                        if (null !== $killerSide && null !== $killerSlot) {
                            $koEvent['killer_side'] = $killerSide;
                            $koEvent['killer_slot'] = $killerSlot;
                        }
                        $this->recordKill($runState, $killerHeroId, max(1, (int) ($combatant['level'] ?? 1)));
                    }
                    $roundEvents[] = $koEvent;
                    $runState['killedHeroIds'][] = $combatant['heroId'];

                    $roundEvents[] = [
                        't' => count($runState['events']) + count($roundEvents),
                        'type' => 'kill_score',
                        'score_a' => $runState['scoreA'],
                        'score_b' => $runState['scoreB'],
                    ];
                }
            }
        }
    }

    /**
     * @param array<string, mixed>        $runState
     * @param array<string, mixed>        $actor
     * @param array<string, mixed>        $target
     * @param array<array<string, mixed>> $roundEvents
     */
    private function executePhysicalAttack(
        array &$runState,
        string $actorSide,
        string $actorSlot,
        array $actor,
        string $opposingSideKey,
        string $targetSlot,
        array $target,
        array &$roundEvents,
        int &$rngState,
    ): void {
        $actorHex = new HexCoordinate((int) ($actor['q'] ?? 0), (int) ($actor['r'] ?? 0));
        $targetHex = new HexCoordinate((int) ($target['q'] ?? 0), (int) ($target['r'] ?? 0));
        $weaponType = $actor['weaponSubType'] ?? null;
        $reach = $this->hexGridService->getWeaponReach($weaponType);
        $distance = $this->hexGridService->engagementDistance(
            $actorHex,
            $this->combatantRadius($actor),
            $targetHex,
            $this->combatantRadius($target),
        );
        $isRanged = $this->hexGridService->isRangedWeapon($weaponType);

        if ($distance > $reach || ($isRanged && $distance < 2) || (!$isRanged && $distance < 1)) {
            $roundEvents[] = [
                't' => count($runState['events']) + count($roundEvents),
                'type' => 'attack_out_of_range',
                'side' => $actorSide,
                'slot' => $actorSlot,
                'target_side' => $opposingSideKey,
                'target_slot' => $targetSlot,
                'distance' => $distance,
                'reach' => $reach,
            ];

            return;
        }

        if ($isRanged && !$this->hexGridService->hasLineOfSight(
            $actorHex,
            $targetHex,
            $this->collectBlockingHexes($runState, $actorSide, $actorSlot, $opposingSideKey, $targetSlot),
        )) {
            $roundEvents[] = [
                't' => count($runState['events']) + count($roundEvents),
                'type' => 'attack_blocked_los',
                'side' => $actorSide,
                'slot' => $actorSlot,
                'target_side' => $opposingSideKey,
                'target_slot' => $targetSlot,
            ];

            return;
        }

        $facingDirection = 'a' === $opposingSideKey ? 1 : -1;
        $isFlanking = $actorHex->isRearArc($targetHex, $facingDirection);

        $roundEvents[] = [
            't' => count($runState['events']) + count($roundEvents),
            'type' => 'attack',
            'side' => $actorSide,
            'slot' => $actorSlot,
            'target_side' => $opposingSideKey,
            'target_slot' => $targetSlot,
            'flanking' => $isFlanking,
            'distance' => $distance,
        ];

        // 1. Accuracy and dodge check
        $acc = (float) $actor['derived']['accuracyPercent'];
        $dodge = (float) $target['derived']['dodgePercent'];

        // Apply status effects to acc and dodge
        foreach ($actor['statusEffects'] as $eff) {
            if ('blind' === $eff['effect']) {
                $acc -= 40.0;
            }
            if ('bless' === $eff['effect']) {
                $acc += 10.0;
            }
        }
        foreach ($target['statusEffects'] as $eff) {
            if ('shadow_cloak' === $eff['effect']) {
                $dodge += 40.0;
            }
        }

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

            return;
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
        $damage *= (float) ($actor['derived']['outgoingDamageMultiplier'] ?? 1.0);

        $actorRace = (string) ($actor['race'] ?? '');
        $targetRace = (string) ($target['race'] ?? '');
        if ('orc' === $actorRace && 'orc' !== $targetRace && !$this->hexGridService->isRangedWeapon($weaponType)) {
            $damage *= 1.20;
        }

        // Apply status effects to damage
        $dmgMult = 1.0;
        foreach ($actor['statusEffects'] as $eff) {
            if ('fury' === $eff['effect']) {
                $dmgMult += 0.30;
            }
            if ('bless' === $eff['effect']) {
                $dmgMult += 0.10;
            }
        }
        $damage *= $dmgMult;

        if ($isFlanking) {
            $damage *= 1.20;
        }

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

        // Apply status effects to armor
        foreach ($target['statusEffects'] as $eff) {
            if ('petrify' === $eff['effect']) {
                $armorVal *= 0.80; // 20% reduction
            }
            if ('fury' === $eff['effect']) {
                $armorVal *= 0.85; // 15% reduction
            }
        }

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

        // Apply shield damage reduction (bypassed on flanking / rear arc)
        if (!$isFlanking) {
            foreach ($target['statusEffects'] as $eff) {
                if ('shield' === $eff['effect']) {
                    $incomingMult *= 0.75; // 25% reduction
                }
            }
        }

        $netDamage = (int) round($damage * (1.0 - $armorReduction) * $incomingMult);
        $netDamage = max(1, $netDamage);

        // 5. Apply damage. XP uses HP actually removed, so overkill does not fill another bar.
        $hpBefore = (int) $target['currentHp'];
        $effectiveDamage = min($netDamage, $hpBefore);
        $target['currentHp'] = max(0, $hpBefore - $netDamage);
        $this->updateCombatant($runState, $opposingSideKey, $targetSlot, $target);
        $this->addContribution($runState, (int) $actor['heroId'], 'damage_hp', $effectiveDamage);

        $roundEvents[] = [
            'target_side' => $opposingSideKey,
            'target_slot' => $targetSlot,
            'amount' => $netDamage,
            'hp_after' => $target['currentHp'],
            'source' => 'physical',
        ] + ['t' => count($runState['events']) + count($roundEvents), 'type' => 'damage'];

        // Track hits received per hero for item durability loss
        $hitKey = (string) $target['heroId'];
        $runState['itemHitCounts'][$hitKey] = ($runState['itemHitCounts'][$hitKey] ?? 0) + 1;

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
                'killer_hero_id' => $actor['heroId'],
            ];

            $this->recordKill($runState, (int) $actor['heroId'], max(1, (int) ($target['level'] ?? 1)));

            // Track killed heroes for combat death pipeline
            $runState['killedHeroIds'][] = $target['heroId'];

            $roundEvents[] = [
                't' => count($runState['events']) + count($roundEvents),
                'type' => 'kill_score',
                'score_a' => $runState['scoreA'],
                'score_b' => $runState['scoreB'],
            ];
        }
    }

    /**
     * @param array<mixed> $runState
     * @param array<mixed> $caster
     * @param array<mixed> $target
     * @param array<mixed> $spell
     * @param array<mixed> $roundEvents
     */
    private function executeSpell(
        array &$runState,
        string $casterSide,
        string $casterSlot,
        array $caster,
        string $targetSlot,
        array $target,
        array $spell,
        array &$roundEvents,
        int &$rngState,
    ): void {
        $payload = SpellPayload::fromEffects(\is_array($spell['effects'] ?? null) ? $spell['effects'] : []);
        $tier = $spell['tier'] ?? 1;

        $targetSide = ('a' === $casterSide) ? 'b' : 'a';

        $sideData = 'a' === $casterSide ? $runState['sideA'] : $runState['sideB'];
        foreach ($sideData['combatants'] as $c) {
            if ($c['slot'] === $targetSlot && $c['heroId'] === $target['heroId']) {
                $targetSide = $casterSide;
                break;
            }
        }

        $spellEventIndex = count($roundEvents);
        $roundEvents[] = [
            't' => count($runState['events']) + count($roundEvents),
            'type' => 'spell',
            'side' => $casterSide,
            'slot' => $casterSlot,
            'hero_id' => $caster['heroId'],
            'caster_side' => $casterSide,
            'caster_slot' => $casterSlot,
            'target_side' => $targetSide,
            'target_slot' => $targetSlot,
            'spell_id' => $spell['id'],
            'spell_name' => $spell['name'],
            'instant_hp' => $payload->instantHp,
            'apply_effect' => $payload->applyEffect?->value,
            'heal_blocked' => false,
        ];

        if (SpellPayload::INSTANT_DAMAGE === $payload->instantHp) {
            $spellPower = (float) $caster['derived']['spellPower'];
            $baseDamage = $spellPower * (1.0 + $tier * 0.15);
            $baseDamage *= (float) ($caster['derived']['outgoingDamageMultiplier'] ?? 1.0);

            $critChance = (float) ($caster['derived']['critPercent'] ?? 0.0);
            if ('genie' === (string) ($caster['race'] ?? '')) {
                $critChance += 15.0;
            }
            $critChance = min(50.0, $critChance);
            $isCrit = $this->nextInt($rngState, 1, 100) <= $critChance;
            if ($isCrit) {
                $critMult = (float) ($caster['derived']['critDamageMultiplier'] ?? 1.5);
                $baseDamage *= $critMult;
                $roundEvents[] = [
                    't' => count($runState['events']) + count($roundEvents),
                    'type' => 'crit',
                ];
            }

            $dmgMult = 1.0;
            foreach ($target['statusEffects'] as $eff) {
                if ('shield' === $eff['effect']) {
                    $dmgMult -= 0.25;
                }
            }

            $resReduction = (float) $target['derived']['magicDamageReduction'];
            $netDamage = (int) round($baseDamage * (1.0 - $resReduction) * $dmgMult);
            if ($netDamage < 1) {
                $netDamage = 1;
            }

            $hpBefore = (int) $target['currentHp'];
            $effectiveDamage = min($netDamage, $hpBefore);
            $target['currentHp'] = max(0, $hpBefore - $netDamage);
            $this->updateCombatant($runState, $targetSide, $targetSlot, $target);
            $this->addContribution($runState, (int) $caster['heroId'], 'damage_hp', $effectiveDamage);

            $roundEvents[$spellEventIndex]['amount'] = $netDamage;
            $roundEvents[$spellEventIndex]['hp_after'] = $target['currentHp'];

            $roundEvents[] = [
                't' => count($runState['events']) + count($roundEvents),
                'type' => 'damage',
                'target_side' => $targetSide,
                'target_slot' => $targetSlot,
                'amount' => $netDamage,
                'hp_after' => $target['currentHp'],
                'source' => 'magical',
                'accordion' => false,
            ];

            $hitKey = (string) $target['heroId'];
            $runState['itemHitCounts'][$hitKey] = ($runState['itemHitCounts'][$hitKey] ?? 0) + 1;

            if ($target['currentHp'] <= 0) {
                if ('a' === $casterSide) {
                    ++$runState['scoreA'];
                } else {
                    ++$runState['scoreB'];
                }

                $roundEvents[] = [
                    't' => count($runState['events']) + count($roundEvents),
                    'type' => 'ko',
                    'side' => $targetSide,
                    'slot' => $targetSlot,
                    'hero_id' => $target['heroId'],
                    'killer_hero_id' => $caster['heroId'],
                ];
                $this->recordKill($runState, (int) $caster['heroId'], max(1, (int) ($target['level'] ?? 1)));
                $runState['killedHeroIds'][] = $target['heroId'];

                $roundEvents[] = [
                    't' => count($runState['events']) + count($roundEvents),
                    'type' => 'kill_score',
                    'score_a' => $runState['scoreA'],
                    'score_b' => $runState['scoreB'],
                ];
            }
        } elseif (SpellPayload::INSTANT_HEAL === $payload->instantHp) {
            if ('undead' === $target['race'] && 'undead' !== $caster['race']) {
                $roundEvents[$spellEventIndex]['heal_blocked'] = true;
                $roundEvents[] = [
                    't' => count($runState['events']) + count($roundEvents),
                    'type' => 'heal_blocked',
                    'target_side' => $targetSide,
                    'target_slot' => $targetSlot,
                    'reason' => 'undead_external_heal',
                    'accordion' => false,
                ];
            } else {
                $spellPower = (float) $caster['derived']['spellPower'];
                $healAmount = (int) round($spellPower * (1.0 + $tier * 0.10));

                $healMult = 1.0;
                foreach ($target['statusEffects'] as $eff) {
                    if ('curse' === $eff['effect']) {
                        $healMult -= 0.50;
                    }
                }
                $healAmount = (int) round($healAmount * $healMult);
                if ($healAmount < 0) {
                    $healAmount = 0;
                }

                $maxHp = max(1, (int) $target['derived']['maxHp']);
                $hpBefore = (int) $target['currentHp'];
                $effectiveHeal = min($healAmount, max(0, $maxHp - $hpBefore));
                $target['currentHp'] = min($maxHp, $hpBefore + $healAmount);
                $this->updateCombatant($runState, $targetSide, $targetSlot, $target);
                $this->addContribution($runState, (int) $caster['heroId'], 'heal_hp', $effectiveHeal);

                $roundEvents[$spellEventIndex]['amount'] = $healAmount;
                $roundEvents[$spellEventIndex]['hp_after'] = $target['currentHp'];

                $roundEvents[] = [
                    't' => count($runState['events']) + count($roundEvents),
                    'type' => 'heal_applied',
                    'target_side' => $targetSide,
                    'target_slot' => $targetSlot,
                    'amount' => $healAmount,
                    'hp_after' => $target['currentHp'],
                    'accordion' => false,
                ];
            }
        } elseif (null === $payload->instantHp) {
            $this->addContribution($runState, (int) $caster['heroId'], 'utility_casts', 1);
        }

        if (null !== $payload->applyEffect) {
            $roundEvents[$spellEventIndex]['status_duration'] = $this->getDurationForStatus($payload->applyEffect->value);
            $this->applyStatusEffect($runState, $targetSide, $targetSlot, $target, $payload->applyEffect->value, $roundEvents, (int) $caster['heroId'], $casterSide, $casterSlot);
        }
    }

    /**
     * @param array<mixed> $runState
     * @param array<mixed> $combatant
     * @param array<mixed> $roundEvents
     */
    private function applyStatusEffect(array &$runState, string $side, string $slot, array $combatant, string $effect, array &$roundEvents, int $sourceHeroId, string $sourceSide, string $sourceSlot): void
    {
        $found = false;
        foreach ($combatant['statusEffects'] as &$eff) {
            if ($eff['effect'] === $effect) {
                $eff['duration'] = $this->getDurationForStatus($effect);
                $eff['sourceHeroId'] = $sourceHeroId;
                $eff['sourceSide'] = $sourceSide;
                $eff['sourceSlot'] = $sourceSlot;
                $found = true;
                break;
            }
        }
        unset($eff);

        if (!$found) {
            $combatant['statusEffects'][] = [
                'effect' => $effect,
                'duration' => $this->getDurationForStatus($effect),
                'sourceHeroId' => $sourceHeroId,
                'sourceSide' => $sourceSide,
                'sourceSlot' => $sourceSlot,
            ];
        }

        $this->creditStatusApplication($runState, $sourceHeroId, $effect, $combatant);

        $this->updateCombatant($runState, $side, $slot, $combatant);

        $roundEvents[] = [
            't' => count($runState['events']) + count($roundEvents),
            'type' => 'status_applied',
            'target_side' => $side,
            'target_slot' => $slot,
            'effect' => $effect,
            'duration' => $this->getDurationForStatus($effect),
            'source_hero_id' => $sourceHeroId,
            'source_side' => $sourceSide,
            'source_slot' => $sourceSlot,
            'accordion' => false,
        ];
    }

    /**
     * Every successful cast pays, including a refresh of the same effect.
     * Damage and heal over time pay half of one full listed run. A status
     * with no tick pays 2 XP per target level. Regeneration on an undead
     * still pays that half run, even though the ticks themselves do not heal.
     *
     * @param array<string, mixed> $runState
     * @param array<string, mixed> $combatant
     */
    private function creditStatusApplication(array &$runState, int $sourceHeroId, string $effect, array $combatant): void
    {
        $tickDamage = $this->getTickDamagePercent($effect);
        $tickHeal = $this->getTickHealPercent($effect);
        $tickPct = $tickDamage > 0 ? $tickDamage : $tickHeal;
        if ($tickPct <= 0) {
            $level = max(1, (int) ($combatant['level'] ?? 1));
            $this->addContribution($runState, $sourceHeroId, 'status_levels', $level);

            return;
        }

        $maxHp = max(1, (int) ($combatant['derived']['maxHp'] ?? 1));
        $ticks = max(1, $this->getDurationForStatus($effect));
        $perTick = (int) max(1, floor($maxHp * ($tickPct / 100.0)));
        $credit = (int) round($perTick * $ticks / 2);
        $this->addContribution($runState, $sourceHeroId, 'status_hp', $credit);
    }

    private function getDurationForStatus(string $effect): int
    {
        try {
            return $this->statusEffectConfig->get(StatusEffect::from($effect))->getDurationTurns();
        } catch (\Throwable) {
            return match ($effect) {
                'burn', 'curse', 'regeneration', 'shield', 'bless' => 3,
                'poison' => 4,
                'shock', 'petrify', 'blind', 'haste', 'fury', 'shadow_cloak', 'taunt', 'silence' => 2,
                'freeze', 'stun' => 1,
                default => 2,
            };
        }
    }

    /**
     * Per-round turn sort only. AP still uses stored `derived.baseInitiative`.
     *
     * @param array<string, mixed> $combatant
     */
    private function initiativeForSort(array $combatant, int $initRoll): int
    {
        $base = (int) ($combatant['derived']['baseInitiative'] ?? 0);
        $bonus = 0;
        $reduction = 0;

        foreach ($combatant['statusEffects'] ?? [] as $eff) {
            $key = $eff['effect'] ?? '';
            if (!\is_string($key) || '' === $key) {
                continue;
            }
            $bonus += $this->speedBonusPercent($key);
            $reduction += $this->speedReductionPercent($key);
        }

        $modifier = max(0.0, 1.0 + ($bonus / 100.0) - ($reduction / 100.0));

        return (int) round($base * $modifier) + $initRoll;
    }

    private function speedBonusPercent(string $effect): int
    {
        try {
            return $this->statusEffectConfig->get(StatusEffect::from($effect))->getSpeedBonusPercent();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function speedReductionPercent(string $effect): int
    {
        try {
            return $this->statusEffectConfig->get(StatusEffect::from($effect))->getSpeedReductionPercent();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function getTickDamagePercent(string $effect): int
    {
        try {
            return $this->statusEffectConfig->get(StatusEffect::from($effect))->getTickDamagePercent();
        } catch (\Throwable) {
            return match ($effect) {
                'burn' => 5,
                'poison' => 3,
                default => 0,
            };
        }
    }

    private function getTickHealPercent(string $effect): int
    {
        try {
            return $this->statusEffectConfig->get(StatusEffect::from($effect))->getTickHealPercent();
        } catch (\Throwable) {
            return 'regeneration' === $effect ? 5 : 0;
        }
    }
}
