<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\Hero\Hero;
use App\Entity\Hero\SchoolMastery;
use App\Entity\Hero\WeaponMastery;
use App\Service\Hero\HeroRatingCalculator;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(event: Events::onFlush)]
class HeroRatingCacheSubscriber
{
    public function __construct(
        private readonly HeroRatingCalculator $heroRatingCalculator,
    ) {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $em = $args->getObjectManager();
        $uow = $em->getUnitOfWork();

        /** @var array<int, Hero> $heroes */
        $heroes = [];

        foreach ($uow->getScheduledEntityInsertions() as $entity) {
            $this->collectHero($entity, $heroes);
        }

        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            $this->collectHero($entity, $heroes);
        }

        foreach ($uow->getScheduledEntityDeletions() as $entity) {
            $this->collectHero($entity, $heroes);
        }

        if ([] === $heroes) {
            return;
        }

        $meta = $em->getClassMetadata(Hero::class);

        foreach ($heroes as $hero) {
            if (!$em->contains($hero)) {
                continue;
            }

            $rating = $this->heroRatingCalculator->calculate($hero);
            $hero->setBaseOvr($rating->getBaseOvr());
            $hero->setComplexRating($rating->getComplexRating());

            if ($uow->isScheduledForInsert($hero)) {
                $uow->recomputeSingleEntityChangeSet($meta, $hero);
            } elseif ($uow->isInIdentityMap($hero)) {
                $uow->recomputeSingleEntityChangeSet($meta, $hero);
            }
        }
    }

    /**
     * @param array<int, Hero> $heroes
     */
    private function collectHero(object $entity, array &$heroes): void
    {
        if ($entity instanceof Hero) {
            $id = spl_object_id($entity);
            $heroes[$id] = $entity;

            return;
        }

        if ($entity instanceof WeaponMastery || $entity instanceof SchoolMastery) {
            $hero = $entity->getHero();
            $id = spl_object_id($hero);
            $heroes[$id] = $hero;
        }
    }
}
