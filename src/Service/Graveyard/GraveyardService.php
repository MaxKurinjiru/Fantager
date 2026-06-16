<?php

declare(strict_types=1);

namespace App\Service\Graveyard;

use App\Entity\Graveyard\GraveyardMemorial;
use App\Entity\Hero\Hero;
use App\Entity\Item\Item;
use App\Entity\Team\Team;
use App\Enum\HeroRole;
use App\Enum\MemorialCause;
use Doctrine\ORM\EntityManagerInterface;

class GraveyardService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function recordMemorial(Hero $hero, Team $team, MemorialCause $cause, ?\DateTimeImmutable $date = null): GraveyardMemorial
    {
        $date ??= new \DateTimeImmutable();

        $record = new GraveyardMemorial();
        $record->setTeam($team);
        $record->setName($hero->getName());
        $record->setRace($hero->getRace());
        $record->setRoleAtDeparture($hero->getRole());
        $record->setCause($cause);
        $record->setAge($hero->getAge());
        $record->setFinalLevel(HeroRole::Combatant === $hero->getRole() ? $hero->getLevel() : null);
        $record->setFinalStats($this->buildStatsSnapshot($hero));
        $record->setDepartedAt($date);
        $record->setOriginalHeroId($hero->getId());

        $this->em->persist($record);

        return $record;
    }

    public function prepareHeroRemoval(Hero $hero): void
    {
        if (!$hero->isCombatant()) {
            throw new \DomainException('Only combatant heroes can be removed through hero dismissal flow.');
        }

        /** @var list<Item> $equippedItems */
        $equippedItems = $this->em->getRepository(Item::class)->findBy(['equippedHero' => $hero]);
        foreach ($equippedItems as $item) {
            $item->setEquippedHero(null);
            $item->setEquippedSlot(null);
        }

        /** @var list<\App\Entity\Formation\FormationSlot> $slots */
        $slots = $this->em->getRepository(\App\Entity\Formation\FormationSlot::class)->findBy(['hero' => $hero]);
        foreach ($slots as $slot) {
            $slot->setHero(null);
        }

        if (null !== $hero->getTrainer()) {
            $hero->getTrainer()->removeTrainee($hero);
            $hero->setTrainer(null);
        }

        foreach ($hero->getHeroSpells()->toArray() as $heroSpell) {
            $this->em->remove($heroSpell);
        }

        foreach ($hero->getSchoolMasteries()->toArray() as $mastery) {
            $this->em->remove($mastery);
        }

        /** @var list<\App\Entity\Hero\HeroTrainingHistory> $historyEntries */
        $historyEntries = $this->em->getRepository(\App\Entity\Hero\HeroTrainingHistory::class)->findBy(['hero' => $hero]);
        foreach ($historyEntries as $entry) {
            $this->em->remove($entry);
        }
    }

    public function prepareTrainerRemoval(Hero $trainer): void
    {
        if (!$trainer->isTrainer()) {
            throw new \DomainException('Only trainer heroes can be removed through trainer dismissal flow.');
        }

        foreach ($trainer->getTrainees()->toArray() as $hero) {
            $trainer->removeTrainee($hero);
        }
    }

    public function removeHero(Hero $hero): void
    {
        $this->em->remove($hero);
    }

    /**
     * @return array<string, int>
     */
    public function buildStatsSnapshot(Hero $hero): array
    {
        $stats = [
            'str' => $hero->getStr(),
            'dex' => $hero->getDex(),
            'kon' => $hero->getKon(),
            'spd' => $hero->getSpd(),
            'int' => $hero->getIntel(),
            'wil' => $hero->getWil(),
            'cha' => $hero->getCha(),
            'lck' => $hero->getLck(),
        ];

        if ($hero->isCombatant()) {
            $stats['form'] = $hero->getForm();
            $stats['fatigue'] = $hero->getFatigue();
            $stats['morale'] = $hero->getMorale();
        }

        return $stats;
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeMemorial(GraveyardMemorial $record): array
    {
        return [
            'id' => $record->getId(),
            'type' => $record->getRoleAtDeparture()->value,
            'name' => $record->getName(),
            'race' => $record->getRace()->value,
            'final_level' => $record->getFinalLevel(),
            'age' => $record->getAge(),
            'cause' => $record->getCause()->value,
            'final_stats' => $record->getFinalStats(),
            'date' => $record->getDepartedAt()->format('Y-m-d'),
            'original_hero_id' => $record->getOriginalHeroId(),
        ];
    }
}
