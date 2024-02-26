<?php

declare(strict_types=1);

namespace App\Model;

use Nette;

class Character
{
	// DB->row
	private $row;

	private $id;
	private $name;
	private $level = 1;

	// value 0 - 10
	private $form = 5;

	// value 0 - 100
	private $fatigue = 0;

	// 1 / 0
	private $alive = 1;

	// value 1 - 20
	// _XXX for modif. by (de)buff, equip, etc.
	// XXX for hero base stat
	private $_STR, $STR = 1;
	private $_DEX, $DEX = 1;
	private $_CON, $CON = 1;
	private $_SPD, $SPD = 1;
	private $_INT, $INT = 1;
	private $_WIL, $WIL = 1;
	private $_CHA, $CHA = 1;
	private $_LUC, $LUC = 1;

	// calculated values

	private $max_hp = 0;
	private $max_mp = 0;
	private $hp = 0;
	private $mp = 0;
	private $melee = 0;
	private $ranged = 0;
	private $defence = 0;
	private $armor = 0;


	// calc modif variables

	private $calc_hp_level = 5;
	private $calc_hp_CON = 1;

	private $calc_mp_level = 7;
	private $calc_mp_INT = 3;

	private $calc_atk_level = 1;
	private $calc_atk_stat = 2;

	private $calc_def_level = 2;
	private $calc_def_DEX = 2;

	private $calc_armor_level = 0;

	/**************************************************************************/

	public function __construct($row = null) {
		$this->row = $row;

		$this->init();

		return $this;
	}

	/**************************************************************************/

	public function changeCalc() {
		// modif calc variables for hero
		// race dependency
	}

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
			// stats for modif by items / spells
			if ( property_exists($this, '_'.$key) ) {
				$this->$key = $value;
			}
		}

		$this->changeCalc();
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

	public function isAlive() {
		return $this->alive ? true : false;
	}

	/**************************************************************************/

	public function getMaxHP() {
		$conHPtotal = $this->calc_hp_CON * $this->_CON * $this->level;
		$lvlHPtotal = $this->calc_hp_level * ($this->level + 1);

		return (int) floor($conHPtotal + $lvlHPtotal);
	}

	public function getMaxMP() {
		$intMPtotal = $this->calc_mp_INT * $this->_INT * $this->level;
		$lvlMPtotal = $this->calc_mp_level * ($this->level + 1);

		return (int) floor($intMPtotal + $lvlMPtotal);
	}

	public function getAtk($isRanged = false) {
		$both = $this->calc_atk_level * ($this->level + 1);

		$strStat = $this->_STR / 2;
		$dexStat = $this->_DEX / 2;

		$typeAtk = $strStat + ($dexStat / 2);

		if ($isRanged) {
			$typeAtk = $dexStat + ($strStat / 2);
		}

		return (int) floor( $both + ($typeAtk * $this->calc_atk_stat) );
	}

	public function getDef() {
		return (int) floor( ($this->calc_def_level * $this->level) + ($this->calc_def_DEX * $this->_DEX) );
	}

	public function getArmor() {
		return (int) floor($this->calc_armor_level * $this->level);
	}

}
