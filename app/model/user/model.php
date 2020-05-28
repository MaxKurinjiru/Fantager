<?php

declare(strict_types=1);

namespace App\Model;

use Nette;


final class User
{
  private $id;
  private $name;
	private $mail;


  public function id() {
    return $this->id;
  }
  public function name() {
    return $this->name;
  }
  public function mail() {
    return $this->mail;
  }

	public function set_id($a) {
    $this->id = $a;
  }
  public function set_name($a) {
    $this->name = $a;
  }
  public function set_mail($a) {
    $this->mail = $a;
  }


}
