<?php

declare(strict_types=1);

namespace App\Controls;

class LeagueTable extends Base {

	/** @var \App\Model\Facades @inject */
	public $facades;

	public $leagueID;

	/** @var string **/
	public $tpl = __DIR__ . '/template.latte';

	/**************************************************************************/

	public function __construct(
		\App\Model\Facades $facades,
		int $id = 0
	) {
		$this->facades = $facades;
		$this->leagueID = $id;
	}

	/**************************************************************************/

	public function beforeRender() {
		$leagueRow = $this->facades->getFacade('league')->findById($this->leagueID);
		$leagueTeams = $this->facades->getFacade('league')->findTeamsInLeague($this->leagueID);

		$this->template->league = $leagueRow;
		$this->template->table = $leagueTeams;
		$this->template->config = $this->getConfig();
	}

	/**************************************************************************/

}
