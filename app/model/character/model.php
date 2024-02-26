<?php

declare(strict_types=1);

namespace App\Model;

use Nette;

final class CharacterOLD
{
	private $row;

	private $id;
	private $race;
	private $name;
	private $age;
	private $exp;
	private $level;
	private $form;
	private $fatigue;

	private $trainer;
	private $alive;

	private $STR;
	private $DEX;
	private $CON;
	private $SPD;
	private $INT;
	private $WIL;
	private $CHA;
	private $LUC;

	private $stats_hp = 0;
	private $stats_mp = 0;
	private $stats_melee = 0;
	private $stats_ranged = 0;
	private $stats_defence = 0;
	private $stats_armor = 0;

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

	/**************************************************************************/

	public function __construct($row = null) {
		$this->row = $row;

		$this->init();

		return $this;
	}

	/**************************************************************************/

	public function init() {
		if (!$this->row) {
			return;
		}

		$rowArray = $this->row->toArray();
		unset($rowArray['created']);

		foreach ($rowArray as $key => $value) {
			if ( property_exists($this, $key) ) {
				$this->$key = $value;
			}
		}
	}

	// init without DB->row
	public function create($array) {
		foreach ($array as $key => $value) {
			if ( property_exists($this, $key) ) {
				$this->$key = $value;
			}
		}
	}

	/**************************************************************************/

	public function isTrainer() {
		return $this->trainer ? true : false;
	}

	public function isAlive() {
		return $this->alive ? true : false;
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

	public function getBaseStats() {
		if ($this->isTrainer()) {
			return;
		}

		$this->stats_hp = $this->getStatMaxHP();
		$this->stats_mp = $this->getStatMaxMP();
		$this->stats_melee = $this->getStatAtk();
		$this->stats_ranged = $this->getStatAtk($ranged = true);
		$this->stats_defence = $this->getStatDef();
		$this->stats_armor = $this->getStatArmor();
	}

	/**************************************************************************/

	public function getRaceRef() {
		return $row->ref('race');
	}

	public function getRaceCode() {
		return $this->getRaceRef()->code;
	}

	public function getAgeMax() {
		return $this->getRaceRef()->age_max;
	}

	public function getAgeOld() {
		return $this->getRaceRef()->age_old;
	}

	public function getRaceTolerance($race) {
		$raceTolerance = 'tolerance_' . $race;
		return $this->getRaceRef()->$raceTolerance;
	}

	/**************************************************************************/

	public function getStatMaxHP() {
		// base hp for level
		$lvlHP = 5;
		// base hp for CON
		$conHP = 1;

			if ($this->race == 6) {
				// obr
				$lvlHP += 1;
			}

			if ($this->race == 7) {
				// ent
				$conHP += 1;
			}

		$conHPtotal = $conHP * $this->CON * $this->level;
		$lvlHPtotal = $lvlHP * ($realLvl + 1);

		return (int) floor($conHPtotal + $lvlHPtotal);
	}

	public function getStatMaxMP() {
		// base mp for level
		$lvlMP = 7;
		// base mp for INT
		$intMP = 3;

			if ($this->race == 8) {
				// jinn
				$lvlMP += 2;
			}
			if ($this->race == 3) {
				// trpaslik
				$lvlMP += 2;
			}

			if ($this->race == 5) {
				// nemrtvi
				$intMP += 1;
			}
			if ($this->race == 4 || $this->race == 6) {
				// skreti a obri
				$intMP -= 1;
			}

		$intMPtotal = $intMP * $this->INT * $this->level;
		$lvlMPtotal = $lvlMP * ($this->level + 1);

		return (int) floor($intMPtotal + $lvlMPtotal);
	}

	public function getStatAtk($isRanged = false) {
		// base atk for lvl
		$lvlAtk = 1;
		// base atk for stat (DEX / STR)
		$statAtk = 2;

			if ($this->race == 8) {
				// jinn
				$statAtk -= 1;
			}
			if ($this->race == 7) {
				// ent
				$statAtk += 1;
			}
			if ($this->race == 6) {
				// obr
				$statAtk += 1;
				$lvlAtk += 1;
			}

		$both = $lvlAtk * $this->level;

		$strStat = $this->STR / 2;
		$dexStat = $this->DEX / 2;

		if ($isRanged) {
			$ranged = $statAtk * (($strStat / 2) + $dexStat);
			$ranged = $both + $ranged;
			return (int) floor($ranged);
		}

		$melee = $statAtk * ($strStat + ($dexStat / 2));
		$melee = $both + $melee;
		return (int) floor($melee);
	}

	public function getStatDef() {
		// base def for level
		$defLvl = 2;
		// base def for DEX
		$defDex = 2;

			if ($this->race == 8) {
				// jinn
				$defLvl -= 1;
			}
			if ($this->race == 7) {
				// ent
				$defLvl += 2;
				$defDex += 1;
			}
			if ($this->race == 6) {
				// obr
				$defDex += 1;
			}

		$totalDef = ($defLvl * $this->level) + ($defDex * $this->DEX);

		return (int) floor($totalDef);
	}

	public function getStatArmor() {
		if ($this->race != 7) {
			// not ent
			return 0;
		}

		// base def for level
		$armorLvl = 2;

		$totalArmor = $armorLvl * $this->level;

		return (int) floor($totalArmor);
	}

}
