<?php

declare(strict_types=1);

namespace App\Model\Cron;

class Cron {

    use CronPlayer;
	use CronArena;
	use CronHero;
	use CronFinance;

	/** @var \App\Model\Facades @inject */
	public $facades;

    public $kingdom;

	/**************************************************************************/

	public function __construct(
		\App\Model\Facades $facades
	) {
		$this->facades = $facades;
	}

	/**************************************************************************/

    public function setKingdom(int $kingdom) {
        $this->kingdom = $kingdom;
    }

	/**************************************************************************/

}
