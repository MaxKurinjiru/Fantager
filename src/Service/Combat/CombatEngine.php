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
            'killedHeroIds' => [],
            'itemHitCounts' => [],
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

            // 1. Decrement active cooldowns for actor
            foreach ($actor['cooldowns'] as $spellId => $cd) {
                if ($cd > 0) {
                    $actor['cooldowns'][$spellId] = $cd - 1;
                }
            }
            $this->updateCombatant($runState, $actorSide, $actorSlot, $actor);

            // 2. Check for freeze/stun (skip turn)
            $hasSkipTurnEffect = false;
            foreach ($actor['statusEffects'] as $eff) {
                if ('freeze' === $eff['effect'] || 'stun' === $eff['effect']) {
                    $hasSkipTurnEffect = true;
                    break;
                }
            }
            if ($hasSkipTurnEffect) {
                $roundEvents[] = [
                    't' => count($runState['events']) + count($roundEvents),
                    'type' => 'skip_turn',
                    'side' => $actorSide,
                    'slot' => $actorSlot,
                    'hero_id' => $actor['heroId'],
                ];
                continue;
            }

            // Target selection — L1 (strategy.target_order) with L0 fallback
            $approachVal = 'a' === $actorSide ? $runState['sideA']['approach'] : $runState['sideB']['approach'];
            $approach = FormationApproach::from($approachVal);

            [$targetSlot, $target] = $this->resolveTarget($runState, $opposingSideKey, $actor, $approach);

            if (null === $target || null === $targetSlot) {
                continue;
            }

            // 3. Spell priority check (L2 Spells)
            $castSpell = null;
            $spellPriorityTarget = 'enemy';

            $isSilenced = false;
            foreach ($actor['statusEffects'] as $eff) {
                if ('silence' === $eff['effect']) {
                    $isSilenced = true;
                    break;
                }
            }

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

                    $cdKey = (string) $spellId;
                    $currentCd = $actor['cooldowns'][$cdKey] ?? 0;
                    if ($currentCd > 0) {
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

                    if ($conditionMet) {
                        $castSpell = $spell;
                        $spellPriorityTarget = $priority['target'] ?? 'enemy';
                        break;
                    }
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

                $this->executeSpell($runState, $actorSide, $actorSlot, $actor, $spellTargetSlot, $spellTarget, $castSpell, $roundEvents);

                // Set cooldown
                $cdKey = (string) $castSpell['id'];
                $actor['cooldowns'][$cdKey] = $castSpell['cooldown'];
                $this->updateCombatant($runState, $actorSide, $actorSlot, $actor);

                continue; // Spell cast, end turn
            }

            // Perform basic physical attack
            $roundEvents[] = [
                't' => count($runState['events']) + count($roundEvents),
                'type' => 'attack',
                'side' => $actorSide,
                'slot' => $actorSlot,
                'target_side' => $opposingSideKey,
                'target_slot' => $targetSlot,
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

            // Apply shield damage reduction
            foreach ($target['statusEffects'] as $eff) {
                if ('shield' === $eff['effect']) {
                    $incomingMult *= 0.75; // 25% reduction
                }
            }

            $netDamage = (int) round($damage * (1.0 - $armorReduction) * $incomingMult);
            $netDamage = max(1, $netDamage);

            // 5. Apply damage
            $target['currentHp'] = max(0, $target['currentHp'] - $netDamage);
            $this->updateCombatant($runState, $opposingSideKey, $targetSlot, $target);

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
                ];

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
                'killed_hero_ids' => $runState['killedHeroIds'],
                'item_hit_counts' => $runState['itemHitCounts'],
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

        // L0: approach-based slot order
        foreach ($this->getTargetSlotsForApproach($approach) as $slotEnum) {
            $candidate = $this->getCombatant($runState, $opposingSideKey, $slotEnum->value);
            if (null !== $candidate && $candidate['currentHp'] > 0) {
                return [$slotEnum->value, $candidate];
            }
        }

        return [null, null];
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
            usort($live, fn (array $a, array $b): int => (int) round($b['derived']['physicalAttack'] - $a['derived']['physicalAttack']));

            return [$live[0]['slot'], $live[0]];
        }

        // Default: approach-based order
        foreach ($this->getTargetSlotsForApproach($approach) as $slotEnum) {
            $candidate = $this->getCombatant($runState, $sideKey, $slotEnum->value);
            if (null !== $candidate && $candidate['currentHp'] > 0) {
                return [$slotEnum->value, $candidate];
            }
        }

        return [null, null];
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
            'cooldowns' => [],
            'statusEffects' => [],
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

                foreach ($combatant['statusEffects'] as $eff) {
                    $effect = $eff['effect'];
                    $duration = $eff['duration'] - 1;

                    // Tick actions
                    if ('burn' === $effect) {
                        $damage = (int) max(1, floor($combatant['derived']['maxHp'] * 0.05));
                        $combatant['currentHp'] = max(0, $combatant['currentHp'] - $damage);

                        $roundEvents[] = [
                            't' => count($runState['events']) + count($roundEvents),
                            'type' => 'status_tick',
                            'side' => $sideKey,
                            'slot' => $combatant['slot'],
                            'effect' => $effect,
                            'action' => 'damage',
                            'amount' => $damage,
                            'hp_after' => $combatant['currentHp'],
                        ];
                    } elseif ('poison' === $effect) {
                        $damage = (int) max(1, floor($combatant['derived']['maxHp'] * 0.03));
                        $combatant['currentHp'] = max(0, $combatant['currentHp'] - $damage);

                        $roundEvents[] = [
                            't' => count($runState['events']) + count($roundEvents),
                            'type' => 'status_tick',
                            'side' => $sideKey,
                            'slot' => $combatant['slot'],
                            'effect' => $effect,
                            'action' => 'damage',
                            'amount' => $damage,
                            'hp_after' => $combatant['currentHp'],
                        ];
                    } elseif ('regeneration' === $effect) {
                        if ('undead' !== $combatant['race']) {
                            $heal = (int) max(1, floor($combatant['derived']['maxHp'] * 0.05));
                            $combatant['currentHp'] = min($combatant['derived']['maxHp'], $combatant['currentHp'] + $heal);

                            $roundEvents[] = [
                                't' => count($runState['events']) + count($roundEvents),
                                'type' => 'status_tick',
                                'side' => $sideKey,
                                'slot' => $combatant['slot'],
                                'effect' => $effect,
                                'action' => 'heal',
                                'amount' => $heal,
                                'hp_after' => $combatant['currentHp'],
                            ];
                        }
                    }

                    // Check expiration
                    if ($duration > 0) {
                        $activeEffects[] = [
                            'effect' => $effect,
                            'duration' => $duration,
                        ];
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
                    $roundEvents[] = [
                        't' => count($runState['events']) + count($roundEvents),
                        'type' => 'ko',
                        'side' => $sideKey,
                        'slot' => $combatant['slot'],
                        'hero_id' => $combatant['heroId'],
                    ];
                    $runState['killedHeroIds'][] = $combatant['heroId'];

                    // Give score to opponent
                    $opposingSideKey = 'a' === $sideKey ? 'b' : 'a';
                    $roundEvents[] = [
                        't' => count($runState['events']) + count($roundEvents),
                        'type' => 'kill_score',
                        'side' => $opposingSideKey,
                    ];

                    if ('a' === $opposingSideKey) {
                        ++$runState['scoreA'];
                    } else {
                        ++$runState['scoreB'];
                    }
                }
            }
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
    ): void {
        $spellType = $spell['type'];
        $school = $spell['school'];
        $tier = $spell['tier'] ?? 1;

        $targetSide = ('a' === $casterSide) ? 'b' : 'a';

        $isAlly = false;
        $sideData = 'a' === $casterSide ? $runState['sideA'] : $runState['sideB'];
        foreach ($sideData['combatants'] as $c) {
            if ($c['slot'] === $targetSlot && $c['heroId'] === $target['heroId']) {
                $isAlly = true;
                $targetSide = $casterSide;
                break;
            }
        }

        $roundEvents[] = [
            't' => count($runState['events']) + count($roundEvents),
            'type' => 'spell_cast',
            'caster_side' => $casterSide,
            'caster_slot' => $casterSlot,
            'target_side' => $targetSide,
            'target_slot' => $targetSlot,
            'spell_id' => $spell['id'],
            'spell_name' => $spell['name'],
        ];

        if ('offensive' === $spellType) {
            $spellPower = (float) $caster['derived']['spellPower'];
            $baseDamage = $spellPower * (1.0 + $tier * 0.15);

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

            $target['currentHp'] = max(0, $target['currentHp'] - $netDamage);
            $this->updateCombatant($runState, $targetSide, $targetSlot, $target);

            $roundEvents[] = [
                't' => count($runState['events']) + count($roundEvents),
                'type' => 'damage',
                'target_side' => $targetSide,
                'target_slot' => $targetSlot,
                'amount' => $netDamage,
                'hp_after' => $target['currentHp'],
                'source' => 'magical',
            ];

            $hitKey = (string) $target['heroId'];
            $runState['itemHitCounts'][$hitKey] = ($runState['itemHitCounts'][$hitKey] ?? 0) + 1;

            $statusEffect = $this->getOffensiveStatusForSchool($school);
            if (null !== $statusEffect) {
                $this->applyStatusEffect($runState, $targetSide, $targetSlot, $target, $statusEffect, $roundEvents);
            }

            if ($target['currentHp'] <= 0) {
                $roundEvents[] = [
                    't' => count($runState['events']) + count($roundEvents),
                    'type' => 'ko',
                    'side' => $targetSide,
                    'slot' => $targetSlot,
                    'hero_id' => $target['heroId'],
                ];
                $runState['killedHeroIds'][] = $target['heroId'];

                $roundEvents[] = [
                    't' => count($runState['events']) + count($roundEvents),
                    'type' => 'kill_score',
                    'side' => $casterSide,
                ];

                if ('a' === $casterSide) {
                    ++$runState['scoreA'];
                } else {
                    ++$runState['scoreB'];
                }
            }
        } elseif ('defensive' === $spellType) {
            if ('undead' === $target['race'] && 'undead' !== $caster['race']) {
                $roundEvents[] = [
                    't' => count($runState['events']) + count($roundEvents),
                    'type' => 'heal_blocked',
                    'target_side' => $targetSide,
                    'target_slot' => $targetSlot,
                    'reason' => 'undead_external_heal',
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

                $maxHp = $target['derived']['maxHp'];
                $target['currentHp'] = min($maxHp, $target['currentHp'] + $healAmount);
                $this->updateCombatant($runState, $targetSide, $targetSlot, $target);

                $roundEvents[] = [
                    't' => count($runState['events']) + count($roundEvents),
                    'type' => 'heal_applied',
                    'target_side' => $targetSide,
                    'target_slot' => $targetSlot,
                    'amount' => $healAmount,
                    'hp_after' => $target['currentHp'],
                ];
            }

            $statusEffect = $this->getDefensiveStatusForSchool($school);
            if (null !== $statusEffect) {
                $this->applyStatusEffect($runState, $targetSide, $targetSlot, $target, $statusEffect, $roundEvents);
            }
        } elseif ('utility' === $spellType) {
            $statusEffect = $this->getUtilityStatusForSchool($school);
            if (null !== $statusEffect) {
                $this->applyStatusEffect($runState, $targetSide, $targetSlot, $target, $statusEffect, $roundEvents);
            }
        }
    }

    /**
     * @param array<mixed> $runState
     * @param array<mixed> $combatant
     * @param array<mixed> $roundEvents
     */
    private function applyStatusEffect(array &$runState, string $side, string $slot, array $combatant, string $effect, array &$roundEvents): void
    {
        $found = false;
        foreach ($combatant['statusEffects'] as &$eff) {
            if ($eff['effect'] === $effect) {
                $eff['duration'] = $this->getDurationForStatus($effect);
                $found = true;
                break;
            }
        }
        unset($eff);

        if (!$found) {
            $combatant['statusEffects'][] = [
                'effect' => $effect,
                'duration' => $this->getDurationForStatus($effect),
            ];
        }

        $this->updateCombatant($runState, $side, $slot, $combatant);

        $roundEvents[] = [
            't' => count($runState['events']) + count($roundEvents),
            'type' => 'status_applied',
            'target_side' => $side,
            'target_slot' => $slot,
            'effect' => $effect,
            'duration' => $this->getDurationForStatus($effect),
        ];
    }

    private function getDurationForStatus(string $effect): int
    {
        return match ($effect) {
            'burn', 'curse', 'regeneration', 'shield', 'bless' => 3,
            'poison' => 4,
            'shock', 'petrify', 'blind', 'haste', 'fury', 'shadow_cloak', 'taunt', 'silence' => 2,
            'freeze', 'stun' => 1,
            default => 2,
        };
    }

    private function getOffensiveStatusForSchool(string $school): ?string
    {
        return match ($school) {
            'fire' => 'burn',
            'air' => 'shock',
            'earth' => 'petrify',
            'light' => 'blind',
            'dark' => 'curse',
            default => null,
        };
    }

    private function getDefensiveStatusForSchool(string $school): ?string
    {
        return match ($school) {
            'fire' => 'fury',
            'water' => 'regeneration',
            'air' => 'haste',
            'earth' => 'shield',
            'light' => 'bless',
            'dark' => 'shadow_cloak',
            default => null,
        };
    }

    private function getUtilityStatusForSchool(string $school): ?string
    {
        return match ($school) {
            'fire' => 'burn',
            'water' => 'freeze',
            'air' => 'stun',
            'earth' => 'petrify',
            'light' => 'silence',
            'dark' => 'taunt',
            default => null,
        };
    }
}
