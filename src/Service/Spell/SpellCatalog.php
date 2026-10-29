<?php

declare(strict_types=1);

namespace App\Service\Spell;

use App\Enum\School;
use App\Enum\SpellType;
use App\Enum\StatusEffect;
use App\Service\Combat\SpellPayload;

/**
 * Seeded T1–T5 catalog rows. Combat identity is intent + payloads.
 *
 * @phpstan-type CatalogRow array{
 *     name: string,
 *     school: School,
 *     tier: int,
 *     type: SpellType,
 *     mana_cost: int,
 *     cooldown: int,
 *     required_mastery_tier: int,
 *     learning_cost_gold: int,
 *     instant_hp: string|null,
 *     apply_effect: StatusEffect|null
 * }
 */
final class SpellCatalog
{
    /**
     * @return list<CatalogRow>
     */
    public static function rows(): array
    {
        $d = SpellPayload::INSTANT_DAMAGE;
        $h = SpellPayload::INSTANT_HEAL;

        return [
            self::row('Jiskra', School::Fire, 1, SpellType::Harm, 10, 1, 100, $d, null),
            self::row('Vzplanutí', School::Fire, 1, SpellType::Harm, 15, 3, 150, $d, StatusEffect::Burn),
            self::row('Ohnivá koule', School::Fire, 2, SpellType::Harm, 25, 2, 300, $d, null),
            self::row('Spalování', School::Fire, 2, SpellType::Harm, 30, 4, 400, null, StatusEffect::Burn),
            self::row('Plamenomet', School::Fire, 3, SpellType::Harm, 40, 2, 800, $d, StatusEffect::Burn),
            self::row('Ohnivá bouře', School::Fire, 3, SpellType::Harm, 45, 4, 1000, $d, null),
            self::row('Pekelný oheň', School::Fire, 4, SpellType::Harm, 55, 3, 2000, $d, StatusEffect::Burn),
            self::row('Spalující spirála', School::Fire, 4, SpellType::Enhance, 60, 4, 2500, null, StatusEffect::Fury),
            self::row('Ohnivý meteor', School::Fire, 5, SpellType::Harm, 80, 4, 5000, $d, null),
            self::row('Fénixí hněv', School::Fire, 5, SpellType::Harm, 90, 5, 6200, $d, StatusEffect::Burn),

            self::row('Vodní proud', School::Water, 1, SpellType::Harm, 12, 2, 100, $d, null),
            self::row('Laskavý dotek', School::Water, 1, SpellType::Restore, 15, 2, 150, $h, null),
            self::row('Léčivá vlna', School::Water, 2, SpellType::Restore, 30, 3, 350, $h, StatusEffect::Regeneration),
            self::row('Ledový osten', School::Water, 2, SpellType::Hinder, 28, 4, 450, null, StatusEffect::Freeze),
            self::row('Příliv', School::Water, 3, SpellType::Restore, 42, 3, 850, $h, null),
            self::row('Ledová klec', School::Water, 3, SpellType::Hinder, 40, 4, 950, null, StatusEffect::Freeze),
            self::row('Živá voda', School::Water, 4, SpellType::Restore, 58, 3, 2100, null, StatusEffect::Regeneration),
            self::row('Sněhová bouře', School::Water, 4, SpellType::Harm, 55, 4, 2400, $d, StatusEffect::Freeze),
            self::row('Mořská milost', School::Water, 5, SpellType::Restore, 80, 4, 5200, $h, StatusEffect::Regeneration),
            self::row('Věčný mráz', School::Water, 5, SpellType::Hinder, 75, 5, 6000, null, StatusEffect::Freeze),

            self::row('Vánek', School::Air, 1, SpellType::Enhance, 10, 3, 100, null, StatusEffect::Haste),
            self::row('Statický náboj', School::Air, 1, SpellType::Harm, 14, 1, 150, $d, StatusEffect::Shock),
            self::row('Úder blesku', School::Air, 2, SpellType::Harm, 32, 3, 400, $d, StatusEffect::Silence),
            self::row('Větrná zeď', School::Air, 2, SpellType::Enhance, 22, 4, 300, null, StatusEffect::Haste),
            self::row('Vír', School::Air, 3, SpellType::Hinder, 30, 3, 750, null, StatusEffect::Stun),
            self::row('Bouřkový šíp', School::Air, 3, SpellType::Harm, 45, 3, 900, $d, StatusEffect::Shock),
            self::row('Vzdušný štít', School::Air, 4, SpellType::Enhance, 42, 4, 1900, null, StatusEffect::Haste),
            self::row('Hromová pěst', School::Air, 4, SpellType::Harm, 62, 3, 2200, $d, StatusEffect::Shock),
            self::row('Oko cyklónu', School::Air, 5, SpellType::Enhance, 55, 4, 4800, null, StatusEffect::Haste),
            self::row('Nebeský blesk', School::Air, 5, SpellType::Harm, 85, 4, 5500, $d, null),

            self::row('Kamenná kůže', School::Earth, 1, SpellType::Enhance, 12, 3, 120, null, StatusEffect::Shield),
            self::row('Hod kamenem', School::Earth, 1, SpellType::Harm, 10, 1, 100, $d, null),
            self::row('Zemětřesení', School::Earth, 2, SpellType::Hinder, 30, 4, 380, null, StatusEffect::Petrify),
            self::row('Štěrková smršť', School::Earth, 2, SpellType::Harm, 24, 2, 320, $d, StatusEffect::Petrify),
            self::row('Balvan', School::Earth, 3, SpellType::Harm, 38, 2, 800, $d, null),
            self::row('Kamenná pevnost', School::Earth, 3, SpellType::Enhance, 35, 3, 850, null, StatusEffect::Shield),
            self::row('Horská pěst', School::Earth, 4, SpellType::Harm, 50, 2, 2000, $d, StatusEffect::Petrify),
            self::row('Rozštěpení země', School::Earth, 4, SpellType::Hinder, 58, 4, 2300, null, StatusEffect::Petrify),
            self::row('Zřícení hor', School::Earth, 5, SpellType::Harm, 75, 3, 5000, $d, null),
            self::row('Titanova kůže', School::Earth, 5, SpellType::Enhance, 60, 4, 5400, null, StatusEffect::Shield),

            self::row('Svaté světlo', School::Light, 1, SpellType::Restore, 12, 2, 110, $h, null),
            self::row('Požehnání', School::Light, 1, SpellType::Enhance, 15, 3, 150, null, StatusEffect::Bless),
            self::row('Očištění', School::Light, 2, SpellType::Restore, 25, 3, 360, $h, null),
            self::row('Paprsek naděje', School::Light, 2, SpellType::Restore, 28, 3, 400, $h, StatusEffect::Bless),
            self::row('Božská záře', School::Light, 3, SpellType::Hinder, 40, 2, 850, null, StatusEffect::Blind),
            self::row('Posvěcení', School::Light, 3, SpellType::Restore, 38, 3, 900, $h, null),
            self::row('Zázrak', School::Light, 4, SpellType::Restore, 58, 3, 2200, $h, StatusEffect::Bless),
            self::row('Světlo naděje', School::Light, 4, SpellType::Enhance, 62, 3, 2500, null, StatusEffect::Bless),
            self::row('Nebeská milost', School::Light, 5, SpellType::Restore, 80, 4, 5400, $h, null),
            self::row('Aura světla', School::Light, 5, SpellType::Enhance, 70, 4, 5000, null, StatusEffect::Bless),

            self::row('Stínový šíp', School::Dark, 1, SpellType::Harm, 10, 1, 100, $d, null),
            self::row('Němota', School::Dark, 1, SpellType::Hinder, 14, 3, 150, null, StatusEffect::Silence),
            self::row('Vysátí života', School::Dark, 2, SpellType::Harm, 26, 3, 380, $d, StatusEffect::Curse),
            self::row('Zkaženost', School::Dark, 2, SpellType::Harm, 22, 2, 320, $d, null),
            self::row('Temný paprsek', School::Dark, 3, SpellType::Harm, 40, 2, 800, $d, StatusEffect::Curse),
            self::row('Prokletí', School::Dark, 3, SpellType::Hinder, 35, 3, 900, null, StatusEffect::Curse),
            self::row('Krvavý obřad', School::Dark, 4, SpellType::Harm, 55, 3, 2300, $d, StatusEffect::Curse),
            self::row('Mor', School::Dark, 4, SpellType::Hinder, 48, 2, 2000, null, StatusEffect::Taunt),
            self::row('Vysátí duše', School::Dark, 5, SpellType::Harm, 80, 4, 5600, $d, null),
            self::row('Věčná tma', School::Dark, 5, SpellType::Enhance, 65, 4, 5000, null, StatusEffect::ShadowCloak),
        ];
    }

    /**
     * @return CatalogRow
     */
    private static function row(
        string $name,
        School $school,
        int $tier,
        SpellType $type,
        int $manaCost,
        int $cooldown,
        int $gold,
        ?string $instantHp,
        ?StatusEffect $status,
    ): array {
        return [
            'name' => $name,
            'school' => $school,
            'tier' => $tier,
            'type' => $type,
            'mana_cost' => $manaCost,
            'cooldown' => $cooldown,
            'required_mastery_tier' => $tier,
            'learning_cost_gold' => $gold,
            'instant_hp' => $instantHp,
            'apply_effect' => $status,
        ];
    }
}
