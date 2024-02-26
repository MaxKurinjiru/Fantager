<?php

declare(strict_types=1);

namespace App\Model\Cron;

trait CronArena
{

	/**************************************************************************/

	// todo: kingdom lock
	public function arenaUpgrade() {
		$list = $this->facades->getFacade('arena')->getUpgradingArenas();

		foreach ($list as $row) {
			$this->facades->getFacade('arena')->addArenaProgressStep($row);
		}
	}

	/**************************************************************************/

	// todo: kingdom lock
	public function arenaRaceChange() {
		$list = $this->facades->getFacade('arena')->getRaceChangingArenas();

		foreach ($list as $row) {
			$this->facades->getFacade('arena')->arenaRaceChangeDone($row);
		}
	}

	/**************************************************************************/

	// todo: kingdom lock
	public function arenaResetJunior() {
		$list = $this->facades->getFacade('arena')->getJuniorsReset();

		foreach ($list as $row) {
			$this->facades->getFacade('arena')->resetJunior($row);
		}
	}

	/**************************************************************************/

}
