<?php

declare(strict_types=1);

namespace App\Tests\Service\Spell;

use App\Entity\Hero\Hero;
use App\Entity\Spell\Spell;
use App\Entity\Team\Team;
use App\Enum\School;
use App\Repository\Hero\HeroSpellRepository;
use App\Repository\Hero\SchoolMasteryRepository;
use App\Repository\Spell\SpellRepository;
use App\Service\Spell\SpellService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class SpellServiceTest extends TestCase
{
    private SpellRepository $spellRepositoryMock;
    private HeroSpellRepository $heroSpellRepositoryMock;
    private SchoolMasteryRepository $masteryRepositoryMock;
    private EntityManagerInterface $entityManagerMock;
    private SpellService $spellService;

    protected function setUp(): void
    {
        $this->spellRepositoryMock = $this->createMock(SpellRepository::class);
        $this->heroSpellRepositoryMock = $this->createMock(HeroSpellRepository::class);
        $this->masteryRepositoryMock = $this->createMock(SchoolMasteryRepository::class);
        $this->entityManagerMock = $this->createMock(EntityManagerInterface::class);

        $this->spellService = new SpellService(
            $this->spellRepositoryMock,
            $this->heroSpellRepositoryMock,
            $this->masteryRepositoryMock,
            $this->entityManagerMock,
        );
    }

    public function testLearnRejectsDuplicateSpell(): void
    {
        $hero = new Hero();
        $team = new Team();
        $team->setGold(1000);
        $team->setEssenceCommon(100);

        $spell = new Spell();
        $spell->setSchool(School::Fire);
        $spell->setRequiredMasteryTier(0);
        $spell->setLearningCostGold(10);
        $spell->setLearningCostEssence(1);

        $this->heroSpellRepositoryMock
            ->method('findOneBy')
            ->willReturn(new \App\Entity\Hero\HeroSpell());

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Hero already knows this spell.');

        $this->spellService->learn($hero, $spell, $team);
    }

    public function testUnequipThrowsWhenNotEquipped(): void
    {
        $heroSpell = new \App\Entity\Hero\HeroSpell();
        $heroSpell->setIsEquipped(false);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Spell is not equipped.');

        $this->spellService->unequip($heroSpell);
    }
}
