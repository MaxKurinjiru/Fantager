<?php

declare(strict_types=1);

namespace App\Model\Facade;

trait JuniorCharacter
{

	/**************************************************************************/

	public function statDistribution($points) {

		$numbers = \App\Helper\ArrayFill::randomAverage($points, 6, $this->config['character']['limit_new']);
		// shuffle and random sort stats
		foreach ($this->config['character']['stats'] as $s) {
			if ($s == 'CHA' || $s == 'LUC') {
				continue;
			}
			$r = array_rand($numbers, 1);
			$stats[$s] = $numbers[$r];
			unset($numbers[$r]);
		}
		return $stats;
	}

    /**************************************************************************/

	public function newJunior(
		$arenaRow,
		$teamRow,
		$userRow
	) {
		/**
		 * 
		 * Arena level: 1-8
		 * Junior trainers: 0-100 -> generate weekly bonus 0-600
		 * Age
		 * Race
		 * Team morale
		 * 
		 * maximum stat level for new hero ?
		 * 
		 * ex: all set to min
		 * arena 1, no jtrainers, bad race, no morale
		 * 
		 * - max should be fe. 3 ? 1-2 + arena(1)
		 * 
		 * ex: all max
		 * arena 8, full trainers, right race, full morale
		 * 
		 * - max should be fe. 12 ? 1-2 + arena(8/2 = 4) + race(0-1) + morale(0-2) + train(0-3)
		*/

		$junior = [];
		$points = 4;

		$arenaLevel = $arenaRow->level;
		$newRaceRow = $arenaRow->ref('race');
		$newRace = $arenaRace = $arenaRow->race;
		$morale = $teamRow->morale;

		// arena level
		// +1 for ideal race (-2 for wrong instead -1 later for short else statement)
		$points += (8 + 1) * ceil($arenaLevel / 2);	// max 9*8 = 72

		// race selection (chance for another race 50:50)
		if (mt_rand(0, 1) == 1) {
			$newRaceRow = $this->getFacade('race')->table()->where('tolerance_' . $arenaRace .' >= 50')->order('RAND()')->limit(1)->fetch();
			$newRace = $newRaceRow->id;
			// not ideal race -2 instead of -1
			$points -= 2 * mt_rand(1, $arenaLevel);
		}

		// age selection
		$raceRow = $this->getFacade('race')->table()->get($newRace);
		// counting age between min and old
		// *10 for year->month transform
		$age_min = $raceRow->age_min * 10;
		$age_max = $raceRow->age_old * 10;
		$age_mid = (int) floor(($age_max + $age_min) / 2);

		$newAge = mt_rand($age_min, $age_max);

		// half of young age over
		if ($newAge >= $age_mid) {
			$points += mt_rand(1, $arenaLevel); // max + 8 = 80
		}

		// team morale
		// each if statement can be valid
		//      for morale > 90 can be only *1 instead *2
		//      -> morale > 75 is applied too :-)
		if ($morale > 90) {
			$points += mt_rand(1, $arenaLevel); // max + 8 = 88
		}
		if ($morale > 75) {
			$points += mt_rand(1, $arenaLevel);	// max + 8 = 96
		}

		if ($morale < 60) {
			$points -= mt_rand(1, $arenaLevel);
		}
		if ($morale < 40) {
			$points -= mt_rand(1, $arenaLevel);
		}

		// name generation
		$newName = \App\Model\Name::generate($newRaceRow->code);
		//\Tracy\Debugger::barDump($newName, 'newName');

		$rands = [];
		for ($i = 1; $i < 8; $i++) {
			$rands[] = mt_rand(1, 20);
		}

		// junior array colection
		$junior['race'] = $newRace;
		$junior['name'] = $newName;
		$junior['age'] = $newAge;
		// charisma & luck are always rand(1-20)
		foreach (['CHA', 'LUC'] as $s) {
			$r = array_rand($rands, 1);
			$junior[$s] = $rands[$r];
			unset($rands[$r]);
		}
		// points distribution
		//\Tracy\Debugger::barDump($points, 'points');
		$stats = $this->statDistribution($points);
		//\Tracy\Debugger::barDump($stats, 'stats');

		// merge stats to junior
		$junior = array_merge($junior, $stats);
		// save junior
		$juniorRow = $this->save($junior);
		// assign to team
		$this->assignCharacter($juniorRow, $teamRow, false, true);

		$arenaRow->update(['junior_pull' => \App\Helper\Format::getTimestamp()]);

		$this->getFacade('activity')->saveActivity([
			'user' => $userRow->id,
			'text' => 'system.activity.team_junior_trained',
			'vars' => \App\Helper\Format::setJson([
				'name' => $userRow->nickname,
				'heroname' => $juniorRow->name,
				'heroid' => $juniorRow->id,
				'herostats' => implode("|", $stats)
			])
		]);

		return $juniorRow;

		//\Tracy\Debugger::barDump($sum, '$sum');
		//\Tracy\Debugger::barDump($junior, '$junior');

	}

	/**************************************************************************/

}
