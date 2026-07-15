<?php

declare(strict_types=1);

namespace App\Service\Combat;

use App\ValueObject\Combat\CombatMatchRequest;
use App\ValueObject\Combat\CombatSide;
use App\ValueObject\Combat\CombatSimulationResult;

/**
 * Thin combat engine (Milestone 6.1a scaffolding).
 *
 * Emits a valid event-stream `combat_log` envelope with match_start / match_end.
 * Kill scores are still placeholder (seeded 0–6) until the turn-resolution loop ships.
 */
class CombatEngine
{
    public const ENGINE_VERSION = 1;

    public function simulate(CombatMatchRequest $request): CombatSimulationResult
    {
        $seed = $request->getSeed();
        $rngState = $seed > 0 ? $seed : 1;

        $scoreA = $this->nextInt($rngState, 0, 6);
        $scoreB = $this->nextInt($rngState, 0, 6);

        $combatLog = [
            'version' => 1,
            'simulator' => 'combat_engine',
            'seed' => $seed,
            'engine_version' => $request->getEngineVersion(),
            'match_type' => $request->getMatchType()->value,
            'placeholder_scores' => true,
            'teams' => [
                'a' => $this->teamMeta($request->getSideA()),
                'b' => $this->teamMeta($request->getSideB()),
            ],
            'lineup' => [
                'a' => $this->lineupMeta($request->getSideA()),
                'b' => $this->lineupMeta($request->getSideB()),
            ],
            'events' => [
                ['t' => 0, 'type' => 'match_start'],
                [
                    't' => 1,
                    'type' => 'match_end',
                    'score_a' => $scoreA,
                    'score_b' => $scoreB,
                    'rounds' => 0,
                ],
            ],
            'result' => [
                'score_a' => $scoreA,
                'score_b' => $scoreB,
            ],
        ];

        return new CombatSimulationResult($scoreA, $scoreB, $seed, $combatLog);
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

    /**
     * Minimal LCG for deterministic placeholder scores (replaced when turn loop lands).
     */
    private function nextInt(int &$state, int $min, int $max): int
    {
        $state = (int) (($state * 1103515245 + 12345) & 0x7FFFFFFF);

        return $min + ($state % ($max - $min + 1));
    }
}
