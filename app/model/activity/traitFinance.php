<?php

declare(strict_types=1);

namespace App\Model\Facade;

trait ActivityFinance
{
    /** @var string */
    public $tableFinance = 'activity_finance';

	/**************************************************************************/

    public function tableFinance() {
		return $this->table($this->tableFinance);
	}

    /**************************************************************************/

    public function saveFinance(array $data) {
        if ( empty($data['value']) ) {
			return;
		}
		return $this->save($data, $this->tableFinance);
	}

	/**************************************************************************/

	public function getFinanceHistoryLastByTeam($teamID) {
		return $this->tableFinance()
			->where('team = ? AND DATE(time) >= DATE( NOW() - INTERVAL 7 DAY )', $teamID)
			->order('id DESC')
			->limit(20)
			->fetchAll();
	}

	/**************************************************************************/

}
