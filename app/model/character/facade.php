<?php

declare(strict_types=1);

namespace App\Model\Facade;

use Nette;

final class Character extends DB
{
	use JuniorCharacter;

	/** @var string */
	public $table = 'character';

	/**************************************************************************/

	public function findAllByTeam($teamID) {
		return $this->table()->where(':team_character.team', $teamID);
	}

	/**************************************************************************/

	

	/**************************************************************************/

	// todo
	public function checkTeamCharacterLimit() {

	}

	/**************************************************************************/

	public function assignCharacter($charRow, $teamRow, $retire = false, $trained = false) {

		$this->getFacade('activity')->saveTeamCharHistory($charRow, $teamRow, $retire, $trained);
		
		$this->save([
			'team' => $teamRow->id,
			'character' => $charRow->id,
			'trained' => $trained ? 1 : 0
		], 'team_character');
	}

	/**************************************************************************/

	public function getRelative() {
		return $this->table();
	}

	/**************************************************************************/

}
