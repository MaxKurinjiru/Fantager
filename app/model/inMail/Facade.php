<?php

declare(strict_types=1);

namespace App\Model\Facade;

use Nette;

final class InMail extends DB
{
	/** @var string */
	public $table = 'mail';

	/**************************************************************************/

	public function saveMail(array $data) {
		$mailRow = $this->save($data);

		// pokud to někdo povolí xD
		//$this->sendEmailMail($mailRow);

		return $mailRow;
	}

	/**************************************************************************/

	public function hasNewMails(int $user) {
		$myMails = $this->table()->where([
			'user' => $user,
			'read' => 0
		]);

		if ( $myMails ) {
			return count($myMails);
		}
		return 0;
	}

	/**************************************************************************/

	public function setMailReaded(int $user, int $from) {
		$myMails = $this->table()->where([
			'user' => $user,
			'from'=> $from,
			'read' => 0
		]);
		if ($myMails) {
			$myMails->update([
				'read' => 1
			]);
		}
	}

	/**************************************************************************/

	public function getMailList(int $user) {
		$myMails = $this->table()
			->where('user IS NOT NULL AND from IS NOT NULL AND user ?', $user)
			->order('id DESC');

		$array = [];
		foreach ($myMails as $row) {
			$mailRow = $row->toArray();
			$mailRow['nickname'] = $row->ref('from')->nickname;

			$array[] = $mailRow;
		}

		return $array;
	}

	/**************************************************************************/

	public function getMails(int $user) {
		$myMails = $this->table()
			->where('user IS NOT NULL AND from IS NOT NULL AND (user ? OR from ?)', [$user, $user])
			->order('id DESC');

		$array = [];

		foreach ($myMails as $row) {
			$otherUser = $row->user != $user ? $row->user : $row->from;

			$array[$otherUser]['list'][] = $row;

			if (empty($array[$otherUser]['unread'])) {
				$array[$otherUser]['unread'] = 0;
			}

			if (empty($row->read) && $row->user == $user) {
				$array[$otherUser]['unread'] += 1;
			}

			if ( empty($array[$otherUser]['nickname']) ) {
				$otherUserRow = $row->user != $user ? $row->ref('user') : $row->ref('from');
				$array[$otherUser]['nickname'] = $otherUserRow->nickname;
				$array[$otherUser]['id'] = $otherUserRow->id;
			}
		}

		return $array;
	}

	/**************************************************************************/

	public function sendEmailMail($mailRow) {
		$urlRoot = \App\Helper\Config::getFullURL();
		$urlRootNice = \App\Helper\Config::getNiceFullURL();

		$title = $mailRow->title;
		$text = $mailRow->text;

		$from = $mailRow->ref('from')->nickname;
		$to = $mailRow->ref('user')->email;

		// translate by target user language... ???
		$newMailTitle = $this->translator->translate('system.mail.mail_notif_new_mail_title');

		$body = '
			<h3 class="font-uniq">' . $newMailTitle . '</h3>
			<h5>[' . $from . '] ' . $title . '</h5>
			<p></p>
			<p>' . $text . '</p>
			<p>
				<br><br>
			</p>
			<p>
				<a href="' . $urlRoot . ' " target="_blank"> '. $urlRootNice .' </a>
			</p>
		';

		$mail = $this->modelMail->createEmail(
			$body,
			$newMailTitle
		);

		$mail->addTo($to);

		$this->modelMail->send($mail);

	}

	/**************************************************************************/

}
