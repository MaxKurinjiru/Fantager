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
use App\Enum\ItemSubType;
use App\Enum\Race;
use App\Enum\School;
use App\Enum\SpellType;
use App\Exception\UserFacingException;
use App\Repository\Hero\HeroSpellRepository;
use App\Repository\Hero\SchoolMasteryRepository;
use App\Repository\Spell\SpellRepository;
use App\Service\Economy\EconomyService;
use App\Service\Hero\HeroMasteryService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class SpellService
{
    public const CATALOG_MIN_TIER = 1;

    public const CATALOG_MAX_TIER = 5;

    /** @var array<string, string> */
    private const NAME_KEYS = [
        'Jiskra' => 'spell.spark',
        'Vzplanutí' => 'spell.ignite',
        'Ohnivá koule' => 'spell.fireball',
        'Spalování' => 'spell.immolation',
        'Plamenomet' => 'spell.flamethrower',
        'Ohnivá bouře' => 'spell.firestorm',
        'Pekelný oheň' => 'spell.hellfire',
        'Spalující spirála' => 'spell.burning_spiral',
        'Ohnivý meteor' => 'spell.fire_meteor',
        'Fénixí hněv' => 'spell.phoenix_wrath',
        'Vodní proud' => 'spell.water_jet',
        'Laskavý dotek' => 'spell.gentle_touch',
        'Léčivá vlna' => 'spell.healing_wave',
        'Ledový osten' => 'spell.ice_spike',
        'Příliv' => 'spell.tide',
        'Ledová klec' => 'spell.ice_cage',
        'Živá voda' => 'spell.living_water',
        'Sněhová bouře' => 'spell.snowstorm',
        'Mořská milost' => 'spell.sea_grace',
        'Věčný mráz' => 'spell.eternal_frost',
        'Vánek' => 'spell.breeze',
        'Statický náboj' => 'spell.static_charge',
        'Úder blesku' => 'spell.lightning_strike',
        'Větrná zeď' => 'spell.wind_wall',
        'Vír' => 'spell.whirlwind',
        'Bouřkový šíp' => 'spell.storm_arrow',
        'Vzdušný štít' => 'spell.air_shield',
        'Hromová pěst' => 'spell.thunder_fist',
        'Oko cyklónu' => 'spell.cyclone_eye',
        'Nebeský blesk' => 'spell.sky_bolt',
        'Kamenná kůže' => 'spell.stone_skin',
        'Hod kamenem' => 'spell.stone_throw',
        'Zemětřesení' => 'spell.earthquake',
        'Štěrková smršť' => 'spell.gravel_storm',
        'Balvan' => 'spell.boulder',
        'Kamenná pevnost' => 'spell.stone_fortress',
        'Horská pěst' => 'spell.mountain_fist',
        'Rozštěpení země' => 'spell.earth_split',
        'Zřícení hor' => 'spell.mountain_collapse',
        'Titanova kůže' => 'spell.titan_skin',
        'Svaté světlo' => 'spell.holy_light',
        'Požehnání' => 'spell.blessing',
        'Očištění' => 'spell.cleanse',
        'Paprsek naděje' => 'spell.ray_of_hope',
        'Božská záře' => 'spell.divine_radiance',
        'Posvěcení' => 'spell.consecration',
        'Zázrak' => 'spell.miracle',
        'Světlo naděje' => 'spell.light_of_hope',
        'Nebeská milost' => 'spell.heavenly_grace',
        'Aura světla' => 'spell.aura_of_light',
        'Stínový šíp' => 'spell.shadow_bolt',
        'Slabost' => 'spell.weakness',
        'Vysátí života' => 'spell.life_drain',
        'Zkaženost' => 'spell.corruption',
        'Temný paprsek' => 'spell.dark_beam',
        'Prokletí' => 'spell.curse',
        'Krvavý obřad' => 'spell.blood_rite',
        'Mor' => 'spell.plague',
        'Vysátí duše' => 'spell.soul_drain',
        'Věčná tma' => 'spell.eternal_dark',
    ];

    public function __construct(
        private readonly SpellRepository $spellRepository,
        private readonly HeroSpellRepository $heroSpellRepository,
        private readonly SchoolMasteryRepository $masteryRepository,
        private readonly EntityManagerInterface $em,
        private readonly EconomyService $economyService,
        private readonly \App\Service\Hero\HeroChronicleService $heroChronicleService,
        private readonly \App\Service\TeamChronicle\TeamChronicleService $teamChronicleService,
        private readonly TranslatorInterface $translator,
        private readonly HeroMasteryService $heroMasteryService,
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

    /** @return list<int> */
    public static function catalogTiers(): array
    {
        return range(self::CATALOG_MIN_TIER, self::CATALOG_MAX_TIER);
    }

    public static function normalizeCatalogTier(?int $tier): ?int
    {
        if (null === $tier) {
            return null;
        }
        if ($tier < self::CATALOG_MIN_TIER || $tier > self::CATALOG_MAX_TIER) {
            return null;
        }

        return $tier;
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
     * Learn a spell. Deducts gold from the team.
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
        $currentTier = $mastery?->getMasteryTier() ?? 1;
        if ($currentTier < $spell->getRequiredMasteryTier()) {
            throw new UserFacingException('error.insufficient_school_mastery', ['%required%' => $spell->getRequiredMasteryTier(), '%current%' => $currentTier]);
        }

        $goldCost = $spell->getLearningCostGold();

        if ($goldCost > 0) {
            $this->economyService->deductGold(
                $team,
                $goldCost,
                FinancialRecordType::SpellLearningCost,
                FinancialRecordActor::Active,
                ['hero_id' => $hero->getId(), 'spell_id' => $spell->getId()]
            );
        }

        $heroSpell = new HeroSpell();
        $heroSpell->setHero($hero);
        $heroSpell->setSpell($spell);

        $this->em->persist($heroSpell);
        $this->heroChronicleService->recordSpellLearned($hero, $spell);
        $this->teamChronicleService->recordSpellLearned($team, $hero, $spell);

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

    public function displayName(Spell $spell): string
    {
        $key = $spell->getNameKey();
        if (null !== $key && '' !== $key) {
            return $this->translator->trans($key);
        }

        return $spell->getName();
    }

    public function heroCanCastOffensiveSpells(Hero $hero): bool
    {
        if (Race::Ent === $hero->getRace()) {
            return true;
        }

        foreach ($this->heroMasteryService->getEquippedSubTypes($hero) as $subType) {
            if (ItemSubType::Wand === $subType || ItemSubType::Staff === $subType) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, mixed> */
    public function serializeSpell(Spell $spell): array
    {
        return [
            'id' => $spell->getId(),
            'name' => $this->displayName($spell),
            'name_key' => $spell->getNameKey(),
            'school' => $spell->getSchool()->value,
            'tier' => $spell->getTier(),
            'type' => $spell->getType()->value,
            'effects' => $spell->getEffects(),
            'mana_cost' => $spell->getManaCost(),
            'cooldown' => $spell->getCooldown(),
            'required_mastery_tier' => $spell->getRequiredMasteryTier(),
            'learning_cost_gold' => $spell->getLearningCostGold(),
            'requires_magical_weapon' => $spell->requiresMagicalWeapon(),
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
     * Seeds the T1–T5 catalog (two spells per school per tier). Inserts missing
     * name+school rows and updates fields on existing ones.
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
            ],
            [
                'name' => 'Plamenomet',
                'school' => School::Fire,
                'tier' => 3,
                'type' => SpellType::Offensive,
                'effects' => ['damage' => 55],
                'mana_cost' => 40,
                'cooldown' => 2,
                'required_mastery_tier' => 3,
                'learning_cost_gold' => 800,
            ],
            [
                'name' => 'Ohnivá bouře',
                'school' => School::Fire,
                'tier' => 3,
                'type' => SpellType::Offensive,
                'effects' => ['damage' => 30, 'apply_effect' => 'burn', 'duration' => 3],
                'mana_cost' => 45,
                'cooldown' => 4,
                'required_mastery_tier' => 3,
                'learning_cost_gold' => 1000,
            ],
            [
                'name' => 'Pekelný oheň',
                'school' => School::Fire,
                'tier' => 4,
                'type' => SpellType::Offensive,
                'effects' => ['damage' => 80],
                'mana_cost' => 55,
                'cooldown' => 3,
                'required_mastery_tier' => 4,
                'learning_cost_gold' => 2000,
            ],
            [
                'name' => 'Spalující spirála',
                'school' => School::Fire,
                'tier' => 4,
                'type' => SpellType::Offensive,
                'effects' => ['damage' => 45, 'double_if_effect' => 'burn'],
                'mana_cost' => 60,
                'cooldown' => 4,
                'required_mastery_tier' => 4,
                'learning_cost_gold' => 2500,
            ],
            [
                'name' => 'Ohnivý meteor',
                'school' => School::Fire,
                'tier' => 5,
                'type' => SpellType::Offensive,
                'effects' => ['damage' => 120],
                'mana_cost' => 80,
                'cooldown' => 4,
                'required_mastery_tier' => 5,
                'learning_cost_gold' => 5000,
            ],
            [
                'name' => 'Fénixí hněv',
                'school' => School::Fire,
                'tier' => 5,
                'type' => SpellType::Offensive,
                'effects' => ['damage' => 70, 'apply_effect' => 'burn', 'duration' => 3],
                'mana_cost' => 90,
                'cooldown' => 5,
                'required_mastery_tier' => 5,
                'learning_cost_gold' => 6200,
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
            ],
            [
                'name' => 'Příliv',
                'school' => School::Water,
                'tier' => 3,
                'type' => SpellType::Defensive,
                'effects' => ['heal' => 70],
                'mana_cost' => 42,
                'cooldown' => 3,
                'required_mastery_tier' => 3,
                'learning_cost_gold' => 850,
            ],
            [
                'name' => 'Ledová klec',
                'school' => School::Water,
                'tier' => 3,
                'type' => SpellType::Utility,
                'effects' => ['damage' => 22, 'apply_effect' => 'freeze', 'duration' => 1],
                'mana_cost' => 40,
                'cooldown' => 4,
                'required_mastery_tier' => 3,
                'learning_cost_gold' => 950,
            ],
            [
                'name' => 'Živá voda',
                'school' => School::Water,
                'tier' => 4,
                'type' => SpellType::Defensive,
                'effects' => ['heal' => 100],
                'mana_cost' => 58,
                'cooldown' => 3,
                'required_mastery_tier' => 4,
                'learning_cost_gold' => 2100,
            ],
            [
                'name' => 'Sněhová bouře',
                'school' => School::Water,
                'tier' => 4,
                'type' => SpellType::Utility,
                'effects' => ['damage' => 35, 'apply_effect' => 'freeze', 'duration' => 1],
                'mana_cost' => 55,
                'cooldown' => 4,
                'required_mastery_tier' => 4,
                'learning_cost_gold' => 2400,
            ],
            [
                'name' => 'Mořská milost',
                'school' => School::Water,
                'tier' => 5,
                'type' => SpellType::Defensive,
                'effects' => ['heal' => 150],
                'mana_cost' => 80,
                'cooldown' => 4,
                'required_mastery_tier' => 5,
                'learning_cost_gold' => 5200,
            ],
            [
                'name' => 'Věčný mráz',
                'school' => School::Water,
                'tier' => 5,
                'type' => SpellType::Utility,
                'effects' => ['damage' => 50, 'apply_effect' => 'freeze', 'duration' => 1],
                'mana_cost' => 75,
                'cooldown' => 5,
                'required_mastery_tier' => 5,
                'learning_cost_gold' => 6000,
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
            ],
            [
                'name' => 'Vír',
                'school' => School::Air,
                'tier' => 3,
                'type' => SpellType::Defensive,
                'effects' => ['apply_effect' => 'haste', 'duration' => 2],
                'mana_cost' => 30,
                'cooldown' => 3,
                'required_mastery_tier' => 3,
                'learning_cost_gold' => 750,
            ],
            [
                'name' => 'Bouřkový šíp',
                'school' => School::Air,
                'tier' => 3,
                'type' => SpellType::Offensive,
                'effects' => ['damage' => 60, 'apply_effect' => 'shock', 'duration' => 2],
                'mana_cost' => 45,
                'cooldown' => 3,
                'required_mastery_tier' => 3,
                'learning_cost_gold' => 900,
            ],
            [
                'name' => 'Vzdušný štít',
                'school' => School::Air,
                'tier' => 4,
                'type' => SpellType::Defensive,
                'effects' => ['apply_effect' => 'evasion', 'duration' => 2],
                'mana_cost' => 42,
                'cooldown' => 4,
                'required_mastery_tier' => 4,
                'learning_cost_gold' => 1900,
            ],
            [
                'name' => 'Hromová pěst',
                'school' => School::Air,
                'tier' => 4,
                'type' => SpellType::Offensive,
                'effects' => ['damage' => 85, 'apply_effect' => 'shock', 'duration' => 2],
                'mana_cost' => 62,
                'cooldown' => 3,
                'required_mastery_tier' => 4,
                'learning_cost_gold' => 2200,
            ],
            [
                'name' => 'Oko cyklónu',
                'school' => School::Air,
                'tier' => 5,
                'type' => SpellType::Defensive,
                'effects' => ['apply_effect' => 'haste', 'duration' => 2],
                'mana_cost' => 55,
                'cooldown' => 4,
                'required_mastery_tier' => 5,
                'learning_cost_gold' => 4800,
            ],
            [
                'name' => 'Nebeský blesk',
                'school' => School::Air,
                'tier' => 5,
                'type' => SpellType::Offensive,
                'effects' => ['damage' => 125, 'apply_effect' => 'shock', 'duration' => 2],
                'mana_cost' => 85,
                'cooldown' => 4,
                'required_mastery_tier' => 5,
                'learning_cost_gold' => 5500,
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
            ],
            [
                'name' => 'Balvan',
                'school' => School::Earth,
                'tier' => 3,
                'type' => SpellType::Offensive,
                'effects' => ['damage' => 50],
                'mana_cost' => 38,
                'cooldown' => 2,
                'required_mastery_tier' => 3,
                'learning_cost_gold' => 800,
            ],
            [
                'name' => 'Kamenná pevnost',
                'school' => School::Earth,
                'tier' => 3,
                'type' => SpellType::Defensive,
                'effects' => ['apply_effect' => 'shield', 'duration' => 3],
                'mana_cost' => 35,
                'cooldown' => 3,
                'required_mastery_tier' => 3,
                'learning_cost_gold' => 850,
            ],
            [
                'name' => 'Horská pěst',
                'school' => School::Earth,
                'tier' => 4,
                'type' => SpellType::Offensive,
                'effects' => ['damage' => 70, 'reduce_speed_percent' => 20],
                'mana_cost' => 50,
                'cooldown' => 2,
                'required_mastery_tier' => 4,
                'learning_cost_gold' => 2000,
            ],
            [
                'name' => 'Rozštěpení země',
                'school' => School::Earth,
                'tier' => 4,
                'type' => SpellType::Utility,
                'effects' => ['damage' => 40, 'apply_effect' => 'petrify', 'duration' => 2],
                'mana_cost' => 58,
                'cooldown' => 4,
                'required_mastery_tier' => 4,
                'learning_cost_gold' => 2300,
            ],
            [
                'name' => 'Zřícení hor',
                'school' => School::Earth,
                'tier' => 5,
                'type' => SpellType::Offensive,
                'effects' => ['damage' => 100],
                'mana_cost' => 75,
                'cooldown' => 3,
                'required_mastery_tier' => 5,
                'learning_cost_gold' => 5000,
            ],
            [
                'name' => 'Titanova kůže',
                'school' => School::Earth,
                'tier' => 5,
                'type' => SpellType::Defensive,
                'effects' => ['apply_effect' => 'shield', 'duration' => 3],
                'mana_cost' => 60,
                'cooldown' => 4,
                'required_mastery_tier' => 5,
                'learning_cost_gold' => 5400,
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
            ],
            [
                'name' => 'Božská záře',
                'school' => School::Light,
                'tier' => 3,
                'type' => SpellType::Defensive,
                'effects' => ['heal' => 55, 'holy_damage_vs_undead' => 50],
                'mana_cost' => 40,
                'cooldown' => 2,
                'required_mastery_tier' => 3,
                'learning_cost_gold' => 850,
            ],
            [
                'name' => 'Posvěcení',
                'school' => School::Light,
                'tier' => 3,
                'type' => SpellType::Defensive,
                'effects' => ['apply_effect' => 'bless', 'duration' => 2],
                'mana_cost' => 38,
                'cooldown' => 3,
                'required_mastery_tier' => 3,
                'learning_cost_gold' => 900,
            ],
            [
                'name' => 'Zázrak',
                'school' => School::Light,
                'tier' => 4,
                'type' => SpellType::Defensive,
                'effects' => ['heal' => 80, 'cleanse_debuffs' => true],
                'mana_cost' => 58,
                'cooldown' => 3,
                'required_mastery_tier' => 4,
                'learning_cost_gold' => 2200,
            ],
            [
                'name' => 'Světlo naděje',
                'school' => School::Light,
                'tier' => 4,
                'type' => SpellType::Defensive,
                'effects' => ['heal' => 90, 'double_if_low_health' => true],
                'mana_cost' => 62,
                'cooldown' => 3,
                'required_mastery_tier' => 4,
                'learning_cost_gold' => 2500,
            ],
            [
                'name' => 'Nebeská milost',
                'school' => School::Light,
                'tier' => 5,
                'type' => SpellType::Defensive,
                'effects' => ['heal' => 130, 'holy_damage_vs_undead' => 80],
                'mana_cost' => 80,
                'cooldown' => 4,
                'required_mastery_tier' => 5,
                'learning_cost_gold' => 5400,
            ],
            [
                'name' => 'Aura světla',
                'school' => School::Light,
                'tier' => 5,
                'type' => SpellType::Defensive,
                'effects' => ['apply_effect' => 'bless', 'duration' => 2],
                'mana_cost' => 70,
                'cooldown' => 4,
                'required_mastery_tier' => 5,
                'learning_cost_gold' => 5000,
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
            ],
            [
                'name' => 'Temný paprsek',
                'school' => School::Dark,
                'tier' => 3,
                'type' => SpellType::Offensive,
                'effects' => ['damage' => 55],
                'mana_cost' => 40,
                'cooldown' => 2,
                'required_mastery_tier' => 3,
                'learning_cost_gold' => 800,
            ],
            [
                'name' => 'Prokletí',
                'school' => School::Dark,
                'tier' => 3,
                'type' => SpellType::Utility,
                'effects' => ['apply_effect' => 'curse', 'duration' => 3],
                'mana_cost' => 35,
                'cooldown' => 3,
                'required_mastery_tier' => 3,
                'learning_cost_gold' => 900,
            ],
            [
                'name' => 'Krvavý obřad',
                'school' => School::Dark,
                'tier' => 4,
                'type' => SpellType::Offensive,
                'effects' => ['damage' => 50, 'lifesteal_percent' => 50],
                'mana_cost' => 55,
                'cooldown' => 3,
                'required_mastery_tier' => 4,
                'learning_cost_gold' => 2300,
            ],
            [
                'name' => 'Mor',
                'school' => School::Dark,
                'tier' => 4,
                'type' => SpellType::Offensive,
                'effects' => ['damage' => 25, 'apply_effect' => 'poison', 'duration' => 3],
                'mana_cost' => 48,
                'cooldown' => 2,
                'required_mastery_tier' => 4,
                'learning_cost_gold' => 2000,
            ],
            [
                'name' => 'Vysátí duše',
                'school' => School::Dark,
                'tier' => 5,
                'type' => SpellType::Offensive,
                'effects' => ['damage' => 90, 'lifesteal_percent' => 50],
                'mana_cost' => 80,
                'cooldown' => 4,
                'required_mastery_tier' => 5,
                'learning_cost_gold' => 5600,
            ],
            [
                'name' => 'Věčná tma',
                'school' => School::Dark,
                'tier' => 5,
                'type' => SpellType::Utility,
                'effects' => ['apply_effect' => 'curse', 'duration' => 3],
                'mana_cost' => 65,
                'cooldown' => 4,
                'required_mastery_tier' => 5,
                'learning_cost_gold' => 5000,
            ],
        ];

        $insertedCount = 0;
        $skippedCount = 0;

        foreach ($spellsData as $data) {
            $spell = $this->spellRepository->findOneBy(['name' => $data['name'], 'school' => $data['school']]);
            $isNew = false;
            if (null === $spell) {
                $spell = new Spell();
                $spell->setName($data['name']);
                $spell->setSchool($data['school']);
                $isNew = true;
            }

            $spell->setTier($data['tier']);
            $spell->setType($data['type']);
            $spell->setEffects($data['effects']);
            $spell->setManaCost($data['mana_cost']);
            $spell->setCooldown($data['cooldown']);
            $spell->setRequiredMasteryTier($data['required_mastery_tier']);
            $spell->setLearningCostGold($data['learning_cost_gold']);
            $spell->setRequiresMagicalWeapon(SpellType::Offensive === $data['type']);
            $spell->setNameKey(self::NAME_KEYS[$data['name']]);

            if ($isNew) {
                $this->em->persist($spell);
                ++$insertedCount;
            } else {
                ++$skippedCount;
            }
        }

        // Flush all changes (both new persists and updates)
        $this->em->flush();

        return ['inserted' => $insertedCount, 'skipped' => $skippedCount];
    }
}
