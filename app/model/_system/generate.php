<?php

declare(strict_types=1);

namespace App\Model\Facade;

use Nette;

/**
 * this shit is deprecated - create new when this is in need
 * 
 * left here just for inspiration on creating new
*/

final class Generate extends DB
{

	public function tableLeague() {
		return $this->table('league');
	}
	public function saveLeague($data) {
		return $this->save($data, 'league');
	}

	public function tableUser() {
		return $this->table('user');
	}
	public function saveUser($data) {
		return $this->save($data, 'user');
	}

	public function tableTeam() {
		return $this->table('team');
	}
	public function saveTeam($data) {
		return $this->save($data, 'team');
	}

	public function tableArena() {
		return $this->table('arena');
	}
	public function saveArena($data) {
		return $this->save($data, 'arena');
	}

	public function tableLeagueTeam() {
		return $this->table('league_team');
	}
	public function saveLeagueTeam($data) {
		return $this->save($data, 'league_team');
	}


	public function getRandomRace() {
		$race = $this->tableRace()->order('RAND()')->limit(1)->fetch();
		return $race;
	}

	public function newKingdom(array $data) {
		$kingdom = $this->saveKingdom($data);

		$activity = [
			'text' => 'Založeno nové království - ' . $data['name']
		];
		$this->saveActivity($activity);

		$session = $this->newSession($kingdom->id);

		$this->generateFillGroups($kingdom->id, $session->id);
	}

	public function newSession(int $kingdom, string $name = '') {
		$data = [
			"kingdom" => $kingdom,
			"name" => $name
		];

		$activity = [
			'text' => 'Založena nová sezóna (' . $name . ') v království #' . $kingdom
		];
		$this->saveActivity($activity);

		return $session = $this->saveSession($data);
	}

	/****************************************************************************************************************/

	public function removeNpcTeamsFromLeague() {
		foreach ($this->tableLeague() as $mainLeagueRow) {
			\Tracy\Debugger::barDump($mainLeagueRow, 'mainLeagueRow');
			$teamsDelete = $this->tableLeagueTeam()->where('league = ? AND team.user IS NULL', $mainLeagueRow->id)->limit(2);

			foreach ($teamsDelete as $leagueTeam) {
				//$leagueTeam->ref('team')->delete();
				//$team = $leagueTeam->ref('team');
				//\Tracy\Debugger::barDump($team, 'team');
			}
		}
	}

	/****************************************************************************************************************/

	public function generateFillGroups(int $kingdom, int $session) {
		$chars = range('A', 'Z');

		$teams = $this->config['league']['teams'];

		foreach ( $this->config['league']['list'] as $tier => $v ) {
			$g = $v['groups'];

			for ($x = 0; $x < $g; $x++) {
				// skupiny pro ligu
				$leagueData = [
					'session' => $session,
					'league' => $tier,
					'group' => $chars[$x]
				];

				$group = $this->saveLeague($leagueData);

				// teamy do skupiny
				for ($y = 0; $y < $teams; $y++) {

					// generace noveho teamu
					$token = \App\Helper\Token::createToken();
					$teamData = array(
						"name" => "Team #" . $kingdom . $group->id . $y
					);

					$team = $this->saveTeam($teamData);

					// prirazeni teamu do skupiny
					$leagueTeamData = [
						'league' => $group->id,
						'team' => $team->id
					];

					$this->saveLeagueTeam($leagueTeamData);

					// vytvoření arény
					$mainRace = $this->getRandomRace();
					$arenaData = [
						'team' => $team->id,
						'race' => $mainRace->id
					];

					$this->saveArena($arenaData);
				}
			}

		}

		$activity = [
			'text' => 'Vygenerovány ligy, skupiny, teamy a arény v království #' . $kingdom . ' pro sezonu #' . $session
		];
		$this->saveActivity($activity);

	}


	/****************************************************************************************************************/

	public function regenerateGroups() {
		$chars = range('A', 'Z');

		foreach ( $this->table() as $league ) {
			if ( $league->group = 'X' ) {
				// skip svetovy pohar
				continue;
			}
			$leagueID = $league->id;
			$groups = $league->groups;

			$selection = $this->findGroupsByLeague($leagueID)->order('id ASC');
			$selectionCount = count($selection);

			// doplnit skupiny
			if ( $selectionCount < $groups) {
				$addCount = $groups - $selectionCount;

				for ($x = 0; $x < $addCount; $x++) {
					$missing = array(
						"league" => $leagueID,
						"kingdom" => 1,
						"session" => 1,
						"group" => $chars[$selectionCount + $x]
					);
					$this->saveGroup($missing);
				}
			}

			// odebrat skupiny
			if ( $selectionCount > $groups) {
				$i = 0;
				foreach($selection as $row) {
					$i++;
					if ($i >= $groups) {
						continue;
					}
					$row->update(["active" => 0]);
				}
			}
		}
	}

	public function generateTeams() {
		//*
		$array = array();
		foreach ( $this->tableGroup() as $leagueGroup ) {
			$league = $leagueGroup->ref('league');

			$array[] = [
				"group" => $leagueGroup->id,
				"teams" => $league->team_total
			];
		}

		foreach ($array as $group) {
			for ($x = 0; $x < $group['teams']; $x++) {
				$token = \App\Helper\Token::createToken();
				$team = array(
					"name" => "Team Bot #" . $token,
					"league_group" => $group['group'],
					"money" => 50000,
					"rating" => 50
				);
				$this->save($team, 'team');
			}
		}
		//*/
	}


}
