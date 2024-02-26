<?php

namespace App\Controls;

class OnlinePlayersStats extends Base {

    /** @var \App\Model\Facades @inject */
	public $facades;

	/** @var string **/
	public $tpl = __DIR__ . '/template.latte';

	/**************************************************************************/

	public function __construct(
        \App\Model\Facades $facades
	) {
        $this->facades = $facades;
	}

	/**************************************************************************/

	public function beforeRender()
	{
		$facadeUser = $this->facades->getFacade('user');

		$this->template->statWeekGrow = $facadeUser->getWeekGrow();
		$this->template->statOnline = $facadeUser->getOnline();
		$this->template->statActive = $facadeUser->getActive();

		$this->template->rightOnline = $facadeUser->getOnlineNames();
	}

	/**************************************************************************/

}
