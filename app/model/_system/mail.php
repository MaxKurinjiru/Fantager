<?php

declare(strict_types=1);

namespace App\Model;

use Nette;

class Mail
{

	/** @var \App\Model\Mailer @inject */
	public $modelMailer;

	/** @var \App\Helper\Config @inject */
	public $cfg;

	/** @var array **/
	public $config;

	/**************************************************************************/

	public function __construct(
		\App\Model\Mailer $modelMailer,
		\App\Helper\Config $cfg
	) {
		$this->modelMailer = $modelMailer;

		$this->cfg = $cfg;
		$this->config = $this->cfg->getConfig();
	}

	/**************************************************************************/

	public function setFrom(Nette\Mail\Message $mail): Nette\Mail\Message {
		$mail->setFrom(
			$this->config['mailer']['email'],
			$this->config['mailer']['name']
		);
		return $mail;
	}

	public function send(Nette\Mail\Message $mail) {
		$mailer   = $this->modelMailer->createMailer();

		$mail = $this->setFrom($mail);

		try {
			$mailer->send($mail);
		} catch (\Nette\Mail\SmtpException $e) {
			\Tracy\Debugger::barDump('error sending mail');
		}
	}

	public function createEmail(string $body, string $title): Nette\Mail\Message {

		$html = $this->getMailContainer($body, $title);

		$mail = new Nette\Mail\Message;
		$mail->setSubject($title);
		$mail->setHtmlBody($html);

		return $mail;
	}

	/**************************************************************************/

	public function getMailContainer(string $body, string $title = '') {
		$mailContainer = "
			<html>
			<head>
				<meta charset='utf-8'>
				<title>" . $title . "</title>
				<style>
					/* latin-ext */
					@font-face {
						font-family: 'Eagle Lake';
						font-style: normal;
						font-weight: 400;
						font-display: swap;
						src: url(https://fonts.gstatic.com/s/eaglelake/v20/ptRMTiqbbuNJDOiKj9wG1Of4KDNu.woff2) format('woff2');
						unicode-range: U+0100-024F, U+0259, U+1E00-1EFF, U+2020, U+20A0-20AB, U+20AD-20CF, U+2113, U+2C60-2C7F, U+A720-A7FF;
					}
					/* latin */
					@font-face {
						font-family: 'Eagle Lake';
						font-style: normal;
						font-weight: 400;
						font-display: swap;
						src: url(https://fonts.gstatic.com/s/eaglelake/v20/ptRMTiqbbuNJDOiKj9wG1On4KA.woff2) format('woff2');
						unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+2000-206F, U+2074, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;
					}
					/* latin-ext */
					@font-face {
						font-family: 'Rosarivo';
						font-style: normal;
						font-weight: 400;
						font-display: swap;
						src: url(https://fonts.gstatic.com/s/rosarivo/v20/PlI-Fl2lO6N9f8HaNDeL0Hl8nA.woff2) format('woff2');
						unicode-range: U+0100-024F, U+0259, U+1E00-1EFF, U+2020, U+20A0-20AB, U+20AD-20CF, U+2113, U+2C60-2C7F, U+A720-A7FF;
					}
					/* latin */
					@font-face {
						font-family: 'Rosarivo';
						font-style: normal;
						font-weight: 400;
						font-display: swap;
						src: url(https://fonts.gstatic.com/s/rosarivo/v20/PlI-Fl2lO6N9f8HaNDeF0Hk.woff2) format('woff2');
						unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+2000-206F, U+2074, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;
					}

					body {
						font-size: 16px;
						font-family: 'Rosarivo', serif;
						color: rgb(240,234,210);
						box-sizing: border-box;
					}

					* {
						font-size: 16px;
						font-family: 'Rosarivo', serif;
						color: rgb(240,234,210);
						box-sizing: border-box;
						line-height: 150%;
					}
					.font-uniq {
						font-family: 'Eagle Lake', cursive;
						color: rgb(173,193,120);
					}
					a {
						color: rgb(173,193,120)!important;
						font-weight: 600!important;
					}
					a:hover {
						color: rgb(173,193,120)!important;
						text-decoration: underline;
					}
					h3 {
						margin-top: 0;
					}
					p {
						margin-top: 25px;
						margin-bottom: 25px;
					}
					.small {
						font-size: 13px;
					}
				</style>
			</head>
			<body>
				<div style='width: 100%; background: rgb(104,79,59); padding: 30px; padding-bottom: 100px;'>
					<div style='width: 100%; max-width: 1200px; padding: 40px; background: rgb(24,20,17); border: 1px solid rgb(173,193,120); border-radius: 5px;' >
						" . $body . "
					</div>
				</div>
			</body>
			</html>
		";

		return $mailContainer;
	}

	/**************************************************************************/

}
