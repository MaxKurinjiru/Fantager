<?php

declare(strict_types=1);

namespace App\Model\Facade;

use Nette;

final class Arena extends DB
{
	/** @var string */
	public $table = 'team_arena';

	/**************************************************************************/

	public function findByTeam(int $id) {
		return $this->table()->where(array('team' => $id))->fetch();
	}

	/**************************************************************************/

	/* Arena levels */

	public function existArenaLevel(int $lvl) {
		return !empty($this->config['arena'][$lvl]);
	}

	public function getArenaSeatsByLevel(int $lvl) {
		if (!$this->existArenaLevel($lvl)) {
			return false;
		}
		return $this->config['arena'][$lvl]['seats'];
	}

	public function getArenaPriceByLevel(int $lvl) {
		if (!$this->existArenaLevel($lvl)) {
			return false;
		}
		return $this->config['arena'][$lvl]['price'];
	}

	public function getPayMaintenanceLvl($lvl, $randomized = true) {
		$arenaPrice = $this->getArenaPriceByLevel($lvl);
		$arenaSeats = $this->getArenaSeatsByLevel($lvl);

		$seatPriceDiff = $arenaPrice / $arenaSeats * 0.1;

		$price = $seatPriceDiff * $arenaSeats;

		return \App\Helper\Format::priceRandomizer($price, $randomized);
	}

	public function getArenaDaysByLevel(int $lvl) {
		if (!$this->existArenaLevel($lvl)) {
			return false;
		}
		return $this->config['arena'][$lvl]['days'];
	}

	/**************************************************************************/

	public function getPayMaintenance(int $id, $randomized = true) {
		if (!$arenaRow = $this->table()->where(array('team' => $id))->fetch()) {
			return 0;
		}

		return $this->getPayMaintenanceLvl($arenaRow->level, $randomized);
	}

	/**************************************************************************/

	public function getFullArenaTicketsPay($lvl) {
		$arenaSeats = $this->getArenaSeatsByLevel($lvl);

		$price = $arenaSeats * $this->config['ticketPrice'];

		return \App\Helper\Format::priceRandomizer($price, $randomized);
	}

	/**************************************************************************/

	public function getArenaBuildLeft(int $lvl, int $progress) {
		if (!$this->existArenaLevel($lvl)) {
			return false;
		}

		$daysTotal = $this->config['arena'][$lvl]['days'];

		return (int) ceil($daysTotal - $progress);
	}

	/**************************************************************************/

	public function startArenaUpgrade($arenaRow) {
		// už je v procesu
		if ( !empty($arenaRow->level_progress) ) {
			return false;
		}

		$newLevel = $arenaRow->level + 1;

		// nelze víc vylepšit
		if ( !$arenaCost = $this->getArenaPriceByLevel($newLevel) ) {
			return false;
		}

		$teamRow = $arenaRow->ref('team');

		// nejsou money
		if ( $teamRow->money < $arenaCost) {
			return false;
		}

		if ($userRow = $teamRow->ref('user')) {
			$activity = [
				'user' => $userRow->id,
				'text' => 'system.activity.arena_upgrade_start',
				'vars' => \App\Helper\Format::setJson([
					'name' => $userRow->nickname,
					'level' => $newLevel
				])
			];
		} else {
			$activity = [
				'text' => 'system.activity.arena_upgrade_start',
				'vars' => \App\Helper\Format::setJson([
					'name' => '(#' . $teamRow->id . ') ' . $teamRow->name,
					'level' => $newLevel
				])
			];
		}

		$payPrice = ($arenaCost * -1);

		\App\Model\Facade\Team::changeMoney($teamRow, $payPrice);

		$this->getFacade('activity')->saveActivity($activity);
		$this->getFacade('activity')->saveFinance([
			'team' => $teamRow->id,
			'value' => $payPrice,
			'action' => 'system.activity.finance_action.arena_upgrade'
		]);

		$arenaRow->update(['level_progress' => 1]);
	}

	/**************************************************************************/

	public function completeArenaUpgrade($arenaRow) {
		if ( empty($arenaRow->level_progress) ) {
			return false;
		}

		$newLevel = $arenaRow->level + 1;
		if (!$this->existArenaLevel($newLevel)) {
			return false;
		}

		$teamRow = $arenaRow->ref('team');
		if ($userRow = $teamRow->ref('user')) {
			$mail = [
				'user' => $userRow->id,
				'from' => 1,
				'title' => 'mail.message.arena_upgrade_finish_title',
				'text' => 'mail.message.arena_upgrade_finish',
				'vars' => \App\Helper\Format::setJson([
					'name' => $userRow->nickname,
					'level' => $newLevel
				])
			];

			$this->getFacade('inMail')->saveMail($mail);

			$activity = [
				'user' => $userRow->id,
				'text' => 'system.activity.arena_upgrade_finish',
				'vars' => \App\Helper\Format::setJson([
					'name' => $userRow->nickname,
					'level' => $newLevel
				])
			];
		} else {
			$activity = [
				'text' => 'system.activity.arena_upgrade_finish',
				'vars' => \App\Helper\Format::setJson([
					'name' => '(#' . $teamRow->id . ') ' . $teamRow->name,
					'level' => $newLevel
				])
			];
		}

		$this->getFacade('activity')->saveActivity($activity);

		$arenaRow->update(['level_progress' => null, 'level' => $newLevel]);
	}

	/**************************************************************************/

	public function addArenaProgressStep($arenaRow) {
		if ( empty($progress = $arenaRow->level_progress) ) {
			return false;
		}

		$newLevel = $arenaRow->level + 1;

		$daysTotal = $this->getArenaDaysByLevel($newLevel);

		$complete = (int) $progress + 1;

		if ( $complete >= $daysTotal ) {
			$this->completeArenaUpgrade($arenaRow);
			return;
		}

		$arenaRow->update(['level_progress' => $complete]);

	}

	/**************************************************************************/

	public function getUpgradingArenas() {
		$list = [];

		foreach ($this->table()->where('level_progress IS NOT NULL') as $row) {
			$list[] = $row;
		}

		return $list;
	}

	/**************************************************************************/

	/* Arena Race */

	public function getRaceChangingArenas() {
		$list = [];

		foreach ($this->table()->where('race_change IS NOT NULL') as $row) {
			$list[] = $row;
		}

		return $list;
	}

	/**************************************************************************/

	public function arenaRaceChangeDone($arenaRow) {
		if ( empty($arenaRow->race_change) ) {
			return false;
		}

		//$raceBefore = \App\Helper\Config::raceName($arenaRow->ref('race')->code, 'multi');
		//$raceAfter = \App\Helper\Config::raceName($arenaRow->ref('race_change')->code, 'multi');

		$raceBefore = $this->translator->translate('system.race.' . $arenaRow->ref('race')->code . '.multi');
		$raceAfter = $this->translator->translate('system.race.' . $arenaRow->ref('race_change')->code . '.multi');


		$teamRow = $arenaRow->ref('team');
		if ($userRow = $teamRow->ref('user')) {
			$mail = [
				'user' => $userRow->id,
				'from' => 1,
				'title' => 'mail.message.arena_change_complete_title',
				'text' => 'mail.message.arena_change_complete',
				'vars' => \App\Helper\Format::setJson([
					'racebefore' => $raceBefore,
					'raceafter' => $raceAfter
				])
			];

			$this->getFacade('inMail')->saveMail($mail);

			$activity = [
				'user' => $userRow->id,
				'text' => 'system.activity.arena_change_complete',
				'vars' => \App\Helper\Format::setJson([
					'name' => $userRow->nickname,
					'racebefore' => $raceBefore,
					'raceafter' => $raceAfter
				])
			];
		} else {
			$activity = [
				'text' => 'system.activity.arena_change_complete',
				'vars' => \App\Helper\Format::setJson([
					'name' => '(#' . $teamRow->id . ') ' . $teamRow->name,
					'racebefore' => $raceBefore,
					'raceafter' => $raceAfter
				])
			];
		}

		$this->getFacade('activity')->saveActivity($activity);

		$newRace = $arenaRow->race_change;

		$arenaRow->update(['race_change' => null, 'race' => $newRace]);
	}

	/**************************************************************************/

	public function changeArenaRace($arenaRow, int $race) {
		//\Tracy\Debugger::barDump($arenaRow, 'arenaRow');

		if ( !empty($arenaRow->race_change) ) {
			return false;
		}

		return $arenaRow->update(['race_change' => $race]);
	}

	/**************************************************************************/

	/* Arena Stuff */

	public function changeArenaStaff($arenaRow, $staff, int $count) {
		if ($count > 100) {
			$count = 100;
		}

		if ($count < 0) {
			$count = 0;
		}

		if (!in_array($staff, $this->config['staff']['list'])) {
			return false;
		}

		$staffCol = 'staff_' . $staff;

		$salary = \App\Helper\Config::getStaffSalary();

		$oldCount = $arenaRow->$staffCol;
		$countChange = $count - $oldCount;
		$changeCount = abs($countChange);

		if ( $changeCount == 0) {
			return false;
		}

		$paySalary = $salary * $changeCount;

		$teamRow = $arenaRow->ref('team');
		// nejsou peníze, nejde najmout - propustit ano
		if ( $changeCount > 0 && $teamRow->money < $paySalary) {
			return false;
		}

		$payPrice = ($paySalary * -1);

		\App\Model\Facade\Team::changeMoney($teamRow, $payPrice);

		$this->getFacade('activity')->saveFinance([
			'team' => $teamRow->id,
			'value' => $payPrice,
			'action' => $countChange > 0 ? 'system.activity.finance_action.arena_hire' : 'system.activity.finance_action.arena_fire'
		]);

		return $arenaRow->update([$staffCol => $count]);
	}

	/**************************************************************************/

	public function getStaffSallary($arenaRow) {
		$count = 0;
		$salary = \App\Helper\Config::getStaffSalary();

		foreach ($this->config['staff']['list'] as $s) {
			$p = 'staff_' . $s;
			$count += $arenaRow->$p;
		}

		return (int) $count * $salary;
	}

	/**************************************************************************/

	/* Arena Junior */

	public function resetJunior($arenaRow) {
		return $arenaRow->update(['junior_pull' => null]);
	}

	public function canTrainJunior($arenaRow) {
		return $arenaRow->junior_pull ? false : true;
	}

	/**************************************************************************/

	public function getJuniorsReset() {
		$list = [];

		foreach ($this->table()->where('junior_pull IS NOT NULL') as $row) {
			$list[] = $row;
		}

		return $list;
	}

	/**************************************************************************/

}
