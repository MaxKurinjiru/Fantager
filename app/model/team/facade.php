<?php

declare(strict_types=1);

namespace App\Model\Facade;

use Nette;

final class Team extends DB
{
	/** @var string */
	public $table = 'team';

	/**************************************************************************/

	public static function changeMoney($teamRow, $diff) {
		return $teamRow->update(['money' => $teamRow->money + $diff]);
	}

	/**************************************************************************/

	public function playersInKingdom($kingdom = null) {
		$selection = $this->table()->where('user IS NOT NULL');
		if (!empty($kingdom)) {
			$selection->where(['kingdom' => $kingdom]);
		}

		return $selection;
	}

	/**************************************************************************/

	public function findByUser(int $id) {
		return $this->table()->where(array('user' => $id));
	}

	/**************************************************************************/

	public function checkNameExist(string $name) {
		$webalized = \Nette\Utils\Strings::webalize($name);
		$sel = $this->table()->where(array('webalized' => $webalized));

		return count( $sel ) > 0;
	}

	/**************************************************************************/

	// temporarily?
	public function webalizeNames() {
		foreach ($this->playersInKingdom() as $row) {
			$this->webalizeName($row);
		}
	}
	
	// temporarily?
	public function webalizeName(\Nette\Database\Table\ActiveRow $team) {
		$webalized = \Nette\Utils\Strings::webalize($team->name);
		$team->update([
			'webalized' => $webalized
		]);
	}

	/**************************************************************************/

	public function assignTeam(int $userID, int $kingdom) {
		if (!$teamRow = $this->table()
							->where('user IS NULL')
							->where(['kingdom' => $kingdom])
							->order('RAND()')
							->limit(1)
							->fetch()
		) {
			return false;
		}
		
		$teamRow->update(['user' => $userID]);

		$userRow = $teamRow->ref('user');

		$this->getFacade('activity')->saveActivity([
			'user' => $userID,
			'text' => 'system.message.team_assign',
			'vars' => \App\Helper\Format::setJson([
				'name' => $userRow->nickname
			])
		]);

		$this->getFacade('activity')->saveTeamUserHistory($userRow, $teamRow);

		$this->getFacade('inMail')->saveMail([
			'user' => $userID,
			'from' => 1,
			'title' => $this->translator->translate('system.user.team_assign_title'),
			'text' => $this->translator->translate('system.user.team_assign_text')
		]);
	}

	/**************************************************************************/

}
