<?php

declare(strict_types=1);

namespace App\Service\Graveyard;

use App\Entity\Graveyard\GraveyardMemorial;
use App\Entity\Hero\Hero;
use App\Entity\Item\Item;
use App\Entity\Team\Team;
use App\Enum\HeroRole;
use App\Enum\MemorialCause;
use App\Exception\UserFacingException;
use App\Service\Hero\HeroChronicleService;
use App\Service\Hero\HeroRatingCalculator;
use App\Service\Marketplace\MarketplaceService;
use Doctrine\ORM\EntityManagerInterface;

class GraveyardService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly HeroRatingCalculator $heroRatingCalculator,
        private readonly HeroChronicleService $heroChronicleService,
        private readonly MarketplaceService $marketplaceService,
    ) {
    }

    public function createMemorial(Hero $hero, Team $team, MemorialCause $cause, ?\DateTimeImmutable $date = null): GraveyardMemorial
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
        $record->setTrait($hero->getTrait());
        $record->setMatchesPlayed($hero->getMatchesPlayed());
        $record->setMatchesWon($hero->getMatchesWon());
        $record->setCombatsFallen($hero->getCombatsFallen());

        return $record;
    }

    public function recordMemorial(Hero $hero, Team $team, MemorialCause $cause, ?\DateTimeImmutable $date = null): GraveyardMemorial
    {
        $record = $this->createMemorial($hero, $team, $cause, $date);

        $this->em->persist($record);
        $this->heroChronicleService->recordDied($hero, $cause->value);

        return $record;
    }

    public function prepareHeroRemoval(Hero $hero): void
    {
        if (!$hero->isCombatant()) {
            throw new UserFacingException('error.graveyard_combatant_only');
        }

        $this->marketplaceService->forceCancelActiveListingsForHero($hero);

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

        // Spells, school masteries, and training history stay so the hero profile
        // and chronicle remain readable after dismissal, same as combat death.
    }

    public function prepareTrainerRemoval(Hero $trainer): void
    {
        if (!$trainer->isTrainer()) {
            throw new UserFacingException('error.graveyard_trainer_only');
        }

        $this->marketplaceService->forceCancelActiveListingsForHero($trainer);

        foreach ($trainer->getTrainees()->toArray() as $hero) {
            $trainer->removeTrainee($hero);
        }
    }

    /**
     * Detach a hero who died in combat from the active roster.
     *
     * Clears formation slots, trainer assignment, equipped items, and marketplace
     * listings. Spells, school masteries, and training history stay. The caller
     * sets status to dead; the hero row is not deleted.
     */
    public function prepareCombatDeath(Hero $hero): void
    {
        // 1. Remove from all formation slots
        /** @var list<\App\Entity\Formation\FormationSlot> $slots */
        $slots = $this->em->getRepository(\App\Entity\Formation\FormationSlot::class)->findBy(['hero' => $hero]);
        foreach ($slots as $slot) {
            $slot->setHero(null);
        }

        // 2. Detach from trainer if currently training
        if (null !== $hero->getTrainer()) {
            $hero->getTrainer()->removeTrainee($hero);
            $hero->setTrainer(null);
        }

        // 3. Unequip items (keep items in team inventory — hero's gear stays on the team)
        /** @var list<Item> $equippedItems */
        $equippedItems = $this->em->getRepository(Item::class)->findBy(['equippedHero' => $hero]);
        foreach ($equippedItems as $item) {
            $item->setEquippedHero(null);
            $item->setEquippedSlot(null);
        }

        // 4. Cancel active hero/trainer marketplace listings and refund reserved bids
        $this->marketplaceService->forceCancelActiveListingsForHero($hero);

        // NOTE: spells, school masteries, and training history stay so the hero
        // profile and chronicle remain readable after death.
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

        $rating = $this->heroRatingCalculator->calculate($hero);
        $stats['base_ovr'] = $rating->getBaseOvr();
        $stats['complex_rating'] = $rating->getComplexRating();

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
            'trait' => $record->getTrait(),
            'matches_played' => $record->getMatchesPlayed(),
            'matches_won' => $record->getMatchesWon(),
            'combats_fallen' => $record->getCombatsFallen(),
        ];
    }
}
