<?php

declare(strict_types=1);

namespace App\Presenters;

use Nette;

final class MailPresenter extends OnlinePresenter
{
	/** @var \App\Model\Facades @inject */
	public $facades;

	/** @var \App\Controls\Form\Message\Factory @inject */
	public $messageFactory;

	/**************************************************************************/

	public function createComponentNewForm() {

		$newForm = $this->messageFactory->create($this->userRow->kingdom);

		$self = $this;

		$newForm->onSuccess[] = function($cmp, $form, $values) use ($self) {

			$self->redirect('Mail:default');

		};

		return $newForm;
	}

	/**************************************************************************/

	public function actionDefault() {

		$mails = $this->facades->getFacade('inMail')->getMails($this->userRow->id);
		$this->template->mailList = $mails;


		if ( $read = $this->getParameter('id') ) {
			$read = (int) $read;
		} else {
			foreach ($mails as $userID => $mail) {
				$read = (int) $userID;
				break;
			}
		}

		$this->template->read = $read;

		if ($mails[$read]['unread'] > 0) {
			$this->facades->getFacade('inMail')->setMailReaded($this->userRow->id, $read);
		}

		if ( $write = $this->getParameter('write') ) {
			$this->template->write = $write;
		}
	}

	/**************************************************************************/

	public function actionNew() {
		$this->actionDefault();
	}

	/**************************************************************************/

}
