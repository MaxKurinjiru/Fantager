<?php

namespace App\Controls\Arena;

class Staff extends \App\Controls\Base {

    /** @var \App\Model\Facades @inject */
	public $facades;

	/** @var string **/
	public $tpl = __DIR__ . '/template.latte';

	public $arenaRow;

	/**************************************************************************/

	public function setArenaRow($arenaRow) {
		$this->arenaRow = $arenaRow;
		return $this;
	}
	
	/**************************************************************************/

	public function __construct(
        \App\Model\Facades $facades
	) {
        $this->facades = $facades;
	}

	/**************************************************************************/

	public function handleStaff() {
		$staff = $this->getParameter('staff');
		$count = $this->getParameter('count');

		if ( $this->facades->getFacade('arena')->changeArenaStaff($this->arenaRow, $staff, (int) $count) ) {
			//$this->flashMessage('Změna personálu provedena');
		}

		$this->presenter->redirect('Arena:staff');
	}

	/**************************************************************************/

	public function beforeRender()
	{
		$this->template->arenaRow = $this->arenaRow;
		$this->template->arenaStaffSalary = $this->facades->getFacade('arena')->getStaffSallary($this->arenaRow);
	}

	/**************************************************************************/

}
