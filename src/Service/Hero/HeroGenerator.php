<?php

declare(strict_types=1);

namespace App\Service\Hero;

use App\Entity\Hero\Hero;
use App\Entity\Team\Team;
use App\Enum\Race;
use App\Service\Config\RaceConfig;

/**
 * Creates level-1 heroes with race-based stats (summoning, kingdom init, etc.).
 */
class HeroGenerator
{
    /**
     * Fallback base stat value when no Summoning Chamber bonuses are provided.
     * Results in a stat range of 1–3 per attribute (before race bonuses).
     */
    private const BASE_STAT_VALUE = 1;

    /** Fallback random ceiling when no Summoning Chamber bonuses are provided. */
    private const STAT_RANDOM_MAX = 2;

    /** @var array<string, list<string>> */
    private const NAMES_BY_RACE = [
        'human' => [
            'Aldric', 'Mira', 'Gareth', 'Lyra', 'Edric', 'Selene', 'Bran', 'Thea',
            'Katherine', 'Alistair', 'Beatrice', 'Cedric', 'Diane', 'Elena', 'Fiona', 'Gerald',
            'Helena', 'Ian', 'Julian', 'Kaelen', 'Leona', 'Marcus', 'Nadia', 'Osmund',
            'Rowena', 'Tristan', 'Valeria', 'Walter', 'Yvonne', 'Zachary',
        ],
        'elf' => [
            'Aelindra', 'Caelum', 'Sylvara', 'Eryndal', 'Thessaly', 'Faeryn', 'Liriel', 'Aelar',
            'Faelar', 'Elentari', 'Galadhel', 'Ithilrin', 'Yvanna', 'Valandil', 'Nimue', 'Haldir',
            'Eredin', 'Celeborn', 'Morwen', 'Arwen', 'Thranduil', 'Tauriel', 'Legolas', 'Eldrin',
            'Keyleth', 'Varis', 'Aerith', 'Illyria', 'Silaqui', 'Xiloscient',
        ],
        'dwarf' => [
            'Thorin', 'Brunhilda', 'Dolgrin', 'Marta', 'Gundrik', 'Helga', 'Barrek', 'Sigrun',
            'Gimli', 'Balin', 'Dwalin', 'Fili', 'Kili', 'Bofur', 'Bifur', 'Bombur',
            'Oin', 'Gloin', 'Dain', 'Thrain', 'Borin', 'Frerin', 'Dis', 'Nori',
            'Dori', 'Ori', 'Thror', 'Gorm', 'Hela', 'Runa',
        ],
        'orc' => [
            "Gruk'ar", "Mog'ra", 'Durz', 'Varg', "Krak'ok", 'Ulna', 'Torg', 'Rasha',
            'Garrosh', 'Thrall', 'Grommash', 'Durotan', 'Orgrim', 'Gul\'dan', 'Blackhand', 'Drek\'Thar',
            'Rehgar', 'Broxigar', 'Saurfang', 'Nazgrel', 'Zaela', 'Geyah', 'Aggra', 'Eitrigg',
            'Maim', 'Rend', 'Kargath', 'Fenris', 'Kilrogg', 'Jorin',
        ],
        'undead' => [
            'Morvius', 'Shroud', 'Vexa', 'Krill', 'Nethara', 'Gravock', 'Lyche', 'Serne',
            'Sylvanas', 'Kel\'Thuzad', 'Anub\'arak', 'Arthas', 'Teron', 'Nathanas', 'Putress', 'Alonsus',
            'Calia', 'Lilian', 'Voss', 'Faranell', 'Gunther', 'Meryl', 'Helcular', 'Mor\'Ladim',
            'Abercrombie', 'Eliza', 'Morra', 'Cynthia', 'Damien', 'Silas',
        ],
        'giant' => [
            'Grognak', 'Hrothgar', 'Thokk', 'Ugga', 'Drek', 'Borak', 'Grog', 'Urm',
            'Ymir', 'Surtr', 'Thrym', 'Gymir', 'Hrungnir', 'Geirrod', 'Baugi', 'Utgarda',
            'Skrymir', 'Kari', 'Logi', 'Fornjot', 'Angrboda', 'Gridr', 'Gerd', 'Rindr',
            'Bestla', 'Bolthorn', 'Sutting', 'Fjalar', 'Galar', 'Vafthrudnir',
        ],
        'ent' => [
            'Fangorn', 'Bregalad', 'Finglas', 'Fladrif', 'Wandlimb', 'Beechbone', 'Leaflock', 'Rootwalker',
            'Barkmoss', 'Stemvine', 'Deeproot', 'Greenmantle', 'Thornhold', 'Mossback', 'Ironwood', 'Ashwood',
            'Elmwood', 'Willowbark', 'Oakbrow', 'Cedarbark', 'Silverleaf', 'Alderbranch', 'Willowshade', 'Elmheart',
            'Cedarstride', 'Rowansong', 'Yewkeeper', 'Birchwhisper', 'Maplegrain', 'Hawthorngast',
        ],
        'genie' => [
            'Jinn', 'Siroc', 'Baku', 'Asra', 'Rihan', 'Kamran', 'Shayda', 'Farid',
            'Zephyr', 'Caliph', 'Amir', 'Leila', 'Soraya', 'Jasmine', 'Aladdin', 'Scheherazade',
            'Mustafa', 'Ali', 'Fatima', 'Omar', 'Khalid', 'Samir', 'Tariq', 'Zain',
            'Yusuf', 'Zayd', 'Amina', 'Salma', 'Nadia', 'Hasan',
        ],
    ];

    /** @var array<string, list<string>> */
    private const SURNAMES_BY_RACE = [
        'human' => [
            'Ashford', 'Ironwood', 'Caldwell', 'Mercer', 'Dunmore', 'Fenn', 'Harwick', 'Stonebridge',
            'Blackwood', 'Kingscote', 'Redcliffe', 'Winterbourne', 'Sterling', 'Thorne', 'Ravenscroft', 'Vance',
            'Croft', 'Pendelton', 'Westcott', 'Hasting', 'Fairwind', 'Harlow', 'Oakheart', 'Rivers',
            'Hightower', 'Greenfield', 'Crowther', 'Whitmore', 'Woodhouse', 'Horton',
        ],
        'elf' => [
            'Moonwhisper', 'Dawnveil', 'Starweave', 'Silverleaf', 'Brightgale', 'Thornmist', 'Goldenbow', 'Oakhallow',
            'Windrunner', 'Sunstrider', 'Whisperwind', 'Shadowsong', 'Ravenshadow', 'Duskwarden', 'Stormrider', 'Greenbough',
            'Moonshadow', 'Silverdew', 'Starflower', 'Wildwood', 'Deeproot', 'Spellweaver', 'Swiftblade', 'Forestwalker',
            'Evergreen', 'Leafweaver', 'Gladekeeper', 'Miststrider', 'Galeshield', 'Starlight',
        ],
        'dwarf' => [
            'Stonebrew', 'Ironmantle', 'Copperpick', 'Graniteback', 'Forgeborn', 'Hammerfist', 'Deepdelve', 'Goldvein',
            'Shieldbreaker', 'Axebearer', 'Ironfoot', 'Mountainheart', 'Coalminer', 'Runecarver', 'Bronzebeard', 'Steelshaper',
            'Bouldershoulder', 'Anvilstrike', 'Earthbreaker', 'Flintridge', 'Heavyhammer', 'Gemcutter', 'Orefinder', 'Rockbreaker',
            'Underhill', 'Silverminer', 'Strongbrew', 'Coldsteel', 'Stonehewer', 'Deepforge',
        ],
        'orc' => [
            'Bloodtusk', 'Skullsplitter', 'Ironjaw', 'Bonecrusher', 'Ravenfang', 'Grimhide', 'Warscream', 'Steelhide',
            'Doomhammer', 'Hellcream', 'Gorehowl', 'Blackrock', 'Thunderlord', 'Bleedinghollow', 'Shatteredhand', 'Dragonmaw',
            'Shadowmoon', 'Frostwolf', 'Laughingcull', 'Rippedear', 'Scarface', 'Ashbiter', 'Ironfury', 'Bonegnawer',
            'Stoneshield', 'Deathrattle', 'Wildfury', 'Gorebelly', 'Skullcracker', 'Broadaxe',
        ],
        'undead' => [
            'Ashveil', 'Bonechill', 'Duskmantle', 'Gravewarden', 'Hollowsoul', 'Nightshade', 'Rotwick', 'Wraithborn',
            'Deathweaver', 'Soulreaper', 'Gravebound', 'Plaguebearer', 'Coldheart', 'Blightwood', 'Tombborn', 'Shadowstrike',
            'Ebonrun', 'Grimshade', 'Corpsewalker', 'Decaycrest', 'Cryptdust', 'Ghostwalker', 'Phantomshade', 'Necroglen',
            'Darkbinder', 'Soulburn', 'Nightfall', 'Dreadwood', 'Bonecarver', 'Rotlimb',
        ],
        'giant' => [
            'Mountainfoot', 'Boulderback', 'Stormcrown', 'Earthshaker', 'Ironpeak', 'Cliffborn', 'Skycleave', 'Thundrus',
            'Stonefist', 'Frostgiant', 'Firegiant', 'Cloudstrider', 'Hillbreaker', 'Earthgirdle', 'Rockhurler', 'Titanstride',
            'Avalanche', 'Summitwalker', 'Cragstone', 'Peakclimber', 'Heavystep', 'Glaciershield', 'Ridgeback', 'Worldpillar',
            'Deeproot', 'Stormshield', 'Ironback', 'Earthbreaker', 'Skybreaker', 'Granitefist',
        ],
        'ent' => [
            'Ancientheart', 'Deepgrain', 'Oldbark', 'Stonewood', 'Grimroot', 'Ironbark', 'Gnarledbough', 'Knotwood',
            'Tangledroot', 'Broadbough', 'Greenthorn', 'Mosshaven', 'Fernweald', 'Meadowstride', 'Oakenshield', 'Greywood',
            'Oldgrowth', 'Deepwood', 'Stillwater', 'Marshfoot', 'Bogwood', 'Fernback', 'Thornback', 'Grovewalker',
            'Canopyshade', 'Undergrowth', 'Willowmere', 'Ashgrove', 'Elmhurst', 'Cedarholm',
        ],
        'genie' => [
            'al-Rashid', 'al-Noor', 'ibn Zafar', 'al-Sabah', 'bint Sirocco', 'al-Qamar', 'ibn Lamiya', 'al-Azhar',
            'al-Amin', 'al-Hassan', 'al-Farabi', 'al-Ghazali', 'ibn Battuta', 'ibn Sina', 'al-Masmudi', 'al-Khwarizmi',
            'al-Kindi', 'al-Bakri', 'ibn Rushd', 'al-Tusi', 'al-Idrisi', 'al-Haytham', 'al-Biruni', 'al-Mansur',
            'al-Mu\'izz', 'al-Hakim', 'al-Mustansir', 'al-Adid', 'al-Kamil', 'al-Salih',
        ],
    ];

    public function __construct(
        private readonly RaceConfig $raceConfig,
    ) {
    }

    /**
     * Creates a fresh level-1 hero for a team.
     *
     * Stats are generated per attribute using race bonuses and Summoning Chamber bonuses.
     * To prevent "super-specialized" or "perfect" all-rounder heroes:
     *   1. Individual stats are capped by a level-based and race-based limit.
     *   2. The total stat sum is capped at 60% of the sum of these individual limits.
     *
     * Scaling targets (max race bonus = +3, actual configuration: base +0.4, random +1.0):
     *   - Chamber level 1  : max single stat 5 (with +3 race), total cap 12-14
     *   - Chamber level 5  : max single stat 11 (with +3 race), total cap 36-43
     *   - Chamber level 10 : max single stat 17 (with +3 race), total cap 68-72
     *
     * @param array{summon_base_stat_bonus?: float, summon_stat_random_bonus?: float, summon_stat_total_cap?: float} $chamberBonuses
     *                                                                                                                               Passive bonuses from the team's Summoning Chamber facility.
     *                                                                                                                               Pass an empty array for NPC teams at kingdom initialisation — built-in constants are used as fallback.
     */
    public function createForTeam(Team $team, Race $race, array $chamberBonuses = []): Hero
    {
        $ageConfig = $this->raceConfig->getAge($race);
        $statBonuses = $this->raceConfig->getStatBonuses($race);

        $effectiveBase = self::BASE_STAT_VALUE + (int) round($chamberBonuses['summon_base_stat_bonus'] ?? 0);
        $effectiveRandomMax = self::STAT_RANDOM_MAX + (int) round($chamberBonuses['summon_stat_random_bonus'] ?? 0);
        $level = (int) ($chamberBonuses['summon_stat_random_bonus'] ?? 1);
        $reduction = 2 + (int) round(($level - 1) * 0.11);

        $sumOfMaxStats = 0;
        $maxLimits = [];
        $statsKeys = ['str', 'dex', 'kon', 'spd', 'int', 'wil', 'cha', 'lck'];
        foreach ($statsKeys as $key) {
            $raceBonus = $statBonuses[$key] ?? 0;
            $naturalMax = $effectiveBase + $effectiveRandomMax + $raceBonus;
            $maxLimits[$key] = max(1, $naturalMax - $reduction);
            $sumOfMaxStats += $maxLimits[$key];
        }

        // The total cap is dynamically set to 60% of the sum of all individual stat ceilings (minimum 8)
        $maxTotal = max(8, (int) round($sumOfMaxStats * 0.6));

        $rawStats = [];
        foreach ($statsKeys as $key) {
            $rawStats[$key] = $this->randomStat($statBonuses[$key] ?? 0, $maxLimits[$key], $chamberBonuses);
        }

        $stats = $this->enforceStatCap($rawStats, $maxTotal, $statBonuses);

        $hero = new Hero();
        $hero->setTeam($team);
        $hero->setName($this->randomName($race));
        $hero->setRace($race);
        $hero->setAge(random_int($ageConfig['min'], max($ageConfig['min'], $ageConfig['max_junior'])));

        $hero->setStrRaw(min(200, $stats['str'] * 10 + random_int(0, 9)));
        $hero->setDexRaw(min(200, $stats['dex'] * 10 + random_int(0, 9)));
        $hero->setKonRaw(min(200, $stats['kon'] * 10 + random_int(0, 9)));
        $hero->setSpdRaw(min(200, $stats['spd'] * 10 + random_int(0, 9)));
        $hero->setIntelRaw(min(200, $stats['int'] * 10 + random_int(0, 9)));
        $hero->setWilRaw(min(200, $stats['wil'] * 10 + random_int(0, 9)));
        $hero->setChaRaw(min(200, $stats['cha'] * 10 + random_int(0, 9)));
        $hero->setLckRaw(min(200, $stats['lck'] * 10 + random_int(0, 9)));

        return $hero;
    }

    /**
     * Generates a single stat value using effective base and random range derived from
     * the Summoning Chamber's passive bonuses on top of the built-in fallback constants.
     *
     * To increase randomness, we roll the stat 3 times and choose one of the rolls at random.
     * Before returning, the value is capped by a dynamic single stat limit, ensuring starting stats are balanced.
     *
     * @param array{summon_base_stat_bonus?: float, summon_stat_random_bonus?: float, summon_stat_total_cap?: float} $chamberBonuses
     */
    private function randomStat(int $raceBonus, int $maxStatLimit, array $chamberBonuses = []): int
    {
        $effectiveBase = self::BASE_STAT_VALUE + (int) round($chamberBonuses['summon_base_stat_bonus'] ?? 0);
        $effectiveRandomMax = self::STAT_RANDOM_MAX + (int) round($chamberBonuses['summon_stat_random_bonus'] ?? 0);

        // Roll 3 times and pick one randomly to increase randomness/variance structure
        $rolls = [];
        for ($i = 0; $i < 3; ++$i) {
            $rolls[] = $effectiveBase + $raceBonus + random_int(0, $effectiveRandomMax);
        }
        $base = $rolls[array_rand($rolls)];

        $finalStat = min($maxStatLimit, $base);

        return max(1, $finalStat);
    }

    /**
     * Trims stats organically by decrementing random attributes one by one until the total is at or below $maxTotal.
     * Each stat is protected by a minimum floor (1 or its positive race bonus, whichever is higher).
     *
     * @param array<string, int> $stats
     * @param array<string, int> $statBonuses
     *
     * @return array<string, int>
     */
    private function enforceStatCap(array $stats, int $maxTotal, array $statBonuses): array
    {
        $excess = array_sum($stats) - $maxTotal;
        if ($excess <= 0) {
            return $stats;
        }

        // Randomly decrement eligible stats one by one to organically distribute the trimming
        // We protect stats with positive race bonuses by not letting them be trimmed below the bonus value.
        while ($excess > 0) {
            $eligibleKeys = [];
            foreach ($stats as $key => $val) {
                $minLimit = isset($statBonuses[$key]) ? max(1, $statBonuses[$key]) : 1;
                if ($val > $minLimit) {
                    $eligibleKeys[] = $key;
                }
            }

            if (empty($eligibleKeys)) {
                break; // Safety fallback if all stats reach their minimum limits
            }

            $randomKey = $eligibleKeys[array_rand($eligibleKeys)];
            --$stats[$randomKey];
            --$excess;
        }

        return $stats;
    }

    private function randomName(Race $race): string
    {
        $firstNames = self::NAMES_BY_RACE[$race->value];
        $surnames = self::SURNAMES_BY_RACE[$race->value];

        $firstName = $firstNames[array_rand($firstNames)];
        $surname = $surnames[array_rand($surnames)];

        return trim($firstName.' '.$surname);
    }
}
