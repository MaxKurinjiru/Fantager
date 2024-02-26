<?php

namespace App\Filters;

class BSicon {

	use \Nette\SmartObject;

	public function __invoke($arg) {
		return '<use xlink:href="/images/bootstrap-icons.svg#' . $arg . '"/>';
	}

}
