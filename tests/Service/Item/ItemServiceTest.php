<?php

declare(strict_types=1);

namespace App\Tests\Service\Item;

use App\Entity\Hero\Hero;
use App\Entity\Item\Item;
use App\Entity\Marketplace\MarketplaceTransaction;
use App\Entity\Team\Team;
use App\Enum\ItemRarity;
use App\Enum\ItemSlotType;
use App\Enum\ItemStatus;
use App\Repository\Item\ItemRepository;
use App\Exception\UserFacingException;
use App\Service\Item\ItemService;
use App\Service\Economy\EconomyService;
use App\Service\TeamChronicle\TeamChronicleService;
use App\Service\Translation\UserMessageTranslator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class ItemServiceTest extends TestCase
{
    private ItemRepository&MockObject $itemRepositoryMock;
    private EntityManagerInterface&MockObject $entityManagerMock;
    private TranslatorInterface&MockObject $symfonyTranslatorMock;
    private RequestStack&MockObject $requestStackMock;
    private EconomyService&MockObject $economyServiceMock;
    private TeamChronicleService&MockObject $teamChronicleServiceMock;
    private ItemService $itemService;

    protected function setUp(): void
    {
        $this->itemRepositoryMock = $this->createMock(ItemRepository::class);
        $this->entityManagerMock = $this->createMock(EntityManagerInterface::class);
        $this->symfonyTranslatorMock = $this->createMock(TranslatorInterface::class);
        $this->requestStackMock = $this->createMock(RequestStack::class);
        $this->economyServiceMock = $this->createMock(EconomyService::class);
        $this->economyServiceMock
            ->method('deductGold')
            ->willReturnCallback(static function (Team $team, int $amount): void {
                $team->setGold($team->getGold() - $amount);
            });
        $this->teamChronicleServiceMock = $this->createMock(TeamChronicleService::class);
        
        $translator = new UserMessageTranslator($this->symfonyTranslatorMock, $this->requestStackMock);
        
        $this->itemService = new ItemService(
            $this->itemRepositoryMock,
            $this->entityManagerMock,
            $translator,
            $this->economyServiceMock,
            $this->teamChronicleServiceMock
        );
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

        $this->expectException(UserFacingException::class);
        $this->expectExceptionMessage('error.item_cannot_dismantle_equipped');

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

        $this->expectException(UserFacingException::class);
        $this->expectExceptionMessage('error.item_slot_mismatch');

        $this->itemService->equip($item, $hero, ItemSlotType::Body);
    }

    public function testPurchaseBasicItemSuccess(): void
    {
        $team = new Team();
        $team->setGold(100);

        $this->symfonyTranslatorMock
            ->method('trans')
            ->willReturnCallback(static function (
                string $id,
                array $params = [],
                ?string $domain = null,
                ?string $locale = null,
            ): string {
                self::assertSame('item.short_sword', $id);
                self::assertSame([], $params);
                self::assertSame('messages', $domain);
                self::assertSame('cs', $locale);

                return 'Short Sword';
            });

        $this->entityManagerMock
            ->method('persist')
            ->willReturnCallback(static function (object $entity): void {
                self::assertTrue($entity instanceof Item || $entity instanceof MarketplaceTransaction || $entity instanceof \App\Entity\Team\TeamChronicle);
            });
        $this->entityManagerMock->expects($this->exactly(2))
            ->method('flush');

        $item = $this->itemService->purchaseBasicItem($team, 'short_sword');

        $this->assertSame(50, $team->getGold());
        $this->assertSame($team, $item->getOwnerTeam());
        $this->assertSame('Short Sword', $item->getName());
        $this->assertSame(ItemSlotType::MainHand, $item->getSlotType());
        $this->assertSame(100, $item->getDurability());
        $this->assertSame(['damage' => 10], $item->getBonuses());
    }

    public function testPurchaseBasicItemInsufficientGold(): void
    {
        $team = new Team();
        $team->setGold(10);

        $this->expectException(UserFacingException::class);
        $this->expectExceptionMessage('error.item_purchase_insufficient_gold');

        $this->itemService->purchaseBasicItem($team, 'short_sword');
    }
}
