<?php

declare(strict_types=1);

namespace App\Service\Formation;

use App\Entity\Formation\Formation;
use App\Entity\Formation\FormationSlot;
use App\Entity\Team\Team;
use App\Enum\FormationApproach;
use App\Enum\FormationPosition;
use App\Repository\Formation\FormationRepository;
use App\Repository\Formation\FormationSlotRepository;
use App\Repository\Hero\HeroRepository;
use Doctrine\ORM\EntityManagerInterface;

class FormationService
{
    public function __construct(
        private readonly FormationRepository $formationRepository,
        private readonly FormationSlotRepository $slotRepository,
        private readonly HeroRepository $heroRepository,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /** @return list<Formation> */
    public function listByTeam(Team $team): array
    {
        return $this->formationRepository->findBy(['team' => $team], ['isDefault' => 'DESC', 'id' => 'ASC']);
    }

    public function findForTeam(int $id, Team $team): ?Formation
    {
        return $this->formationRepository->findOneBy(['id' => $id, 'team' => $team]);
    }

    /**
     * Create a new formation or update an existing one (matched by id).
     *
     * @param list<array{position: string, hero_id: int|null, strategy: array, spell_priorities: array}> $slotsData
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
    ): Formation {
        $name = trim($name);
        if ('' === $name) {
            throw new \InvalidArgumentException('Formation name cannot be empty.');
        }

        $formation = null;
        if (null !== $id) {
            $formation = $this->findForTeam($id, $team);
            if (null === $formation) {
                throw new \DomainException('Formation not found.');
            }
        }

        if (null === $formation) {
            $formation = new Formation();
            $formation->setTeam($team);
        }

        $formation->setName($name);
        $formation->setApproach($approach);

        if ($isDefault) {
            $this->clearDefaultFlag($team);
            $formation->setIsDefault(true);
        }

        // Remove existing slots so we rebuild them clean
        foreach ($this->slotRepository->findBy(['formation' => $formation]) as $oldSlot) {
            $this->em->remove($oldSlot);
        }

        $this->em->persist($formation);
        $this->em->flush(); // flush removals before adding new slots

        foreach ($slotsData as $slotData) {
            $positionValue = (string) ($slotData['position'] ?? '');
            $position = FormationPosition::tryFrom($positionValue);
            if (null === $position) {
                throw new \InvalidArgumentException(sprintf('Invalid position "%s".', $positionValue));
            }

            $hero = null;
            $heroId = isset($slotData['hero_id']) ? (int) $slotData['hero_id'] : null;
            if (null !== $heroId && $heroId > 0) {
                $hero = $this->heroRepository->findOneBy(['id' => $heroId, 'team' => $team]);
                if (null === $hero) {
                    throw new \DomainException(sprintf('Hero %d not found in your team.', $heroId));
                }
            }

            $slot = new FormationSlot();
            $slot->setFormation($formation);
            $slot->setPosition($position);
            $slot->setHero($hero);
            $slot->setStrategy(is_array($slotData['strategy'] ?? null) ? $slotData['strategy'] : []);
            $slot->setSpellPriorities(is_array($slotData['spell_priorities'] ?? null) ? $slotData['spell_priorities'] : []);

            $this->em->persist($slot);
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
            throw new \DomainException('Formation does not belong to your team.');
        }

        foreach ($this->slotRepository->findBy(['formation' => $formation]) as $slot) {
            $this->em->remove($slot);
        }

        $this->em->remove($formation);
        $this->em->flush();
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
            'is_default' => $formation->isDefault(),
            'slots' => $slots,
        ];
    }

    private function clearDefaultFlag(Team $team): void
    {
        foreach ($this->formationRepository->findBy(['team' => $team, 'isDefault' => true]) as $f) {
            $f->setIsDefault(false);
        }
    }
}
