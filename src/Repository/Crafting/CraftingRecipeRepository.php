<?php

declare(strict_types=1);

namespace App\Repository\Crafting;

use App\Entity\Crafting\CraftingRecipe;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CraftingRecipeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CraftingRecipe::class);
    }
}
