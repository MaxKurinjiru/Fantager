<?php

declare(strict_types=1);

namespace App\Model\Facade;

trait ActivityBan
{
    /** @var string */
    public $tableBan = 'activity_ban';

	/**************************************************************************/

    public function tableBan() {
		return $this->table($this->tableBan);
	}

    /**************************************************************************/

    public function saveBan($userRow, $reason = '') {
		$userRow->update([
			'ban' => \App\Helper\Format::getTimestamp()
		]);

		$this->save([
			'user' => $userRow->id,
			'reason' => $reason
		], $this->tableBan);
	}

	/**************************************************************************/

}
