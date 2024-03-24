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
		$junior = [];
		$points = 4;

		$arenaLevel = $arenaRow->level;
		$arenaLevelHalf = (int) ceil($arenaLevel / 2);
		$newRaceRow = $arenaRow->ref('race');
		$newRace = $arenaRace = $arenaRow->race;
		$morale = $teamRow->morale;

		// arena level
		// +1 for ideal race (-2 for wrong instead -1 later for short else statement)
		$points += (8 + 1) * $arenaLevelHalf; // base by arenaHalf || + 9-36

		// race selection (chance for another race cca 50:50)
		// still could be ideal, cos it have 100 tolerance
		if (mt_rand(0, 1) == 1) {
			$newRaceRow = $this->getFacade('race')->table()->where('tolerance_' . $arenaRace .' >= 50')->order('RAND()')->limit(1)->fetch();
			$newRace = $newRaceRow->id;
		}

		// not ideal race
		if ($newRace !== $arenaRace) {
			// points handicap for not ideal race
			$points -= 2 * mt_rand(1, $arenaLevelHalf); // penalty || - 2-8
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
			// points benefits for "longer" time in training
			$points += mt_rand(1, $arenaLevelHalf); // random || + 1-4
		}

		// team morale bonus/penalty points
		// "each" if statement can be valid
		if ($morale > 90) {
			$points += mt_rand(1, $arenaLevelHalf); // random || + 1-4
		}
		if ($morale > 75) {
			$points += mt_rand(1, $arenaLevelHalf);	// random || + 1-4
		}
		// "each" if statement can be valid
		if ($morale < 60) {
			$points -= mt_rand(1, $arenaLevelHalf); // random penalty || - 1-4
		}
		if ($morale < 40) {
			$points -= mt_rand(1, $arenaLevelHalf); // random penalty || - 1-4
		}

		// arena personal trainers value
		$confJuniorBonus = $this->config['character']['junior_bonus'];
		$arenaBonusAvail = $arenaRow->junior_bonus;
		
		$juniorBonusSpend = 0;
		$juniorBonus = 0;
		
		// have bonus for one more point at least
		if ($arenaBonusAvail >= $confJuniorBonus) {
			// increasing value spent for each point
			while($arenaBonusAvail >= $juniorBonusSpend) {
				$juniorBonus++;
				$juniorBonusPrice = (int) ceil(sqrt($juniorBonus) * $confJuniorBonus * sqrt($arenaLevelHalf));
				$juniorBonusSpend += $juniorBonusPrice;
			}

			// todo: better calculating process to prevent this
			// overtake last point
			if ($arenaBonusAvail < $juniorBonusSpend) {
				$juniorBonus--;
				$juniorBonusSpend -= $juniorBonusPrice;
			}

			$points += $juniorBonus;
			$arenaRow->update(['junior_bonus' => $arenaBonusAvail - $juniorBonusSpend]);
		}

		// name generation
		$newName = \App\Model\Name::generate($newRaceRow->code);

		// junior array colection
		$junior['race'] = $newRace;
		$junior['name'] = $newName;
		$junior['age'] = $newAge;

		// randoms for luck and charisma
		// make 8 possible random values and choose random two from them
		$rands = [];
		for ($i = 1; $i < 8; $i++) {
			$rands[] = mt_rand(1, 20);
		}
		foreach (['CHA', 'LUC'] as $s) {
			$r = array_rand($rands, 1);
			$junior[$s] = $rands[$r];
			unset($rands[$r]);
		}
		
		// points distribution
		$stats = $this->statDistribution($points);

		// merge stats to junior
		$junior = array_merge($junior, $stats);
		// save junior
		$juniorRow = $this->save($junior);
		// assign to team
		$this->assignCharacter($juniorRow, $teamRow, $retire = false, $trained = true);

		$arenaRow->update(['junior_pull' => \App\Helper\Format::getTimestamp()]);

		$this->getFacade('activity')->saveActivity([
			'user' => $userRow->id,
			'text' => 'system.activity.team_junior_trained',
			'vars' => \App\Helper\Format::setJson([
				'name' => $userRow->nickname,
				'heroname' => $juniorRow->name,
				'heroid' => $juniorRow->id,
				'herostats' => implode("|", $stats),
				'points' => $points,
				'jBonus' => $juniorBonus,
				'arenaLevel' => $arenaLevel,
				'morale' => $morale,
				'age' => $newAge . '/' . $age_mid,
				'racematch' => $newRace == $arenaRace,
			])
		]);

		return $juniorRow;

		//\Tracy\Debugger::barDump($sum, '$sum');
		//\Tracy\Debugger::barDump($junior, '$junior');

	}

	/**************************************************************************/

}
