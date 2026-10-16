<?php

declare(strict_types=1);

namespace App\Tests\Service\Spell;

use App\Entity\Hero\Hero;
use App\Entity\Spell\Spell;
use App\Exception\UserFacingException;
use App\Entity\Team\Team;
use App\Enum\School;
use App\Enum\SpellType;
use App\Repository\Hero\HeroSpellRepository;
use App\Repository\Hero\SchoolMasteryRepository;
use App\Repository\Spell\SpellRepository;
use App\Service\Economy\EconomyService;
use App\Service\Spell\SpellService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class SpellServiceTest extends TestCase
{
    /** @var \PHPUnit\Framework\MockObject\MockObject&SpellRepository */
    private $spellRepositoryMock;
    /** @var \PHPUnit\Framework\MockObject\MockObject&HeroSpellRepository */
    private $heroSpellRepositoryMock;
    /** @var \PHPUnit\Framework\MockObject\MockObject&SchoolMasteryRepository */
    private $masteryRepositoryMock;
    /** @var \PHPUnit\Framework\MockObject\MockObject&EntityManagerInterface */
    private $entityManagerMock;
    /** @var \PHPUnit\Framework\MockObject\MockObject&EconomyService */
    private $economyServiceMock;
    private SpellService $spellService;

    protected function setUp(): void
    {
        $this->spellRepositoryMock = $this->createMock(SpellRepository::class);
        $this->heroSpellRepositoryMock = $this->createMock(HeroSpellRepository::class);
        $this->masteryRepositoryMock = $this->createMock(SchoolMasteryRepository::class);
        $this->entityManagerMock = $this->createMock(EntityManagerInterface::class);
        $this->economyServiceMock = $this->createMock(EconomyService::class);

        $this->spellService = new SpellService(
            $this->spellRepositoryMock,
            $this->heroSpellRepositoryMock,
            $this->masteryRepositoryMock,
            $this->entityManagerMock,
            $this->economyServiceMock,
            $this->createMock(\App\Service\Hero\HeroChronicleService::class),
            $this->createMock(\App\Service\TeamChronicle\TeamChronicleService::class),
        );
    }


    public function testLearnRejectsDuplicateSpell(): void
    {
        $hero = new Hero();
        $team = new Team();
        $team->setGold(1000);

        $spell = new Spell();
        $spell->setSchool(School::Fire);
        $spell->setRequiredMasteryTier(0);
        $spell->setLearningCostGold(10);

        $this->heroSpellRepositoryMock
            ->method('findOneBy')
            ->willReturn(new \App\Entity\Hero\HeroSpell());

        $this->expectException(UserFacingException::class);
        $this->expectExceptionMessage('error.spell_already_known');

        $this->spellService->learn($hero, $spell, $team);
    }

    public function testUnequipThrowsWhenNotEquipped(): void
    {
        $heroSpell = new \App\Entity\Hero\HeroSpell();
        $heroSpell->setIsEquipped(false);

        $this->expectException(UserFacingException::class);
        $this->expectExceptionMessage('error.spell_not_equipped');

        $this->spellService->unequip($heroSpell);
    }

    public function testSeedDefaultSpellsCoversTiersOneThroughFive(): void
    {
        $this->spellRepositoryMock->method('findOneBy')->willReturn(null);

        /** @var list<Spell> $persisted */
        $persisted = [];
        $this->entityManagerMock
            ->method('persist')
            ->willReturnCallback(function (object $entity) use (&$persisted): void {
                $this->assertInstanceOf(Spell::class, $entity);
                $persisted[] = $entity;
            });

        $result = $this->spellService->seedDefaultSpells();

        $this->assertSame(48, $result['inserted']);
        $this->assertSame(0, $result['skipped']);
        $this->assertCount(48, $persisted);

        $allowedEffectKeys = [
            'damage',
            'apply_effect',
            'duration',
            'double_if_effect',
            'heal',
            'reduce_accuracy_percent',
            'reduce_speed_percent',
            'holy_damage_vs_undead',
            'cleanse_debuffs',
            'double_if_low_health',
            'lifesteal_percent',
        ];
        $bySchoolAndTier = [];
        $names = [];

        foreach ($persisted as $spell) {
            $tier = $spell->getTier();
            $this->assertContains($tier, SpellService::catalogTiers());
            $this->assertSame($tier, $spell->getRequiredMasteryTier());
            $this->assertGreaterThan(0, $spell->getManaCost());
            $this->assertGreaterThan(0, $spell->getCooldown());
            $this->assertGreaterThan(0, $spell->getLearningCostGold());
            $this->assertSame(
                SpellType::Offensive === $spell->getType(),
                $spell->requiresMagicalWeapon()
            );

            foreach (array_keys($spell->getEffects()) as $key) {
                $this->assertContains($key, $allowedEffectKeys);
            }

            $nameKey = $spell->getSchool()->value.'|'.$spell->getName();
            $this->assertArrayNotHasKey($nameKey, $names);
            $names[$nameKey] = true;

            $bySchoolAndTier[$spell->getSchool()->value][$tier][] = $spell;

            if (3 === $tier) {
                $this->assertGreaterThanOrEqual(700, $spell->getLearningCostGold());
                $this->assertLessThanOrEqual(1100, $spell->getLearningCostGold());
            }
            if (4 === $tier) {
                $this->assertGreaterThanOrEqual(1800, $spell->getLearningCostGold());
                $this->assertLessThanOrEqual(2700, $spell->getLearningCostGold());
            }
            if (5 === $tier) {
                $this->assertGreaterThanOrEqual(4500, $spell->getLearningCostGold());
                $this->assertLessThanOrEqual(7000, $spell->getLearningCostGold());
            }
        }

        foreach (School::cases() as $school) {
            $this->assertArrayHasKey($school->value, $bySchoolAndTier);
            foreach (SpellService::catalogTiers() as $tier) {
                $this->assertCount(2, $bySchoolAndTier[$school->value][$tier] ?? []);
            }
        }

        foreach ($bySchoolAndTier[School::Light->value] as $lightSpells) {
            foreach ($lightSpells as $spell) {
                $this->assertSame(SpellType::Defensive, $spell->getType());
            }
        }
        foreach ($bySchoolAndTier[School::Fire->value] as $fireSpells) {
            foreach ($fireSpells as $spell) {
                $this->assertSame(SpellType::Offensive, $spell->getType());
            }
        }
        foreach ($bySchoolAndTier[School::Dark->value] as $darkSpells) {
            foreach ($darkSpells as $spell) {
                $this->assertNotSame(SpellType::Defensive, $spell->getType());
            }
        }
    }

    public function testNormalizeCatalogTierRejectsOutOfRange(): void
    {
        $this->assertNull(SpellService::normalizeCatalogTier(null));
        $this->assertNull(SpellService::normalizeCatalogTier(0));
        $this->assertNull(SpellService::normalizeCatalogTier(6));
        $this->assertSame(1, SpellService::normalizeCatalogTier(1));
        $this->assertSame(5, SpellService::normalizeCatalogTier(5));
        $this->assertSame([1, 2, 3, 4, 5], SpellService::catalogTiers());
    }
}
