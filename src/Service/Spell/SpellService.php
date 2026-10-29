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
use App\Exception\UserFacingException;
use App\Repository\Hero\HeroSpellRepository;
use App\Repository\Hero\SchoolMasteryRepository;
use App\Repository\Spell\SpellRepository;
use App\Service\Combat\SpellPayload;
use App\Service\Economy\EconomyService;
use App\Service\Hero\HeroMasteryService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class SpellService
{
    public const CATALOG_MIN_TIER = 1;

    public const CATALOG_MAX_TIER = 5;

    /** Previous catalog names → current name (seed updates in place). */
    private const FORMER_NAMES = [
        'Němota' => ['Slabost'],
    ];

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
        'Němota' => 'spell.mute',
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
        $payload = SpellPayload::fromEffects($spell->getEffects());

        return [
            'id' => $spell->getId(),
            'name' => $this->displayName($spell),
            'name_key' => $spell->getNameKey(),
            'school' => $spell->getSchool()->value,
            'tier' => $spell->getTier(),
            'type' => $spell->getType()->value,
            'instant_hp' => $payload->instantHp,
            'apply_effect' => $payload->applyEffect?->value,
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
        $spellsData = SpellCatalog::rows();

        $insertedCount = 0;
        $skippedCount = 0;

        foreach ($spellsData as $data) {
            $spell = $this->findCatalogSpell($data['name'], $data['school']);
            $isNew = false;
            if (null === $spell) {
                $spell = new Spell();
                $spell->setName($data['name']);
                $spell->setSchool($data['school']);
                $isNew = true;
            } elseif ($spell->getName() !== $data['name']) {
                $spell->setName($data['name']);
            }

            $effects = SpellPayload::catalog($data['instant_hp'], $data['apply_effect']);
            $payload = SpellPayload::fromEffects($effects);
            $spell->setTier($data['tier']);
            $spell->setType($data['type']);
            $spell->setEffects($effects);
            $spell->setManaCost($data['mana_cost']);
            $spell->setCooldown($data['cooldown']);
            $spell->setRequiredMasteryTier($data['required_mastery_tier']);
            $spell->setLearningCostGold($data['learning_cost_gold']);
            $spell->setRequiresMagicalWeapon($payload->requiresMagicalWeapon());
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

    private function findCatalogSpell(string $name, School $school): ?Spell
    {
        $spell = $this->spellRepository->findOneBy(['name' => $name, 'school' => $school]);
        if (null !== $spell) {
            return $spell;
        }

        foreach (self::FORMER_NAMES[$name] ?? [] as $formerName) {
            $spell = $this->spellRepository->findOneBy(['name' => $formerName, 'school' => $school]);
            if (null !== $spell) {
                return $spell;
            }
        }

        return null;
    }
}
