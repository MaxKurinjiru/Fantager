<?php

namespace App\Model;

class Mailer {

	/** @var \Nette\Mail\IMailer @inject */
	public $mailer;

	/**************************************************************************/

	public function __construct(
		\Nette\Mail\IMailer $mailer
	) {
		$this->mailer = $mailer;
	}

	/**************************************************************************/

	public function createMailer() {
		return $this->mailer;
	}

	/**************************************************************************/

}
