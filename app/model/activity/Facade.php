<?php

declare(strict_types=1);

namespace App\Model\Facade;

use Nette;

final class Activity extends DB
{
	/** @var string */
	public $table = 'activity';

	use ActivityFinance;
	use ActivityBan;
	use ActivityLogin;

	use HistoryAsign;

	/**************************************************************************/

    public function tableActivity() {
		return $this->table($this->table);
	}

    /**************************************************************************/

    public function saveActivity(array $data) {
		return $this->save($data, $this->table);
	}

	/**************************************************************************/

}
