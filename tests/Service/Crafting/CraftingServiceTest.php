<?php

declare(strict_types=1);

namespace App\Tests\Service\Crafting;

use App\Entity\Crafting\CraftingQueue;
use App\Entity\Crafting\CraftingRecipe;
use App\Entity\Headquarters\Facility;
use App\Entity\Headquarters\Headquarters;
use App\Entity\Team\Team;
use App\Enum\CraftingStatus;
use App\Enum\FacilityType;
use App\Enum\ItemCategory;
use App\Enum\ItemRarity;
use App\Repository\Crafting\CraftingQueueRepository;
use App\Repository\Crafting\CraftingRecipeRepository;
use App\Repository\Headquarters\HeadquartersRepository;
use App\Service\Crafting\CraftingService;
use App\Service\Economy\EconomyService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class CraftingServiceTest extends TestCase
{
    private CraftingRecipeRepository $recipeRepositoryMock;
    private CraftingQueueRepository $queueRepositoryMock;
    private HeadquartersRepository $hqRepositoryMock;
    private EconomyService $economyServiceMock;
    private EntityManagerInterface $entityManagerMock;
    private CraftingService $craftingService;

    protected function setUp(): void
    {
        $this->recipeRepositoryMock = $this->createMock(CraftingRecipeRepository::class);
        $this->queueRepositoryMock = $this->createMock(CraftingQueueRepository::class);
        $this->hqRepositoryMock = $this->createMock(HeadquartersRepository::class);
        $this->economyServiceMock = $this->createMock(EconomyService::class);
        $this->entityManagerMock = $this->createMock(EntityManagerInterface::class);

        $this->craftingService = new CraftingService(
            $this->recipeRepositoryMock,
            $this->queueRepositoryMock,
            $this->hqRepositoryMock,
            $this->economyServiceMock,
            $this->entityManagerMock,
        );
    }

    public function testStartJobRejectsInsufficientForgeLevel(): void
    {
        $team = new Team();
        $team->setGold(1000);

        $recipe = new CraftingRecipe();
        $recipe->setRequiredForgeLevel(3);
        $recipe->setGoldCost(100);
        $recipe->setEssenceCostAmount(0);
        $recipe->setCraftingTime(60);
        $recipe->setResultItemCategory(ItemCategory::Weapon);
        $recipe->setResultItemRarity(ItemRarity::Common);

        $hq = new Headquarters();
        $forge = new Facility();
        $forge->setType(FacilityType::Forge);
        $forge->setLevel(1);
        $hq->addFacility($forge);

        $this->recipeRepositoryMock->method('find')->with(1)->willReturn($recipe);
        $this->hqRepositoryMock->method('findOneBy')->willReturn($hq);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Forge level 3 required');

        $this->craftingService->startJob($team, 1, new \DateTimeImmutable('now'));
    }

    public function testProcessDueJobsCompletesSuccessfulCraft(): void
    {
        $team = new Team();
        $recipe = new CraftingRecipe();
        $recipe->setResultItemCategory(ItemCategory::Weapon);
        $recipe->setResultItemRarity(ItemRarity::Common);
        $recipe->setSuccessRateBase('1.00');

        $job = new CraftingQueue();
        $job->setTeam($team);
        $job->setRecipe($recipe);
        $job->setStatus(CraftingStatus::InProgress);
        $job->setStartedAt(new \DateTimeImmutable('-1 hour'));
        $job->setCompletesAt(new \DateTimeImmutable('-1 minute'));

        $this->queueRepositoryMock
            ->method('findDueJobs')
            ->willReturn([$job]);

        $this->entityManagerMock->expects($this->once())->method('persist');
        $this->entityManagerMock->expects($this->once())->method('flush');

        $result = $this->craftingService->processDueJobs(new \DateTimeImmutable('now'));

        $this->assertSame(1, $result['completed']);
        $this->assertSame(0, $result['failed']);
        $this->assertSame(CraftingStatus::Completed, $job->getStatus());
    }
}
