<?php

declare(strict_types=1);

namespace App\Presenters;

use Nette;


final class HelpPresenter extends BasePresenter
{

	public function beforeRender() {
		$this->template->toleranceTable = $this->facades->getFacade('race')->getToleranceTable();
		$this->template->ageTable = $this->facades->getFacade('race')->getAgeTable();

	}

}
