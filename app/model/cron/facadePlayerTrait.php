<?php

declare(strict_types=1);

namespace App\Model\Cron;

trait CronPlayer
{

    /**************************************************************************/

	public function checkInactivePlayers() {
		// todo:
		// activity logs

		// 5 weeks no action
		// week without activation
		$users = $this->facades->getFacade('user')->playersInKingdom($this->kingdom)
			->where('
				(DATE(created) <= DATE( NOW() - INTERVAL 7 DAY ) AND activated IS NULL)
				OR
				(DATE(last_action) <= DATE( NOW() - INTERVAL 35 DAY ))
			');

		// main action to inactive
		/*
		foreach ($users as $userRow) {
			//$userRow->update(['password_token' => 'delete']);
		}
		*/
	}

	/**************************************************************************/

	public function checkInsolventPlayers() {
		// todo:
		// activity logs

		// teams with balance lower insolvent limit or with insolvent count
		$teams = $this->facades->getFacade('team')->playersInKingdom($this->kingdom)->where('money <= -20000 OR insolvent IS NOT NULL');

		foreach ($teams as $teamRow) {
			if ($teamRow->money >= -20000) {
				// not insolvent now
				$teamRow->update(['insolvent' => null]);
				continue;
			}

			if (empty($teamRow->insolvent)) {
				// new insolvent
				$teamRow->update(['insolvent' => 1]);
				continue;
			}

			$daysOff = $teamRow->insolvent + 1;

			if ($daysOff >= 21) {
				// remove team from player
				/*
				$teamRow->update(['insolvent' => null, 'user' => null]);
				continue;
				*/
			}

			$teamRow->update(['insolvent' => $daysOff]);
		}
	}

	/**************************************************************************/

}
