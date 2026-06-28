<?php

declare(strict_types=1);

namespace App\Tests\Service\Hero;

use App\Entity\Hero\Hero;
use App\Entity\Hero\HeroSpell;
use App\Entity\Hero\SchoolMastery;
use App\Entity\Hero\WeaponMastery;
use App\Entity\Item\Item;
use App\Entity\Spell\Spell;
use App\Enum\ItemSubType;
use App\Enum\School;
use App\Repository\Hero\SchoolMasteryRepository;
use App\Repository\Hero\WeaponMasteryRepository;
use App\Repository\Item\ItemRepository;
use App\Service\Hero\HeroMasteryService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class HeroMasteryServiceTest extends TestCase
{
    private ItemRepository&MockObject $itemRepositoryMock;
    private WeaponMasteryRepository&MockObject $weaponMasteryRepositoryMock;
    private SchoolMasteryRepository&MockObject $schoolMasteryRepositoryMock;
    private EntityManagerInterface&MockObject $entityManagerMock;
    private HeroMasteryService $masteryService;

    protected function setUp(): void
    {
        $this->itemRepositoryMock = $this->createMock(ItemRepository::class);
        $this->weaponMasteryRepositoryMock = $this->createMock(WeaponMasteryRepository::class);
        $this->schoolMasteryRepositoryMock = $this->createMock(SchoolMasteryRepository::class);
        $this->entityManagerMock = $this->createMock(EntityManagerInterface::class);

        $this->masteryService = new HeroMasteryService(
            $this->itemRepositoryMock,
            $this->weaponMasteryRepositoryMock,
            $this->schoolMasteryRepositoryMock,
            $this->entityManagerMock
        );
    }

    public function testGetEquippedSubTypes(): void
    {
        $hero = new Hero();

        $item1 = new Item();
        $item1->setSubType(ItemSubType::OneHandedSword);

        $item2 = new Item();
        $item2->setSubType(ItemSubType::Shield);

        $item3 = new Item();
        $item3->setSubType(ItemSubType::LightArmor);

        $item4 = new Item(); // Null subType (accessory)

        $this->itemRepositoryMock
            ->method('findBy')
            ->willReturnCallback(static function (array $criteria) use ($hero, $item1, $item2, $item3, $item4): array {
                self::assertSame(['equippedHero' => $hero], $criteria);

                return [$item1, $item2, $item3, $item4];
            });

        $subTypes = $this->masteryService->getEquippedSubTypes($hero);

        $this->assertCount(3, $subTypes);
        $this->assertContains(ItemSubType::OneHandedSword, $subTypes);
        $this->assertContains(ItemSubType::Shield, $subTypes);
        $this->assertContains(ItemSubType::LightArmor, $subTypes);
    }

    public function testAddWeaponMasteryXpAndLevelsUp(): void
    {
        $hero = new Hero();
        $wm = new WeaponMastery();
        $wm->setHero($hero);
        $wm->setStyle(ItemSubType::OneHandedSword);
        $wm->setXp(90);
        $wm->setMasteryTier(1);

        $this->weaponMasteryRepositoryMock
            ->method('findOneBy')
            ->willReturnCallback(static function (array $criteria) use ($hero, $wm): WeaponMastery {
                self::assertSame(['hero' => $hero, 'style' => ItemSubType::OneHandedSword], $criteria);

                return $wm;
            });

        $this->masteryService->addWeaponMasteryXp($hero, ItemSubType::OneHandedSword, 15);

        // XP increases to 105 (max 1000)
        $this->assertSame(105, $wm->getXp());
        // Mastery tier increases to 2 because 105 >= 100
        $this->assertSame(2, $wm->getMasteryTier());
    }

    public function testProcessMatchParticipation(): void
    {
        $hero = new Hero();

        // Mock equipped sword
        $item = new Item();
        $item->setSubType(ItemSubType::OneHandedSword);
        $this->itemRepositoryMock->expects($this->once())
            ->method('findBy')
            ->willReturn([$item]);

        // Mock WeaponMastery record exists
        $wm = new WeaponMastery();
        $wm->setHero($hero);
        $wm->setStyle(ItemSubType::OneHandedSword);
        $wm->setAttunementProgress(20);
        $wm->setXp(10);
        $wm->setMasteryTier(1);
        $this->weaponMasteryRepositoryMock->expects($this->exactly(2))
            ->method('findOneBy')
            ->willReturn($wm);

        // Mock equipped Spell (Fire school)
        $spell = new Spell();
        $spell->setSchool(School::Fire);
        $hs = new HeroSpell();
        $hs->setSpell($spell);
        $hs->setIsEquipped(true);
        $hero->getHeroSpells()->add($hs);

        // Mock SchoolMastery
        $sm = new SchoolMastery();
        $sm->setHero($hero);
        $sm->setSchool(School::Fire);
        $sm->setXp(50);
        $sm->setMasteryTier(1);
        $this->schoolMasteryRepositoryMock->expects($this->once())
            ->method('findOneBy')
            ->willReturn($sm);

        $this->masteryService->processMatchParticipation($hero);

        // Attunement progress increases by 50 to 70
        $this->assertSame(70, $wm->getAttunementProgress());
        // Weapon XP increases by 15 to 25
        $this->assertSame(25, $wm->getXp());
        // Magic School XP increases by 15 to 65
        $this->assertSame(65, $sm->getXp());
    }

    public function testDailyDecayTickDecaysInactiveGearAndSchools(): void
    {
        $hero = new Hero();

        // 1. Setup active weapon mastery (OneHandedSword, stays intact)
        $wmActive = new WeaponMastery();
        $wmActive->setHero($hero);
        $wmActive->setStyle(ItemSubType::OneHandedSword);
        $wmActive->setXp(120);
        $wmActive->setMasteryTier(2);
        $wmActive->setAttunementProgress(100);
        $hero->getWeaponMasteries()->add($wmActive);

        // 2. Setup inactive weapon mastery (Shield, decays)
        $wmInactive = new WeaponMastery();
        $wmInactive->setHero($hero);
        $wmInactive->setStyle(ItemSubType::Shield);
        $wmInactive->setXp(105); // Just crossed T2 threshold (100)
        $wmInactive->setMasteryTier(2);
        $wmInactive->setAttunementProgress(50);
        $hero->getWeaponMasteries()->add($wmInactive);

        // 3. Setup active magic mastery (Fire, stays intact)
        $smActive = new SchoolMastery();
        $smActive->setHero($hero);
        $smActive->setSchool(School::Fire);
        $smActive->setXp(80);
        $smActive->setMasteryTier(1);
        $hero->getSchoolMasteries()->add($smActive);

        // 4. Setup inactive magic mastery (Water, decays)
        $smInactive = new SchoolMastery();
        $smInactive->setHero($hero);
        $smInactive->setSchool(School::Water);
        $smInactive->setXp(105); // Just crossed T2 threshold (100)
        $smInactive->setMasteryTier(2);
        $hero->getSchoolMasteries()->add($smInactive);

        // Mock equipped items: Only sword is equipped
        $item = new Item();
        $item->setSubType(ItemSubType::OneHandedSword);
        $this->itemRepositoryMock->expects($this->once())
            ->method('findBy')
            ->willReturn([$item]);

        // Mock equipped spells: Only Fire is equipped
        $spell = new Spell();
        $spell->setSchool(School::Fire);
        $hs = new HeroSpell();
        $hs->setSpell($spell);
        $hs->setIsEquipped(true);
        $hero->getHeroSpells()->add($hs);

        $this->masteryService->processDailyDecayTick($hero);

        // Active weapon mastery remains unchanged
        $this->assertSame(100, $wmActive->getAttunementProgress());
        $this->assertSame(120, $wmActive->getXp());
        $this->assertSame(2, $wmActive->getMasteryTier());

        // Inactive weapon mastery decays
        $this->assertSame(30, $wmInactive->getAttunementProgress()); // 50 - 20 = 30
        $this->assertSame(95, $wmInactive->getXp()); // 105 - 10 = 95
        $this->assertSame(1, $wmInactive->getMasteryTier()); // Levels down to T1 (95 < 100)

        // Active school mastery remains unchanged
        $this->assertSame(80, $smActive->getXp());
        $this->assertSame(1, $smActive->getMasteryTier());

        // Inactive school mastery decays
        $this->assertSame(95, $smInactive->getXp()); // 105 - 10 = 95
        $this->assertSame(1, $smInactive->getMasteryTier()); // Levels down to T1 (95 < 100)
    }
}
