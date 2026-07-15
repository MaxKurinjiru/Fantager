<?php

declare(strict_types=1);

namespace App\Service\Spell;

use App\Entity\Hero\Hero;
use App\Entity\Hero\HeroSpell;
use App\Entity\Hero\SchoolMastery;
use App\Entity\Spell\Spell;
use App\Entity\Team\Team;
use App\Enum\FinancialRecordActor;
use App\Enum\FinancialRecordType;
use App\Enum\School;
use App\Enum\SpellType;
use App\Exception\UserFacingException;
use App\Repository\Hero\HeroSpellRepository;
use App\Repository\Hero\SchoolMasteryRepository;
use App\Repository\Spell\SpellRepository;
use App\Service\Economy\EconomyService;
use Doctrine\ORM\EntityManagerInterface;

class SpellService
{
    public function __construct(
        private readonly SpellRepository $spellRepository,
        private readonly HeroSpellRepository $heroSpellRepository,
        private readonly SchoolMasteryRepository $masteryRepository,
        private readonly EntityManagerInterface $em,
        private readonly EconomyService $economyService,
    ) {
    }

    /**
     * List the global spell library, optionally filtered by school and/or tier.
     *
     * @return list<Spell>
     */
    public function listLibrary(?School $school = null, ?int $tier = null): array
    {
        $criteria = [];
        if (null !== $school) {
            $criteria['school'] = $school;
        }
        if (null !== $tier) {
            $criteria['tier'] = $tier;
        }

        return $this->spellRepository->findBy($criteria, ['school' => 'ASC', 'tier' => 'ASC']);
    }

    /**
     * List all spells a hero knows (both equipped and unequipped).
     *
     * @return list<HeroSpell>
     */
    public function listForHero(Hero $hero): array
    {
        return $this->heroSpellRepository->findBy(['hero' => $hero], ['id' => 'ASC']);
    }

    /**
     * Learn a spell. Deducts gold and essence from the team.
     *
     * @throws \DomainException on prerequisite failure or duplicate
     * @throws \DomainException on insufficient resources
     */
    public function learn(Hero $hero, Spell $spell, Team $team): HeroSpell
    {
        // Duplicate check
        $existing = $this->heroSpellRepository->findOneBy(['hero' => $hero, 'spell' => $spell]);
        if (null !== $existing) {
            throw new UserFacingException('error.spell_already_known');
        }

        // Mastery check
        $mastery = $this->getMasteryForSchool($hero, $spell->getSchool());
        $currentTier = $mastery?->getMasteryTier() ?? 0;
        if ($currentTier < $spell->getRequiredMasteryTier()) {
            throw new UserFacingException('error.insufficient_school_mastery', ['%required%' => $spell->getRequiredMasteryTier(), '%current%' => $currentTier]);
        }

        $goldCost = $spell->getLearningCostGold();
        $essenceCost = $spell->getLearningCostEssence();

        if ($goldCost > 0) {
            $this->economyService->deductGold(
                $team,
                $goldCost,
                FinancialRecordType::SpellLearningCost,
                FinancialRecordActor::Active,
                ['hero_id' => $hero->getId(), 'spell_id' => $spell->getId()]
            );
        }

        if ($essenceCost > 0) {
            $this->economyService->deductEssence(
                $team,
                'common',
                $essenceCost,
                FinancialRecordType::SpellLearningCost,
                FinancialRecordActor::Active,
                ['hero_id' => $hero->getId(), 'spell_id' => $spell->getId()]
            );
        }

        $heroSpell = new HeroSpell();
        $heroSpell->setHero($hero);
        $heroSpell->setSpell($spell);

        $this->em->persist($heroSpell);
        $this->em->flush();

        return $heroSpell;
    }

    /**
     * Equip a learned spell to a slot number (1 to hero.magicCapacity).
     *
     * @throws \DomainException
     */
    public function equip(HeroSpell $heroSpell, int $slot): void
    {
        $hero = $heroSpell->getHero();
        $capacity = $hero->getMagicCapacity();

        if ($slot < 1 || $slot > $capacity) {
            throw new UserFacingException('error.spell_slot_range', ['%capacity%' => $capacity]);
        }

        // Unequip any spell already in that slot
        $existing = $this->heroSpellRepository->findOneBy([
            'hero' => $hero,
            'isEquipped' => true,
            'slotNumber' => $slot,
        ]);
        if (null !== $existing && $existing->getId() !== $heroSpell->getId()) {
            $existing->setIsEquipped(false);
            $existing->setSlotNumber(null);
        }

        $heroSpell->setIsEquipped(true);
        $heroSpell->setSlotNumber($slot);

        $this->em->flush();
    }

    /**
     * Unequip a spell from its current slot.
     *
     * @throws \DomainException
     */
    public function unequip(HeroSpell $heroSpell): void
    {
        if (!$heroSpell->isEquipped()) {
            throw new UserFacingException('error.spell_not_equipped');
        }

        $heroSpell->setIsEquipped(false);
        $heroSpell->setSlotNumber(null);

        $this->em->flush();
    }

    /**
     * Get or create a SchoolMastery record for a hero+school.
     * Used by the Training tick processor when magic training completes.
     */
    public function getOrCreateMastery(Hero $hero, School $school): SchoolMastery
    {
        $mastery = $this->getMasteryForSchool($hero, $school);
        if (null !== $mastery) {
            return $mastery;
        }

        $mastery = new SchoolMastery();
        $mastery->setHero($hero);
        $mastery->setSchool($school);

        $this->em->persist($mastery);
        $this->em->flush();

        return $mastery;
    }

    private function getMasteryForSchool(Hero $hero, School $school): ?SchoolMastery
    {
        return $this->masteryRepository->findOneBy(['hero' => $hero, 'school' => $school]);
    }

    /** @return array<string, mixed> */
    public function serializeSpell(Spell $spell): array
    {
        return [
            'id' => $spell->getId(),
            'name' => $spell->getName(),
            'school' => $spell->getSchool()->value,
            'tier' => $spell->getTier(),
            'type' => $spell->getType()->value,
            'effects' => $spell->getEffects(),
            'mana_cost' => $spell->getManaCost(),
            'cooldown' => $spell->getCooldown(),
            'required_mastery_tier' => $spell->getRequiredMasteryTier(),
            'learning_cost_gold' => $spell->getLearningCostGold(),
            'learning_cost_essence' => $spell->getLearningCostEssence(),
        ];
    }

    /** @return array<string, mixed> */
    public function serializeHeroSpell(HeroSpell $hs): array
    {
        return [
            'id' => $hs->getId(),
            'spell' => $this->serializeSpell($hs->getSpell()),
            'is_equipped' => $hs->isEquipped(),
            'slot_number' => $hs->getSlotNumber(),
        ];
    }

    /**
     * Seeds the database with default spells if none exist.
     *
     * @return array{inserted: int, skipped: int}
     */
    public function seedDefaultSpells(): array
    {
        $spellsData = [
            // --- FIRE ---
            [
                'name' => 'Jiskra',
                'school' => School::Fire,
                'tier' => 1,
                'type' => SpellType::Offensive,
                'effects' => ['damage' => 15],
                'mana_cost' => 10,
                'cooldown' => 1,
                'required_mastery_tier' => 1,
                'learning_cost_gold' => 100,
                'learning_cost_essence' => 0,
            ],
            [
                'name' => 'Vzplanutí',
                'school' => School::Fire,
                'tier' => 1,
                'type' => SpellType::Offensive,
                'effects' => ['damage' => 8, 'apply_effect' => 'burn', 'duration' => 3],
                'mana_cost' => 15,
                'cooldown' => 3,
                'required_mastery_tier' => 1,
                'learning_cost_gold' => 150,
                'learning_cost_essence' => 0,
            ],
            [
                'name' => 'Ohnivá koule',
                'school' => School::Fire,
                'tier' => 2,
                'type' => SpellType::Offensive,
                'effects' => ['damage' => 35],
                'mana_cost' => 25,
                'cooldown' => 2,
                'required_mastery_tier' => 2,
                'learning_cost_gold' => 300,
                'learning_cost_essence' => 0,
            ],
            [
                'name' => 'Spalování',
                'school' => School::Fire,
                'tier' => 2,
                'type' => SpellType::Offensive,
                'effects' => ['damage' => 20, 'double_if_effect' => 'burn'],
                'mana_cost' => 30,
                'cooldown' => 4,
                'required_mastery_tier' => 2,
                'learning_cost_gold' => 400,
                'learning_cost_essence' => 0,
            ],

            // --- WATER ---
            [
                'name' => 'Vodní proud',
                'school' => School::Water,
                'tier' => 1,
                'type' => SpellType::Utility,
                'effects' => ['damage' => 10, 'reduce_accuracy_percent' => 15],
                'mana_cost' => 12,
                'cooldown' => 2,
                'required_mastery_tier' => 1,
                'learning_cost_gold' => 100,
                'learning_cost_essence' => 0,
            ],
            [
                'name' => 'Laskavý dotek',
                'school' => School::Water,
                'tier' => 1,
                'type' => SpellType::Defensive,
                'effects' => ['heal' => 20],
                'mana_cost' => 15,
                'cooldown' => 2,
                'required_mastery_tier' => 1,
                'learning_cost_gold' => 150,
                'learning_cost_essence' => 0,
            ],
            [
                'name' => 'Léčivá vlna',
                'school' => School::Water,
                'tier' => 2,
                'type' => SpellType::Defensive,
                'effects' => ['heal' => 45],
                'mana_cost' => 30,
                'cooldown' => 3,
                'required_mastery_tier' => 2,
                'learning_cost_gold' => 350,
                'learning_cost_essence' => 0,
            ],
            [
                'name' => 'Ledový osten',
                'school' => School::Water,
                'tier' => 2,
                'type' => SpellType::Utility,
                'effects' => ['damage' => 12, 'apply_effect' => 'freeze', 'duration' => 1],
                'mana_cost' => 28,
                'cooldown' => 4,
                'required_mastery_tier' => 2,
                'learning_cost_gold' => 450,
                'learning_cost_essence' => 0,
            ],

            // --- AIR ---
            [
                'name' => 'Vánek',
                'school' => School::Air,
                'tier' => 1,
                'type' => SpellType::Defensive,
                'effects' => ['apply_effect' => 'haste', 'duration' => 2],
                'mana_cost' => 10,
                'cooldown' => 3,
                'required_mastery_tier' => 1,
                'learning_cost_gold' => 100,
                'learning_cost_essence' => 0,
            ],
            [
                'name' => 'Statický náboj',
                'school' => School::Air,
                'tier' => 1,
                'type' => SpellType::Offensive,
                'effects' => ['damage' => 18],
                'mana_cost' => 14,
                'cooldown' => 1,
                'required_mastery_tier' => 1,
                'learning_cost_gold' => 150,
                'learning_cost_essence' => 0,
            ],
            [
                'name' => 'Úder blesku',
                'school' => School::Air,
                'tier' => 2,
                'type' => SpellType::Offensive,
                'effects' => ['damage' => 40, 'apply_effect' => 'shock', 'duration' => 2],
                'mana_cost' => 32,
                'cooldown' => 3,
                'required_mastery_tier' => 2,
                'learning_cost_gold' => 400,
                'learning_cost_essence' => 0,
            ],
            [
                'name' => 'Větrná zeď',
                'school' => School::Air,
                'tier' => 2,
                'type' => SpellType::Defensive,
                'effects' => ['apply_effect' => 'evasion', 'duration' => 2],
                'mana_cost' => 22,
                'cooldown' => 4,
                'required_mastery_tier' => 2,
                'learning_cost_gold' => 300,
                'learning_cost_essence' => 0,
            ],

            // --- EARTH ---
            [
                'name' => 'Kamenná kůže',
                'school' => School::Earth,
                'tier' => 1,
                'type' => SpellType::Defensive,
                'effects' => ['apply_effect' => 'shield', 'duration' => 3],
                'mana_cost' => 12,
                'cooldown' => 3,
                'required_mastery_tier' => 1,
                'learning_cost_gold' => 120,
                'learning_cost_essence' => 0,
            ],
            [
                'name' => 'Hod kamenem',
                'school' => School::Earth,
                'tier' => 1,
                'type' => SpellType::Offensive,
                'effects' => ['damage' => 14],
                'mana_cost' => 10,
                'cooldown' => 1,
                'required_mastery_tier' => 1,
                'learning_cost_gold' => 100,
                'learning_cost_essence' => 0,
            ],
            [
                'name' => 'Zemětřesení',
                'school' => School::Earth,
                'tier' => 2,
                'type' => SpellType::Utility,
                'effects' => ['damage' => 22, 'apply_effect' => 'petrify', 'duration' => 2],
                'mana_cost' => 30,
                'cooldown' => 4,
                'required_mastery_tier' => 2,
                'learning_cost_gold' => 380,
                'learning_cost_essence' => 0,
            ],
            [
                'name' => 'Štěrková smršť',
                'school' => School::Earth,
                'tier' => 2,
                'type' => SpellType::Offensive,
                'effects' => ['damage' => 25, 'reduce_speed_percent' => 20],
                'mana_cost' => 24,
                'cooldown' => 2,
                'required_mastery_tier' => 2,
                'learning_cost_gold' => 320,
                'learning_cost_essence' => 0,
            ],

            // --- LIGHT ---
            [
                'name' => 'Svaté světlo',
                'school' => School::Light,
                'tier' => 1,
                'type' => SpellType::Defensive,
                'effects' => ['heal' => 20, 'holy_damage_vs_undead' => 30],
                'mana_cost' => 12,
                'cooldown' => 2,
                'required_mastery_tier' => 1,
                'learning_cost_gold' => 110,
                'learning_cost_essence' => 0,
            ],
            [
                'name' => 'Požehnání',
                'school' => School::Light,
                'tier' => 1,
                'type' => SpellType::Defensive,
                'effects' => ['apply_effect' => 'bless', 'duration' => 2],
                'mana_cost' => 15,
                'cooldown' => 3,
                'required_mastery_tier' => 1,
                'learning_cost_gold' => 150,
                'learning_cost_essence' => 0,
            ],
            [
                'name' => 'Očištění',
                'school' => School::Light,
                'tier' => 2,
                'type' => SpellType::Defensive,
                'effects' => ['heal' => 30, 'cleanse_debuffs' => true],
                'mana_cost' => 25,
                'cooldown' => 3,
                'required_mastery_tier' => 2,
                'learning_cost_gold' => 360,
                'learning_cost_essence' => 0,
            ],
            [
                'name' => 'Paprsek naděje',
                'school' => School::Light,
                'tier' => 2,
                'type' => SpellType::Defensive,
                'effects' => ['heal' => 35, 'double_if_low_health' => true],
                'mana_cost' => 28,
                'cooldown' => 3,
                'required_mastery_tier' => 2,
                'learning_cost_gold' => 400,
                'learning_cost_essence' => 0,
            ],

            // --- DARK ---
            [
                'name' => 'Stínový šíp',
                'school' => School::Dark,
                'tier' => 1,
                'type' => SpellType::Offensive,
                'effects' => ['damage' => 16],
                'mana_cost' => 10,
                'cooldown' => 1,
                'required_mastery_tier' => 1,
                'learning_cost_gold' => 100,
                'learning_cost_essence' => 0,
            ],
            [
                'name' => 'Slabost',
                'school' => School::Dark,
                'tier' => 1,
                'type' => SpellType::Utility,
                'effects' => ['apply_effect' => 'curse', 'duration' => 3],
                'mana_cost' => 14,
                'cooldown' => 3,
                'required_mastery_tier' => 1,
                'learning_cost_gold' => 150,
                'learning_cost_essence' => 0,
            ],
            [
                'name' => 'Vysátí života',
                'school' => School::Dark,
                'tier' => 2,
                'type' => SpellType::Offensive,
                'effects' => ['damage' => 26, 'lifesteal_percent' => 50],
                'mana_cost' => 26,
                'cooldown' => 3,
                'required_mastery_tier' => 2,
                'learning_cost_gold' => 380,
                'learning_cost_essence' => 0,
            ],
            [
                'name' => 'Zkaženost',
                'school' => School::Dark,
                'tier' => 2,
                'type' => SpellType::Offensive,
                'effects' => ['damage' => 12, 'apply_effect' => 'poison', 'duration' => 3],
                'mana_cost' => 22,
                'cooldown' => 2,
                'required_mastery_tier' => 2,
                'learning_cost_gold' => 320,
                'learning_cost_essence' => 0,
            ],
        ];

        $insertedCount = 0;
        $skippedCount = 0;

        foreach ($spellsData as $data) {
            $existing = $this->spellRepository->findOneBy(['name' => $data['name'], 'school' => $data['school']]);
            if (null !== $existing) {
                ++$skippedCount;
                continue;
            }

            $spell = new Spell();
            $spell->setName($data['name']);
            $spell->setSchool($data['school']);
            $spell->setTier($data['tier']);
            $spell->setType($data['type']);
            $spell->setEffects($data['effects']);
            $spell->setManaCost($data['mana_cost']);
            $spell->setCooldown($data['cooldown']);
            $spell->setRequiredMasteryTier($data['required_mastery_tier']);
            $spell->setLearningCostGold($data['learning_cost_gold']);
            $spell->setLearningCostEssence($data['learning_cost_essence']);

            $this->em->persist($spell);
            ++$insertedCount;
        }

        if ($insertedCount > 0) {
            $this->em->flush();
        }

        return ['inserted' => $insertedCount, 'skipped' => $skippedCount];
    }
}
