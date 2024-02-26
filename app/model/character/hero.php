<?php

declare(strict_types=1);

namespace App\Model;

use Nette;

final class Hero extends Character
{

	private $race;
	private $age;
	private $exp;

	private $trainer;

	private $weapon_sword = 0;
	private $weapon_axe = 0;
	private $weapon_blunt = 0;
	private $weapon_spear = 0;
	private $weapon_bow = 0;
	private $weapon_crossbow = 0;
	private $weapon_staff = 0;

	private $magic_life = 0;
	private $magic_death = 0;
	private $magic_chaos = 0;
	private $magic_order = 0;
	private $magic_nature = 0;

	private $items = [];

	/**************************************************************************/



	/**************************************************************************/

	public function isTrainer() {
		return $this->trainer ? true : false;
	}

	/**************************************************************************/

	public function getLevelXp($level) {
		return pow($level, 1.47) * 336;
	}

	public function getNextLevelXp() {
		return $this->getLevelXp($this->level);
	}

	public function gainXp($xp) {
		if (!$this->row) {
			return;
		}

		$this->exp += $xp;

		$this->row->update(['exp' => $this->exp]);

		while ($this->exp >= $this->getNextLevelXp()) {
			$this->levelUp();
		}

		// action for exp gain, like event log..
	}

	public function levelUp() {
		if (!$this->row) {
			return;
		}

		$this->level = $this->level + 1;
		$this->row->update(['level' => $this->level]);

		// action for lvl up, like event log..
	}

	/**************************************************************************/

	// change race bonus to stats
	public function changeCalc() {
		// default
		/*
		private $calc_hp_level = 5;
		private $calc_hp_CON = 1;

		private $calc_mp_level = 7;
		private $calc_mp_INT = 3;

		private $calc_atk_level = 1;
		private $calc_atk_stat = 2;

		private $calc_def_level = 2;
		private $calc_def_DEX = 2;
		*/

		switch ($this->race) {

			// man
			case 1: {

			} break;

			// elf
			case 2: {

			} break;

			// dwarf
			case 3: {
				//$this->calc_mp_level -= 1;
			} break;

			// orc
			case 4: {
				$this->calc_mp_INT -= 1;
			} break;

			// undead
			case 5: {
				$this->calc_mp_INT += 1;
			} break;

			// giant
			case 6: {
				$this->calc_hp_level += 1;
				$this->calc_mp_INT -= 1;
				$this->calc_atk_stat += 1;
				$this->calc_atk_level += 1;
				$this->calc_def_DEX += 1;
			} break;

			// ent
			case 7: {
				$this->calc_hp_CON += 1;
				$this->calc_atk_stat += 1;
				$this->calc_def_level += 2;
				$this->calc_def_DEX += 1;

				$this->calc_armor_level = 2;
			} break;

			// jinn
			case 8: {
				$this->calc_mp_level += 2;
				$this->calc_atk_stat -= 1;
				$this->calc_def_level -= 1;
			} break;

		}
	}

	/**************************************************************************/

	public function addItem($item) {
		$this->items[] = $item;
	}

	/**************************************************************************/

	public function isOld() {
		if ($this->age >= $this->getAgeOld()) {
			return true;
		}
		return false;
	}

	public function isOverliving() {
		if ($this->age >= $this->getAgeMax()) {
			return true;
		}
		return false;
	}

	/**************************************************************************/

	public function getRaceRef() {
		return $row->ref('race');
	}

	public function getRaceCode() {
		return $this->getRaceRef()->code;
	}

	public function getAgeMax() {
		return $this->getRaceRef()->age_max * 10;
	}

	public function getAgeOld() {
		return $this->getRaceRef()->age_old * 10;
	}

	public function getRaceTolerance($race) {
		$raceTolerance = 'tolerance_' . $race;
		return $this->getRaceRef()->$raceTolerance;
	}

	/**************************************************************************/

	public function getMaxHP() {
		$max_hp = parent::getMaxHP();

		// equipment and (de)buff

		return (int) floor($max_hp);
	}

	public function getMaxMP() {
		$max_mp = parent::getMaxMP();

		// equipment and (de)buff

		return (int) floor($max_mp);
	}

	public function getAtk($isRanged = false) {
		$atk = parent::getAtk($isRanged);

		// equipment and (de)buff

		return (int) floor( $atk );
	}

	public function getDef() {
		$def = parent::getDef();

		// equipment and (de)buff

		return (int) floor( $def );
	}

	public function getArmor() {
		$armor = parent::getArmor();

		// equipment and (de)buff

		return (int) floor( $armor );
	}

	/**************************************************************************/

	public function getMagic($school) {
		$m = 'magic_' . $school;
		if ( property_exists($this, $m) ) {
			return $this->$m;
		}
	}

	public function setMagic($school, $value) {
		$m = 'magic_' . $school;
		if ( property_exists($this, $m) ) {
			$this->$m = $value;
		}
		return $this;
	}

	public function getWeapon($type) {
		$m = 'weapon_' . $type;
		if ( property_exists($this, $m) ) {
			return $this->$m;
		}
	}

	public function setWeapon($type, $value) {
		$m = 'weapon_' . $type;
		if ( property_exists($this, $m) ) {
			$this->$m = $value;
		}
		return $this;
	}

	/**************************************************************************/

}
