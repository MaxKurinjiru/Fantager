<?php

declare(strict_types=1);

namespace App\Model\Facade;

use Nette;

final class Kingdom extends DB
{
	/** @var string */
	public $table = 'kingdom';

	/**************************************************************************/

	public function saveKingdom(array $data) {
		return $this->save($data);
	}

	/**************************************************************************/

	public function findKingdomById(int $id) {
		return $this->table()->where(['id' => $id]);
	}

	public function findKingdomByCode(string $k) {
		return $this->table()->where(['code' => $k]);
	}

	/**************************************************************************/

	public function getKingdomList($withPlayers = false) {
		$kingdomSlots = \App\Helper\Config::kingdomSlots();
		$array = array();
		foreach ( $this->table()->order('created DESC') as $row) {
			$kingdomName = $row->name;

			if ($withPlayers) {
				$players = count( $this->getFacade('user')->playersInKingdom($row->id) );

				$kingdomName .= ' (' . $players . '/' . $kingdomSlots . ')';
			}

			$array[$row->id] = $kingdomName;
		}
		return $array;
	}

	/**************************************************************************/

	public function isKingdomFull(int $id) {
		$kingdomSlots = \App\Helper\Config::kingdomSlots();
		$players = count( $this->getFacade('user')->playersInKingdom($id) );

		if ($players < $kingdomSlots) {
			return false;
		}
		return true;
	}

	/**************************************************************************/

}
