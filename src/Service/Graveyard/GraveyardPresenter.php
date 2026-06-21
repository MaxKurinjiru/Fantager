<?php

declare(strict_types=1);

namespace App\Service\Graveyard;

use App\Entity\Graveyard\GraveyardMemorial;
use App\Entity\Team\Team;
use App\Enum\HeroRole;
use App\Enum\MemorialCause;
use App\Enum\Race;
use App\Repository\Graveyard\GraveyardMemorialRepository;

class GraveyardPresenter
{
    public function __construct(
        private readonly GraveyardMemorialRepository $memorialRepository,
        private readonly GraveyardService $graveyardService,
    ) {
    }

    /**
     * @return array{
     *     total: int,
     *     by_cause: array<string, int>,
     *     average_age: float|null
     * }
     */
    public function presentSummary(Team $team): array
    {
        return [
            'total' => $this->memorialRepository->countByTeam($team),
            'by_cause' => $this->memorialRepository->countByCauseForTeam($team),
            'average_age' => $this->memorialRepository->averageAgeForTeam($team),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function presentListForTeam(
        Team $team,
        ?HeroRole $role = null,
        ?MemorialCause $cause = null,
        ?Race $race = null,
        ?string $search = null,
        ?int $page = null,
        ?int $limit = null,
    ): array {
        $qb = $this->memorialRepository->findByTeamFiltered($team, $role, $cause, $race, $search, $page, $limit);

        return array_map(fn (GraveyardMemorial $record) => $this->graveyardService->serializeMemorial($record), $qb);
    }

    public function findForTeam(int $id, Team $team): ?GraveyardMemorial
    {
        return $this->memorialRepository->findOneForTeam($id, $team);
    }

    public function countFilteredForTeam(
        Team $team,
        ?HeroRole $role = null,
        ?MemorialCause $cause = null,
        ?Race $race = null,
        ?string $search = null,
    ): int {
        return $this->memorialRepository->countByTeamFiltered($team, $role, $cause, $race, $search);
    }
}
