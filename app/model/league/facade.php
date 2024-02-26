<?php

declare(strict_types=1);

namespace App\Model\Facade;

use Nette;

final class League extends DB
{
	/** @var string */
	public $table = 'league';

	/**************************************************************************/

	public function tableLeagueTeam() {
		return $this->table('league_team');
	}

	/**************************************************************************/

	public function findByName(int $league, string $group, int $session = null) {
		$selection = $this->table()->where(['group' => $group, 'league' => $league])->order('session DESC');
		if ( !empty($session) ) {
			$selection->where(['session' => $session]);
		}

		return $selection;
	}

	public function findLeagueByTag(string $param) {
		$array = explode(".", $param);
		if ( !empty($array[0]) && !empty($array[1]) ) {
			return $this->findByName(intval($array[0]), $array[1]);
		}
		return false;
	}

	public function findLeagueByTeam(int $team) {
		return $this->table()->where([':league_team.team' => $team]);
	}

	public function findTeamsInLeague(int $league) {
		return $this->tableLeagueTeam()->where(['league' => $league]);
	}

	/**************************************************************************/

	public function create(array $data) {
		return $this->save($data);
	}

	/**************************************************************************/

}
