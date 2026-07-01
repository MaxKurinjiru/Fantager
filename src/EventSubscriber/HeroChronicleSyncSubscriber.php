<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\Hero\HeroChronicle;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(event: Events::postPersist)]
class HeroChronicleSyncSubscriber
{
    public function postPersist(PostPersistEventArgs $args): void
    {
        $entity = $args->getObject();
        if ($entity instanceof HeroChronicle && null === $entity->getOriginalHeroId() && null !== $entity->getHero()) {
            $heroId = $entity->getHero()->getId();
            if (null !== $heroId) {
                $em = $args->getObjectManager();
                $em->getConnection()->executeStatement(
                    'UPDATE hero_chronicle SET original_hero_id = ? WHERE id = ?',
                    [$heroId, $entity->getId()]
                );
            }
        }
    }
}
