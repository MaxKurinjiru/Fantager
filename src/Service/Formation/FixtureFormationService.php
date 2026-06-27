<?php

declare(strict_types=1);

namespace App\Service\Formation;

use App\Entity\Formation\Formation;
use App\Entity\Kingdom\Kingdom;
use App\Entity\League\LeagueFixture;
use App\Entity\Team\Team;
use App\Enum\FormationApproach;
use App\Enum\LeagueFixtureStatus;
use App\Exception\UserFacingException;
use App\Repository\Formation\FormationRepository;
use App\Repository\League\LeagueFixtureRepository;
use Doctrine\ORM\EntityManagerInterface;

class FixtureFormationService
{
    public function __construct(
        private readonly FormationService $formationService,
        private readonly FormationRepository $formationRepository,
        private readonly LeagueFixtureRepository $fixtureRepository,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * @return array<string, mixed>
     *
     * @throws \DomainException
     */
    /**
     * Lightweight summary for fixture lists (dashboard, league).
     *
     * @return array{mode: string, formation_id: int|null, formation_name: string|null, is_temporary: bool}
     */
    public function getFormationSummary(LeagueFixture $fixture, Team $team): array
    {
        $assigned = $this->getAssignedFormation($fixture, $team);

        $mode = 'default';
        if (null !== $assigned) {
            $mode = $assigned->isTemporary() ? 'custom' : 'saved';
        }

        return [
            'mode' => $mode,
            'formation_id' => $assigned?->getId(),
            'formation_name' => $assigned?->getName(),
            'is_temporary' => null !== $assigned && $assigned->isTemporary(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getAssignmentState(LeagueFixture $fixture, Team $team): array
    {
        $this->assertTeamInFixture($fixture, $team);
        $assigned = $this->getAssignedFormation($fixture, $team);
        $default = $this->formationService->findDefaultForTeam($team);
        $effective = $this->resolveFormation($fixture, $team);

        $mode = 'default';
        if (null !== $assigned) {
            $mode = $assigned->isTemporary() ? 'custom' : 'saved';
        }

        return [
            'fixture_id' => $fixture->getId(),
            'assignment' => [
                'mode' => $mode,
                'formation_id' => $assigned?->getId(),
                'is_temporary' => null !== $assigned && $assigned->isTemporary(),
            ],
            'default_formation' => null !== $default ? $this->formationService->serialize($default) : null,
            'assigned_formation' => null !== $assigned ? $this->formationService->serialize($assigned) : null,
            'effective_formation' => $this->formationService->serialize($effective),
            'saved_formations' => array_map(
                $this->formationService->serialize(...),
                $this->formationService->listByTeam($team),
            ),
            'saved_limit' => FormationService::MAX_SAVED_FORMATIONS,
            'saved_count' => $this->formationRepository->countSavedByTeam($team),
        ];
    }

    /**
     * Remove temporary match-specific formations after a fixture is completed.
     * Clears fixture assignment FKs and deletes orphaned temporary formations.
     *
     * @return int Number of deleted temporary formations
     */
    public function cleanupTemporaryFormationsAfterCompletion(LeagueFixture $fixture): int
    {
        /** @var array<int, Formation> $toDelete */
        $toDelete = [];
        $this->collectTemporaryFormationsForFixture($fixture, $toDelete);
        $this->clearTemporaryAssignments($fixture);

        foreach ($toDelete as $formation) {
            $this->formationService->deleteTemporary($formation);
        }

        if ([] !== $toDelete) {
            $this->em->flush();
        }

        return count($toDelete);
    }

    /**
     * Remove stale temporary formations for a specific team.
     *
     * @return int Number of deleted temporary formations
     */
    public function cleanupStaleTemporaryFormationsForTeam(Team $team): int
    {
        // Find completed fixtures for the team with temporary assignments
        $fixtures = $this->fixtureRepository->createQueryBuilder('f')
            ->where('f.homeTeam = :team OR f.awayTeam = :team')
            ->andWhere('f.status = :completedStatus')
            ->setParameter('team', $team)
            ->setParameter('completedStatus', LeagueFixtureStatus::Completed)
            ->getQuery()
            ->getResult();

        /** @var array<int, Formation> $deleted */
        $deleted = [];
        foreach ($fixtures as $fixture) {
            $this->collectTemporaryFormationsForFixture($fixture, $deleted);
            $this->clearTemporaryAssignments($fixture);
        }

        // Also find temporary formations for this team with completed source fixtures
        $formations = $this->formationRepository->createQueryBuilder('f')
            ->where('f.team = :team')
            ->andWhere('f.isTemporary = true')
            ->andWhere('f.sourceFixture IS NOT NULL')
            ->setParameter('team', $team)
            ->getQuery()
            ->getResult();

        foreach ($formations as $formation) {
            $sourceFixture = $formation->getSourceFixture();
            if (null !== $sourceFixture && $sourceFixture->isCompleted()) {
                $deleted[$formation->getId() ?? 0] = $formation;
            }
        }

        foreach ($deleted as $formation) {
            $this->formationService->deleteTemporary($formation);
        }

        if ([] !== $deleted) {
            $this->em->flush();
        }

        return count($deleted);
    }

    /**
     * Kingdom-wide sweep for stale temporary formations on completed fixtures.
     * Intended to run from scheduled kingdom ticks (DailyReset, LeagueMatch).
     */
    public function cleanupStaleTemporaryFormationsForKingdom(Kingdom $kingdom): int
    {
        /** @var array<int, Formation> $deleted */
        $deleted = [];

        foreach ($this->fixtureRepository->findCompletedWithTemporaryAssignments($kingdom) as $fixture) {
            $before = count($deleted);
            $this->collectTemporaryFormationsForFixture($fixture, $deleted);
            if (count($deleted) > $before) {
                $this->clearTemporaryAssignments($fixture);
            }
        }

        foreach ($this->formationRepository->findTemporaryWithCompletedSourceFixture($kingdom) as $formation) {
            $deleted[$formation->getId() ?? 0] = $formation;
        }

        foreach ($deleted as $formation) {
            $this->formationService->deleteTemporary($formation);
        }

        if ([] !== $deleted) {
            $this->em->flush();
        }

        return count($deleted);
    }

    /**
     * @param array<int, Formation> $deleted
     */
    private function collectTemporaryFormationsForFixture(LeagueFixture $fixture, array &$deleted): void
    {
        $home = $fixture->getHomeFormation();
        if (null !== $home && $home->isTemporary()) {
            $deleted[$home->getId() ?? 0] = $home;
        }

        $away = $fixture->getAwayFormation();
        if (null !== $away && $away->isTemporary()) {
            $deleted[$away->getId() ?? 0] = $away;
        }

        foreach ($this->formationRepository->findTemporaryByFixture($fixture) as $formation) {
            $deleted[$formation->getId() ?? 0] = $formation;
        }
    }

    private function clearTemporaryAssignments(LeagueFixture $fixture): void
    {
        if ($fixture->getHomeFormation()?->isTemporary()) {
            $fixture->setHomeFormation(null);
        }
        if ($fixture->getAwayFormation()?->isTemporary()) {
            $fixture->setAwayFormation(null);
        }
    }

    /**
     * Resolve the formation that should be used when the match is evaluated.
     *
     * @throws \DomainException
     */
    public function resolveFormation(LeagueFixture $fixture, Team $team): Formation
    {
        $assigned = $this->getAssignedFormation($fixture, $team);
        if (null !== $assigned) {
            if ($assigned->getTeam()->getId() !== $team->getId()) {
                throw new UserFacingException('error.formation_not_belong_team');
            }

            return $assigned;
        }

        return $this->formationService->requireDefaultForTeam($team);
    }

    /**
     * Use team default for this fixture (NULL assignment).
     *
     * @throws \DomainException
     */
    public function assignDefault(LeagueFixture $fixture, Team $team): void
    {
        $this->assertEditable($fixture, $team);
        $this->replaceAssignment($fixture, $team, null);
        $this->em->flush();
    }

    /**
     * Assign a saved (non-temporary) formation to this fixture.
     *
     * @throws \DomainException
     */
    public function assignSavedFormation(LeagueFixture $fixture, Team $team, int $formationId): void
    {
        $this->assertEditable($fixture, $team);

        $formation = $this->formationService->findForTeam($formationId, $team);
        if (null === $formation) {
            throw new UserFacingException('error.formation_not_found');
        }

        $this->replaceAssignment($fixture, $team, $formation);
        $this->em->flush();
    }

    /**
     * Save a match-specific temporary formation for this fixture.
     *
     * @param list<array{position: string, hero_id: int|null, strategy: array<string, mixed>, spell_priorities: array<mixed>}> $slotsData
     *
     * @throws \DomainException|\InvalidArgumentException
     */
    public function saveMatchSpecificFormation(
        LeagueFixture $fixture,
        Team $team,
        string $name,
        FormationApproach $approach,
        array $slotsData,
    ): Formation {
        $this->assertEditable($fixture, $team);

        $assigned = $this->getAssignedFormation($fixture, $team);
        if (null !== $assigned && $assigned->isTemporary() && $assigned->getSourceFixture()?->getId() === $fixture->getId()) {
            $formation = $this->formationService->saveTemporary($assigned, $team, $fixture, $name, $approach, $slotsData);
            $this->em->flush();

            return $formation;
        }

        $this->removeAssignedTemporary($fixture, $team);

        $formation = $this->formationService->createTemporary($team, $fixture, $name, $approach, $slotsData);
        $this->setAssignedFormation($fixture, $team, $formation);
        $this->em->flush();

        return $formation;
    }

    /**
     * Promote the current match-specific formation to a saved player formation.
     *
     * @throws \DomainException|\InvalidArgumentException
     */
    public function promoteMatchFormation(
        LeagueFixture $fixture,
        Team $team,
        string $name,
        bool $isDefault,
    ): Formation {
        $this->assertEditable($fixture, $team);

        $assigned = $this->getAssignedFormation($fixture, $team);
        if (null === $assigned || !$assigned->isTemporary()) {
            throw new UserFacingException('error.formation_no_match_to_promote');
        }

        return $this->formationService->promoteTemporary($assigned, $team, $name, $isDefault);
    }

    public function findFixtureForTeam(int $fixtureId, Team $team): ?LeagueFixture
    {
        /** @var LeagueFixture|null $fixture */
        $fixture = $this->fixtureRepository->find($fixtureId);
        if (null === $fixture) {
            return null;
        }

        if ($fixture->getHomeTeam()->getId() !== $team->getId() && $fixture->getAwayTeam()->getId() !== $team->getId()) {
            return null;
        }

        return $fixture;
    }

    private function getAssignedFormation(LeagueFixture $fixture, Team $team): ?Formation
    {
        if ($fixture->getHomeTeam()->getId() === $team->getId()) {
            return $fixture->getHomeFormation();
        }

        if ($fixture->getAwayTeam()->getId() === $team->getId()) {
            return $fixture->getAwayFormation();
        }

        throw new UserFacingException('error.formation_not_on_fixture');
    }

    private function setAssignedFormation(LeagueFixture $fixture, Team $team, ?Formation $formation): void
    {
        if ($fixture->getHomeTeam()->getId() === $team->getId()) {
            $fixture->setHomeFormation($formation);

            return;
        }

        if ($fixture->getAwayTeam()->getId() === $team->getId()) {
            $fixture->setAwayFormation($formation);

            return;
        }

        throw new UserFacingException('error.formation_not_on_fixture');
    }

    private function replaceAssignment(LeagueFixture $fixture, Team $team, ?Formation $newFormation): void
    {
        $this->removeAssignedTemporary($fixture, $team);
        $this->setAssignedFormation($fixture, $team, $newFormation);
    }

    private function removeAssignedTemporary(LeagueFixture $fixture, Team $team): void
    {
        $current = $this->getAssignedFormation($fixture, $team);
        if (null !== $current && $current->isTemporary()) {
            $this->setAssignedFormation($fixture, $team, null);
            $this->formationService->deleteTemporary($current);
        }
    }

    /**
     * @throws \DomainException
     */
    private function assertTeamInFixture(LeagueFixture $fixture, Team $team): void
    {
        if ($fixture->getHomeTeam()->getId() !== $team->getId() && $fixture->getAwayTeam()->getId() !== $team->getId()) {
            throw new UserFacingException('error.formation_not_on_fixture');
        }
    }

    /**
     * @throws \DomainException
     */
    private function assertEditable(LeagueFixture $fixture, Team $team): void
    {
        $this->assertTeamInFixture($fixture, $team);

        if (LeagueFixtureStatus::Scheduled !== $fixture->getStatus()) {
            throw new UserFacingException('error.formation_only_scheduled');
        }
    }
}
