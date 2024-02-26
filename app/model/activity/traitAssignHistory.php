<?php

declare(strict_types=1);

namespace App\Model\Facade;

trait HistoryAsign
{
    /** @var string */
    public $tableTeamUserHistory = 'activity_assign_team';

	/** @var string */
    public $tableTeamCharHistory = 'activity_assign_character';

	/**************************************************************************/

    public function tableTeamUserHistory() {
		return $this->table($this->tableTeamUserHistory);
	}

	public function tableTeamCharHistory() {
		return $this->table($this->tableTeamCharHistory);
	}

    /**************************************************************************/

    public function saveTeamUserHistory($userRow, $teamRow, $retire = false)
	{
		if ($retire && 
			$row = $this->tableTeamUserHistory()->where([
				'user' => $userRow->id,
				'team' => $teamRow->id,
			])->order('id DESC')->limit(1)->fetch()
		) {
			$row->update([
				'retired' => \App\Helper\Format::getTimestamp()
			]);
			return;
		}

		$this->save([
			'user' => $userRow->id,
			'team' => $teamRow->id,
			'assigned' => \App\Helper\Format::getTimestamp()
		], $this->tableTeamUserHistory);
	}

	/**************************************************************************/

	public function saveTeamCharHistory($charRow, $teamRow, $retire = false, $trained = false)
	{
		if ($retire && 
			$row = $this->tableTeamCharHistory()->where([
				'character' => $charRow->id,
				'team' => $teamRow->id,
			])->order('id DESC')->limit(1)->fetch()
		) {
			$row->update([
				'retired' => \App\Helper\Format::getTimestamp()
			]);
			return;
		}

		$this->save([
			'character' => $charRow->id,
			'team' => $teamRow->id,
			'assigned' => \App\Helper\Format::getTimestamp(),
			'trained' => $trained ? 1 : 0
		], $this->tableTeamCharHistory);
	}

	/**************************************************************************/

}
