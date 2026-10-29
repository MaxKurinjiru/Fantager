<?php

declare(strict_types=1);

namespace App\Service\Formation;

use App\Entity\Formation\Formation;
use App\Entity\Formation\FormationSlot;
use App\Entity\League\LeagueFixture;
use App\Entity\Team\Team;
use App\Enum\FormationApproach;
use App\Enum\FormationPosition;
use App\Exception\UserFacingException;
use App\Repository\Formation\FormationRepository;
use App\Repository\Formation\FormationSlotRepository;
use App\Repository\Hero\HeroRepository;
use App\Repository\League\LeagueFixtureRepository;
use Doctrine\ORM\EntityManagerInterface;

class FormationService
{
    public const MAX_SAVED_FORMATIONS = 4;

    public function __construct(
        private readonly FormationRepository $formationRepository,
        private readonly FormationSlotRepository $slotRepository,
        private readonly HeroRepository $heroRepository,
        private readonly LeagueFixtureRepository $fixtureRepository,
        private readonly EntityManagerInterface $em,
        private readonly FormationTacticsNormalizer $tacticsNormalizer = new FormationTacticsNormalizer(),
    ) {
    }

    /** @return list<Formation> */
    public function listByTeam(Team $team): array
    {
        return $this->formationRepository->findSavedByTeam($team);
    }

    public function findForTeam(int $id, Team $team): ?Formation
    {
        $formation = $this->formationRepository->findOneBy(['id' => $id, 'team' => $team]);

        if (null !== $formation && $formation->isTemporary()) {
            return null;
        }

        return $formation;
    }

    public function findDefaultForTeam(Team $team): ?Formation
    {
        return $this->formationRepository->findDefaultForTeam($team);
    }

    /**
     * @throws \DomainException
     */
    public function requireDefaultForTeam(Team $team): Formation
    {
        $formation = $this->findDefaultForTeam($team);
        if (null === $formation) {
            throw new UserFacingException('error.formation_no_default');
        }

        return $formation;
    }

    /**
     * Create a new formation or update an existing one (matched by id).
     *
     * @param list<array{position: string, hero_id: int|null, strategy: array<string, mixed>, spell_priorities: array<mixed>}> $slotsData
     * @param array<string, mixed>                                                                                             $tacticsDefaults
     *
     * @throws \DomainException|\InvalidArgumentException
     */
    public function save(
        Team $team,
        ?int $id,
        string $name,
        FormationApproach $approach,
        array $slotsData,
        bool $isDefault,
        array $tacticsDefaults = [],
    ): Formation {
        $name = trim($name);
        if ('' === $name) {
            throw new UserFacingException('error.formation_name_empty');
        }

        $formation = null;
        if (null !== $id) {
            $formation = $this->findForTeam($id, $team);
            if (null === $formation) {
                throw new UserFacingException('error.formation_not_found');
            }
        }

        if (null === $formation) {
            $this->assertCanCreateSavedFormation($team);
            $formation = new Formation();
            $formation->setTeam($team);
        }

        $formation->setName($name);
        $formation->setApproach($approach);
        $formation->setTacticsDefaults($this->tacticsNormalizer->normalizeTacticsDefaults($tacticsDefaults));
        $formation->setIsTemporary(false);
        $formation->setSourceFixture(null);

        if ($isDefault) {
            $this->clearDefaultFlag($team);
            $formation->setIsDefault(true);
        }

        $this->rebuildSlots($formation, $team, $slotsData, $formation->getTacticsDefaults());

        return $formation;
    }

    /**
     * @param list<array{position: string, hero_id: int|null, strategy: array<string, mixed>, spell_priorities: array<mixed>}> $slotsData
     * @param array<string, mixed>                                                                                             $tacticsDefaults
     *
     * @throws \DomainException|\InvalidArgumentException
     */
    public function saveTemporary(
        Formation $formation,
        Team $team,
        LeagueFixture $fixture,
        string $name,
        FormationApproach $approach,
        array $slotsData,
        array $tacticsDefaults = [],
    ): Formation {
        if ($formation->getTeam()->getId() !== $team->getId()) {
            throw new UserFacingException('error.formation_not_on_team');
        }

        if (!$formation->isTemporary()) {
            throw new UserFacingException('error.formation_only_temp_update');
        }

        if ($formation->getSourceFixture()?->getId() !== $fixture->getId()) {
            throw new UserFacingException('error.formation_wrong_fixture');
        }

        $name = trim($name);
        if ('' === $name) {
            throw new UserFacingException('error.formation_name_empty');
        }

        $formation->setName($name);
        $formation->setApproach($approach);
        $formation->setTacticsDefaults($this->tacticsNormalizer->normalizeTacticsDefaults($tacticsDefaults));
        $this->rebuildSlots($formation, $team, $slotsData, $formation->getTacticsDefaults());

        return $formation;
    }

    /**
     * @param list<array{position: string, hero_id: int|null, strategy: array<string, mixed>, spell_priorities: array<mixed>}> $slotsData
     * @param array<string, mixed>                                                                                             $tacticsDefaults
     *
     * @throws \DomainException|\InvalidArgumentException
     */
    public function createTemporary(
        Team $team,
        LeagueFixture $fixture,
        string $name,
        FormationApproach $approach,
        array $slotsData,
        array $tacticsDefaults = [],
    ): Formation {
        $formation = new Formation();
        $formation->setTeam($team);
        $formation->setIsTemporary(true);
        $formation->setSourceFixture($fixture);
        $formation->setIsDefault(false);
        $formation->setName(trim($name));
        $formation->setApproach($approach);
        $formation->setTacticsDefaults($this->tacticsNormalizer->normalizeTacticsDefaults($tacticsDefaults));

        if ('' === $formation->getName()) {
            throw new UserFacingException('error.formation_name_empty');
        }

        $this->em->persist($formation);
        $this->rebuildSlots($formation, $team, $slotsData, $formation->getTacticsDefaults());

        return $formation;
    }

    /**
     * @throws \DomainException
     */
    public function promoteTemporary(
        Formation $formation,
        Team $team,
        string $name,
        bool $isDefault,
    ): Formation {
        if ($formation->getTeam()->getId() !== $team->getId()) {
            throw new UserFacingException('error.formation_not_on_team');
        }

        if (!$formation->isTemporary()) {
            throw new UserFacingException('error.formation_only_temp_promote');
        }

        $this->assertCanCreateSavedFormation($team);

        $name = trim($name);
        if ('' === $name) {
            throw new UserFacingException('error.formation_name_empty');
        }

        $formation->setName($name);
        $formation->setIsTemporary(false);
        $formation->setSourceFixture(null);

        if ($isDefault) {
            $this->clearDefaultFlag($team);
            $formation->setIsDefault(true);
        }

        $this->em->flush();

        return $formation;
    }

    /**
     * Delete a formation.
     *
     * @throws \DomainException
     */
    public function delete(Formation $formation, Team $team): void
    {
        if ($formation->getTeam()->getId() !== $team->getId()) {
            throw new UserFacingException('error.formation_not_on_team');
        }

        if ($formation->isTemporary()) {
            throw new UserFacingException('error.formation_only_temp_delete');
        }

        $this->clearFixtureReferences($formation);
        $this->removeFormation($formation);
    }

    public function deleteTemporary(Formation $formation): void
    {
        if (!$formation->isTemporary()) {
            throw new UserFacingException('error.formation_only_temp_delete');
        }

        $this->clearFixtureReferences($formation);
        $this->removeFormation($formation);
    }

    /** @return array<string, mixed> */
    public function serialize(Formation $formation): array
    {
        $slots = [];
        foreach ($formation->getSlots() as $slot) {
            $slots[] = [
                'position' => $slot->getPosition()->value,
                'hero_id' => $slot->getHero()?->getId(),
                'hero_name' => $slot->getHero()?->getName(),
                'strategy' => $slot->getStrategy(),
                'spell_priorities' => $slot->getSpellPriorities(),
            ];
        }

        return [
            'id' => $formation->getId(),
            'name' => $formation->getName(),
            'approach' => $formation->getApproach()->value,
            'tactics_defaults' => $this->tacticsNormalizer->normalizeTacticsDefaults($formation->getTacticsDefaults()),
            'is_default' => $formation->isDefault(),
            'is_temporary' => $formation->isTemporary(),
            'source_fixture_id' => $formation->getSourceFixture()?->getId(),
            'slots' => $slots,
            'saved_limit' => self::MAX_SAVED_FORMATIONS,
            'saved_count' => $this->formationRepository->countSavedByTeam($formation->getTeam()),
        ];
    }

    /**
     * @throws \DomainException
     */
    public function assertCanCreateSavedFormation(Team $team): void
    {
        if ($this->formationRepository->countSavedByTeam($team) >= self::MAX_SAVED_FORMATIONS) {
            throw new UserFacingException('error.formation_max_saved', ['%max%' => self::MAX_SAVED_FORMATIONS]);
        }
    }

    /**
     * @param list<array{position: string, hero_id: int|null, strategy: array<string, mixed>, spell_priorities: array<mixed>}> $slotsData
     * @param array<string, mixed>                                                                                             $tacticsDefaults
     *
     * @throws \DomainException|\InvalidArgumentException
     */
    private function rebuildSlots(Formation $formation, Team $team, array $slotsData, array $tacticsDefaults = []): void
    {
        $prepared = $this->prepareSlots($team, $slotsData, $tacticsDefaults);

        foreach ($this->slotRepository->findBy(['formation' => $formation]) as $oldSlot) {
            $this->em->remove($oldSlot);
        }

        $this->em->persist($formation);
        $this->em->flush();

        foreach ($prepared as $slotData) {
            $slot = new FormationSlot();
            $slot->setFormation($formation);
            $slot->setPosition($slotData['position']);
            $slot->setHero($slotData['hero']);
            $slot->setStrategy($slotData['strategy']);
            $slot->setSpellPriorities($slotData['spell_priorities']);

            $this->em->persist($slot);
        }

        $this->em->flush();
    }

    /**
     * @param list<array{position: string, hero_id: int|null, strategy: array<string, mixed>, spell_priorities: array<mixed>}> $slotsData
     * @param array<string, mixed>                                                                                             $tacticsDefaults
     *
     * @return list<array{position: FormationPosition, hero: \App\Entity\Hero\Hero|null, strategy: array<string, mixed>, spell_priorities: array<mixed>}>
     */
    private function prepareSlots(Team $team, array $slotsData, array $tacticsDefaults = []): array
    {
        $prepared = [];
        $seenHeroIds = [];
        $defaults = $this->tacticsNormalizer->normalizeTacticsDefaults($tacticsDefaults);

        foreach ($slotsData as $slotData) {
            $positionValue = $slotData['position'];
            $position = FormationPosition::tryFrom($positionValue);
            if (null === $position) {
                throw new UserFacingException('error.formation_invalid_position', ['%position%' => $positionValue]);
            }

            $hero = null;
            $heroId = isset($slotData['hero_id']) ? (int) $slotData['hero_id'] : null;
            if (null !== $heroId && $heroId > 0) {
                $hero = $this->heroRepository->findOneBy(['id' => $heroId, 'team' => $team]);
                if (null === $hero) {
                    throw new UserFacingException('error.formation_hero_not_on_team', ['%id%' => $heroId]);
                }

                $resolvedId = $hero->getId();
                if (null !== $resolvedId && isset($seenHeroIds[$resolvedId])) {
                    throw new UserFacingException('error.formation_duplicate_hero');
                }
                if (null !== $resolvedId) {
                    $seenHeroIds[$resolvedId] = true;
                }
            }

            $prepared[] = [
                'position' => $position,
                'hero' => $hero,
                'strategy' => $this->tacticsNormalizer->resolveSlotStrategy(
                    $slotData['strategy'],
                    $defaults,
                    $position->value,
                    true,
                ),
                'spell_priorities' => $this->tacticsNormalizer->normalizeSpellPriorities($slotData['spell_priorities']),
            ];
        }

        return $prepared;
    }

    private function removeFormation(Formation $formation): void
    {
        foreach ($this->slotRepository->findBy(['formation' => $formation]) as $slot) {
            $this->em->remove($slot);
        }

        $this->em->remove($formation);
        $this->em->flush();
    }

    private function clearFixtureReferences(Formation $formation): void
    {
        $formationId = $formation->getId();
        if (null === $formationId) {
            return;
        }

        /** @var list<LeagueFixture> $fixtures */
        $fixtures = $this->fixtureRepository->createQueryBuilder('f')
            ->where('f.homeFormation = :formation OR f.awayFormation = :formation')
            ->setParameter('formation', $formation)
            ->getQuery()
            ->getResult();

        foreach ($fixtures as $fixture) {
            if ($fixture->getHomeFormation()?->getId() === $formationId) {
                $fixture->setHomeFormation(null);
            }
            if ($fixture->getAwayFormation()?->getId() === $formationId) {
                $fixture->setAwayFormation(null);
            }
        }
    }

    private function clearDefaultFlag(Team $team): void
    {
        foreach ($this->formationRepository->findBy(['team' => $team, 'isDefault' => true, 'isTemporary' => false]) as $f) {
            $f->setIsDefault(false);
        }
    }
}
