<?php

declare(strict_types=1);

namespace App\Service\Graveyard;

use App\Entity\Graveyard\GraveyardRecord;
use App\Entity\Graveyard\StaffRecord;
use App\Entity\Hero\Hero;
use App\Entity\Item\Item;
use App\Entity\Team\Team;
use App\Entity\Training\Trainer;
use App\Enum\GraveyardCause;
use App\Enum\StaffDepartureCause;
use Doctrine\ORM\EntityManagerInterface;

class GraveyardService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function recordHero(Hero $hero, Team $team, GraveyardCause $cause, ?\DateTimeImmutable $date = null): GraveyardRecord
    {
        $date ??= new \DateTimeImmutable();

        $record = new GraveyardRecord();
        $record->setTeam($team);
        $record->setHeroName($hero->getName());
        $record->setRace($hero->getRace());
        $record->setFinalLevel($hero->getLevel());
        $record->setAgeAtDeath($hero->getAge());
        $record->setCauseOfDeath($cause->value);
        $record->setTotalBattles(0);
        $record->setVictories(0);
        $record->setFinalStats($this->buildHeroStatsSnapshot($hero));
        $record->setAchievements([]);
        $record->setDateOfDeath($date);
        $record->setOriginalHeroId($hero->getId());

        $this->em->persist($record);

        return $record;
    }

    public function recordTrainer(Trainer $trainer, Team $team, StaffDepartureCause $cause, ?\DateTimeImmutable $date = null): StaffRecord
    {
        $date ??= new \DateTimeImmutable();

        $record = new StaffRecord();
        $record->setTeam($team);
        $record->setName($trainer->getName());
        $record->setRace($trainer->getRace());
        $record->setAge($trainer->getAge());
        $record->setCause($cause);
        $record->setTrainingType($trainer->getTrainingType()?->value);
        $record->setFinalStats($this->buildTrainerStatsSnapshot($trainer));
        $record->setTraineesCount($trainer->getHeroes()->count());
        $record->setOriginalTrainerId($trainer->getId());
        $record->setDateOfDeparture($date);

        $this->em->persist($record);

        return $record;
    }

    public function prepareHeroRemoval(Hero $hero): void
    {
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
            $hero->getTrainer()->removeHero($hero);
            $hero->setTrainer(null);
        }

        foreach ($hero->getHeroSpells()->toArray() as $heroSpell) {
            $this->em->remove($heroSpell);
        }

        foreach ($hero->getSchoolMasteries()->toArray() as $mastery) {
            $this->em->remove($mastery);
        }

        /** @var list<\App\Entity\Training\TrainingQueue> $queueEntries */
        $queueEntries = $this->em->getRepository(\App\Entity\Training\TrainingQueue::class)->findBy(['hero' => $hero]);
        foreach ($queueEntries as $entry) {
            $this->em->remove($entry);
        }
    }

    public function prepareTrainerRemoval(Trainer $trainer): void
    {
        foreach ($trainer->getHeroes()->toArray() as $hero) {
            $trainer->removeHero($hero);
        }
    }

    public function removeHero(Hero $hero): void
    {
        $this->em->remove($hero);
    }

    public function removeTrainer(Trainer $trainer): void
    {
        $this->em->remove($trainer);
    }

    /**
     * @return array<string, int>
     */
    public function buildHeroStatsSnapshot(Hero $hero): array
    {
        return [
            'str' => $hero->getStr(),
            'dex' => $hero->getDex(),
            'kon' => $hero->getKon(),
            'spd' => $hero->getSpd(),
            'int' => $hero->getIntel(),
            'wil' => $hero->getWil(),
            'cha' => $hero->getCha(),
            'lck' => $hero->getLck(),
            'form' => $hero->getForm(),
            'fatigue' => $hero->getFatigue(),
            'morale' => $hero->getMorale(),
        ];
    }

    /**
     * @return array<string, int>
     */
    public function buildTrainerStatsSnapshot(Trainer $trainer): array
    {
        return [
            'str' => $trainer->getStr(),
            'dex' => $trainer->getDex(),
            'kon' => $trainer->getKon(),
            'spd' => $trainer->getSpd(),
            'int' => $trainer->getIntel(),
            'wil' => $trainer->getWil(),
            'cha' => $trainer->getCha(),
            'lck' => $trainer->getLck(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeHeroRecord(GraveyardRecord $record): array
    {
        return [
            'id' => $record->getId(),
            'type' => 'hero',
            'name' => $record->getHeroName(),
            'race' => $record->getRace()->value,
            'final_level' => $record->getFinalLevel(),
            'age' => $record->getAgeAtDeath(),
            'cause' => $record->getCauseOfDeath(),
            'total_battles' => $record->getTotalBattles(),
            'victories' => $record->getVictories(),
            'final_stats' => $record->getFinalStats(),
            'achievements' => $record->getAchievements(),
            'date' => $record->getDateOfDeath()->format('Y-m-d'),
            'original_hero_id' => $record->getOriginalHeroId(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeStaffRecord(StaffRecord $record): array
    {
        return [
            'id' => $record->getId(),
            'type' => 'staff',
            'name' => $record->getName(),
            'race' => $record->getRace()->value,
            'age' => $record->getAge(),
            'cause' => $record->getCause()->value,
            'training_type' => $record->getTrainingType(),
            'final_stats' => $record->getFinalStats(),
            'trainees_count' => $record->getTraineesCount(),
            'date' => $record->getDateOfDeparture()->format('Y-m-d'),
            'original_trainer_id' => $record->getOriginalTrainerId(),
        ];
    }
}
