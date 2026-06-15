<?php

declare(strict_types=1);

namespace App\Tests\Service\Item;

use App\Entity\Hero\Hero;
use App\Entity\Item\Item;
use App\Entity\Team\Team;
use App\Enum\ItemRarity;
use App\Enum\ItemSlotType;
use App\Enum\ItemStatus;
use App\Repository\Item\ItemRepository;
use App\Service\Item\ItemService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class ItemServiceTest extends TestCase
{
    private ItemRepository $itemRepositoryMock;
    private EntityManagerInterface $entityManagerMock;
    private ItemService $itemService;

    protected function setUp(): void
    {
        $this->itemRepositoryMock = $this->createMock(ItemRepository::class);
        $this->entityManagerMock = $this->createMock(EntityManagerInterface::class);
        $this->itemService = new ItemService($this->itemRepositoryMock, $this->entityManagerMock);
    }

    public function testDismantleRejectsEquippedItem(): void
    {
        $team = new Team();
        $hero = new Hero();
        $item = new Item();
        $item->setOwnerTeam($team);
        $item->setStatus(ItemStatus::Available);
        $item->setEquippedHero($hero);
        $item->setRarity(ItemRarity::Common);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot dismantle an equipped item.');

        $this->itemService->dismantle($item, $team);
    }

    public function testCalculateRepairCostScalesWithMissingDurability(): void
    {
        $item = new Item();
        $item->setRarity(ItemRarity::Rare);
        $item->setDurability(80);

        $this->assertSame(200, $this->itemService->calculateRepairCost($item));
    }

    public function testEquipRejectsWrongSlotType(): void
    {
        $team = new Team();
        $hero = new Hero();
        $hero->setTeam($team);

        $item = new Item();
        $item->setOwnerTeam($team);
        $item->setStatus(ItemStatus::Available);
        $item->setSlotType(ItemSlotType::Head);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('cannot be equipped in slot');

        $this->itemService->equip($item, $hero, ItemSlotType::Body);
    }
}
