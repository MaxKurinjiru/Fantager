<?php

declare(strict_types=1);

namespace App\Repository\Hero;

use App\Entity\Hero\HeroSpell;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<HeroSpell>
 */
class HeroSpellRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HeroSpell::class);
    }
}
