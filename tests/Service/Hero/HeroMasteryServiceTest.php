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
            $this->createMock(\App\Service\Hero\HeroChronicleService::class),
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
        // Weapon XP: xpGain = round(BASE_MATCH_XP * 70 / 100) = round(30 * 0.7) = 21 → 10 + 21 = 31
        $this->assertSame(31, $wm->getXp());
        // Magic School XP increases by fixed 15 to 65
        $this->assertSame(65, $sm->getXp());
    }

    public function testDailyDecaySkipsAStyleExercisedThisWeekAndProtectsTier(): void
    {
        $hero = new Hero();
        $now = new \DateTimeImmutable('2026-10-18 00:00:00');

        $recent = new WeaponMastery();
        $recent->setHero($hero);
        $recent->setStyle(ItemSubType::OneHandedSword);
        $recent->setXp(120);
        $recent->setMasteryTier(2);
        $recent->setAttunementProgress(100);
        $recent->setLastExercisedAt($now->modify('-2 days'));
        $recent->setLastGainXp(30);
        $hero->getWeaponMasteries()->add($recent);

        $protected = new WeaponMastery();
        $protected->setHero($hero);
        $protected->setStyle(ItemSubType::Shield);
        $protected->setXp(105);
        $protected->setMasteryTier(2);
        $protected->setAttunementProgress(50);
        $protected->setLastExercisedAt($now->modify('-10 days'));
        $protected->setLastGainXp(30);
        $hero->getWeaponMasteries()->add($protected);

        $school = new SchoolMastery();
        $school->setHero($hero);
        $school->setSchool(School::Water);
        $school->setXp(105);
        $school->setMasteryTier(2);
        $school->setLastExercisedAt($now->modify('-10 days'));
        $school->setLastGainXp(15);
        $hero->getSchoolMasteries()->add($school);

        $this->masteryService->processDailyDecayTick($hero, $now);

        $this->assertSame(100, $recent->getAttunementProgress());
        $this->assertSame(120, $recent->getXp());

        // Attunement still drops. XP loss is capped at 25% of the last gain and cannot cross the tier floor.
        $this->assertSame(40, $protected->getAttunementProgress());
        $this->assertSame(100, $protected->getXp());
        $this->assertSame(2, $protected->getMasteryTier());

        // School decay cap is round(15 * 0.25) = 4, so 105 drops to 101 and stays above the tier floor.
        $this->assertSame(101, $school->getXp());
        $this->assertSame(2, $school->getMasteryTier());
    }

    public function testDailyDecayCanDropATierAfterTheProtectionWindow(): void
    {
        $hero = new Hero();
        $now = new \DateTimeImmutable('2026-10-18 00:00:00');

        $weapon = new WeaponMastery();
        $weapon->setHero($hero);
        $weapon->setStyle(ItemSubType::Shield);
        $weapon->setXp(105);
        $weapon->setMasteryTier(2);
        $weapon->setAttunementProgress(50);
        $weapon->setLastExercisedAt($now->modify('-15 days'));
        $weapon->setLastGainXp(0);
        $hero->getWeaponMasteries()->add($weapon);

        $school = new SchoolMastery();
        $school->setHero($hero);
        $school->setSchool(School::Water);
        $school->setXp(105);
        $school->setMasteryTier(2);
        $school->setLastExercisedAt($now->modify('-15 days'));
        $school->setLastGainXp(0);
        $hero->getSchoolMasteries()->add($school);

        $this->masteryService->processDailyDecayTick($hero, $now);

        $this->assertSame(40, $weapon->getAttunementProgress());
        // xpDecay = round(20 * (1 - 40/100)) = 12 → 105 - 12 = 93, tier 1
        $this->assertSame(93, $weapon->getXp());
        $this->assertSame(1, $weapon->getMasteryTier());

        $this->assertSame(95, $school->getXp());
        $this->assertSame(1, $school->getMasteryTier());
    }
}
