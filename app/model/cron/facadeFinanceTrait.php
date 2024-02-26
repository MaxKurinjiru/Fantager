<?php

declare(strict_types=1);

namespace App\Model\Cron;

trait CronFinance
{

	/**************************************************************************/

	public function financeWeek() {

		$teams = [];
		$teams = $this->facades->getFacade('team')->playersInKingdom($this->kingdom);

		foreach ($teams as $teamRow) {
			$balance = 0;

			$arenaRow = $this->facades->getFacade('arena')->findByTeam($teamRow->id);

			/**
			* Vydaje
			*/
			// udrzba areny
			{
				$arenaPay = $this->facades->getFacade('arena')->getPayMaintenance($teamRow->id);
				$balance -= $arenaPay;

				$arenaPay = $arenaPay * -1;

				$this->facades->getFacade('activity')->saveFinance([
					'team' => $teamRow->id,
					'value' => $arenaPay,
					'action' => 'system.activity.finance_action.arena_maintenance'
				]);
			}

			// platy personálu
			{
				$staffPay = $this->facades->getFacade('arena')->getStaffSallary($arenaRow);
				$balance -= $staffPay;

				$staffPay = $staffPay * -1;

				$this->facades->getFacade('activity')->saveFinance([
					'team' => $teamRow->id,
					'value' => $staffPay,
					'action' => 'system.activity.finance_action.arena_personal'
				]);
			}

			// platy hrdinu
			{

			}

			// platy treneru
			{

			}

			// juniorka
			{

			}


			/**
			* Příjmy
			*/

			// vstupne
			{
				$arenaBonus = mt_rand(2000,6000);
				$arenaBonus = \App\Helper\Format::priceRandomizer($arenaBonus);
				$balance += $arenaBonus;

				$this->facades->getFacade('activity')->saveFinance([
					'team' => $teamRow->id,
					'value' => $arenaBonus,
					'action' => 'system.activity.finance_action.arena_ticket'
				]);
			}

			// kralovstvi
			{
				$kingdomBonus = mt_rand(2000,6000);
				$kingdomBonus = \App\Helper\Format::priceRandomizer($kingdomBonus);
				$balance += $kingdomBonus;

				$this->facades->getFacade('activity')->saveFinance([
					'team' => $teamRow->id,
					'value' => $kingdomBonus,
					'action' => 'system.activity.finance_action.gift_kingdom'
				]);

				// sponzori
				$sponsorBonus = mt_rand(2000,6000);
				$sponsorBonus = \App\Helper\Format::priceRandomizer($sponsorBonus);
				$balance += $sponsorBonus;

				$this->facades->getFacade('activity')->saveFinance([
					'team' => $teamRow->id,
					'value' => $sponsorBonus,
					'action' => 'system.activity.finance_action.gift_sponsor'
				]);
			}


			/**
			* Uroky
			*/
			{
				$newValue = $teamRow->money + $balance;

				if ($newValue > 0) {
					$bonusUrok = (int) floor($newValue * 0.005);
				} else {
					$bonusUrok = \App\Helper\Format::priceRandomizer(1000);
				}

				$balance += $bonusUrok;

				$this->facades->getFacade('activity')->saveFinance([
					'team' => $teamRow->id,
					'value' => $bonusUrok,
					'action' => 'system.activity.finance_action.interests'
				]);
			}


			// transakce
			\App\Model\Facade\Team::changeMoney($teamRow, $balance);

			// todo: translation
			$mail = [
				'user' => $teamRow->user,
				'from' => 1,
				'title' => 'Finance - týdenní přehled',
				'text' => '
					Příjmy a výdaje tento týden:
					<br>
					<br>Údržba arény: ' . \App\Helper\Format::formatMoney($arenaPay) . '
					<br>Platy personálu: ' . \App\Helper\Format::formatMoney($staffPay) . '
					<br>Příjmy za vstupenky: ' . \App\Helper\Format::formatMoney($arenaBonus) . '
					<br>Dary od království: ' . \App\Helper\Format::formatMoney($kingdomBonus) . '
					<br>Dary od sponzorů: ' . \App\Helper\Format::formatMoney($sponsorBonus) . '
					<br>Úroky: ' . \App\Helper\Format::formatMoney($bonusUrok) . '
					<br>
					<br>Celkem: ' . \App\Helper\Format::formatMoney($balance) . '
				'
			];

			$this->facades->getFacade('inMail')->saveMail($mail);
		}
	}

	/**************************************************************************/

	/**************************************************************************/

}
