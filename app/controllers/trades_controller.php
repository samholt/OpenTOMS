<?php
/*
	OpenTOMS - Open Trade Order Management System
	Copyright (C) 2012  JOHN TAM, LPR CONSULTING LLP

	This program is free software: you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

class TradesController extends AppController {
	var $name = 'Trades';
	var $funds = array();
	
	function index($pass = null) {
		$this->paginate = array('conditions' => array('TradeType.category =' => 'Trading'),
								'fields' => array('Trade.id','Trade.oid','Fund.fund_name','Sec.sec_name','TradeType.trade_type','Reason.reason_desc','Broker.broker_name',
														'Trader.trader_name','Currency.currency_iso_code','Trade.quantity','Trade.consideration','Trade.trade_date','Trade.settlement_date',
														'Trade.execution_price','Trade.cancelled','Trade.executed','Trade.order_quantity'),
								'limit' => 1000,
								'order' => 	array('Trade.id' => 'desc')
		);
	
		$this->setchoices();
		$conditions=array(
			'Trade.act =' => 1
		);
		
		
		//to date
		if (isset($this->params['url']['to_date'])) {
			 $to_date = $this->params['url']['to_date'];
			 $this->Session->write('to_date', $to_date);
		}
		else if ($this->Session->check('to_date')) {
			$to_date = $this->Session->read('to_date');
		}
		else {
			$to_date = date('Y-m-d');
		}
		
		//from date
		if (isset($this->params['url']['from_date'])) {
			 $from_date = $this->params['url']['from_date'];
			 $this->Session->write('from_date', $from_date);
		}
		else if ($this->Session->check('from_date')) {
			$from_date = $this->Session->read('from_date');
		}
		else {
			$from_date = date('Y-m-d', strtotime('-1 week'));
		}
		
		//check that the from date isn't later than the to date, if so then make them both equal to the to date
		if (strtotime($from_date) > strtotime($to_date)) {
			$from_date = $to_date;
		}
		
		//fund dropdown
		if (isset($this->params['url']['fundchosen'])) {
			 $fundchosen = $this->params['url']['fundchosen'];
			 if (!empty($fundchosen)) {
				$this->Session->write('fund_chosen', $fundchosen);
			 }
			 else {
				$this->Session->delete('fund_chosen');
			 }
		}
		else if ($this->Session->check('fund_chosen')) {
			$fundchosen = $this->Session->read('fund_chosen');
		}
		else {
			$fundchosen = null;
		}
		
		//broker dropdown
		if (isset($this->params['url']['brokerchosen'])) {
			 $brokerchosen = $this->params['url']['brokerchosen'];
			 $this->Session->write('trades_sort_brokerchosen', $brokerchosen);
		}
		else if ($this->Session->check('trades_sort_brokerchosen')) {
			$brokerchosen = $this->Session->read('trades_sort_brokerchosen');
		}
		else {
			$brokerchosen = null;
		}
		
		//security chosen
		if (isset($this->params['url']['secchosen'])) {
			 $secchosen = $this->params['url']['secchosen'];
			 $this->Session->write('trades_sort_secchosen', $secchosen);
		}
		else if ($this->Session->check('trades_sort_secchosen')) {
			$secchosen = $this->Session->read('trades_sort_secchosen');
		}
		else {
			$secchosen = null;
		}
		
		//oid
		if (isset($this->params['url']['oid'])) {
			 $oid = $this->params['url']['oid'];
			 $this->Session->write('trades_sort_oid', $oid);
		}
		else if ($this->Session->check('trades_sort_oid')) {
			$oid = $this->Session->read('trades_sort_oid');
		}
		else {
			$oid = null;
		}
		
		
		//add extra conditions to sql query
		if ($oid) {
			$conditions['Trade.oid ='] = $oid;
			$fundchosen = null;
			$brokerchosen = null;
			$secchosen = null;
		}
		else {
			$conditions['Trade.trade_date >='] = $from_date;
			$conditions['Trade.trade_date <='] = $to_date;
			if ($fundchosen) {
				$conditions['Trade.fund_id ='] = $fundchosen;
			}
			if ($brokerchosen) {
				$conditions['Trade.broker_id ='] = $brokerchosen;
			}
			if ($secchosen) {
				$conditions['Trade.sec_id ='] = $secchosen;
			}
		}
		
		///////////////////
		//! N.B. VERY IMPORTANT
		//restrict trades to the funds that the user is allowed to see
		$userdata = $this->Session->read("Auth.User");
		if ($userdata['group_id'] > 4) {	//Administrator group id = 4
			App::import('model','GroupPermission');
			$gp = new GroupPermission();
			$allowed = $gp->getAllowedFunds($userdata['group_id']);
			$f = array();
			foreach ($allowed as $al) {
				array_push($f, array('Trade.fund_id =' => $al));
			}
			$conditions['OR'] = $f;	
		}
		/////////////////////
		
		//get data from the model		
		$data = $this->paginate('Trade', $conditions);
		
		if (!isset($this->params['url']['Submit_x'])) {	//filter button pressed
			$this->set('trades', $data);
			$this->set('title_for_layout', 'View Trades');
			$this->set('filter', array($to_date,$from_date,$fundchosen,$brokerchosen, $secchosen, $oid));
		}
		else {
			//prepare data for output to csv file
			$out = array();
			$row = array();
			foreach ($data as $d) {
				$row = array(
								$d['Trade']['id'],
								$d['Trade']['oid'],
								$d['Fund']['fund_name'],
								$d['Sec']['sec_name'],
								$d['TradeType']['trade_type'],
								$d['Reason']['reason_desc'],
								$d['Broker']['broker_name'],
								$d['Trader']['trader_name'],
								$d['Currency']['currency_iso_code'],
								$d['Trade']['quantity'],
								$d['Trade']['consideration'],
								$d['Trade']['trade_date'],
								$d['Trade']['settlement_date'],
								$d['Trade']['execution_price'],
								$d['Trade']['cancelled'],
								$d['Trade']['executed']
							);
				array_push($out, $row);
			}
			array_unshift($out, array('Id','oid','Fund','Security Name','Trade Type','Reason','Broker','Trader','Currency','Quantity','Consideration','Trade Date','Settlement Date','Execution Price','Cancelled','Executed')); //headers
		
			Configure::write('debug',0);
			$this->layout = 'csv';
			$this->set('data',$out);
			$this->render('/trades/export');
		}
	}
	
	
	function add() {
		$this->setchoices();
	
		if (!empty($this->data)) {	
			//remove any commas from quantity, consideration and notional value
			$this->data['Trade']['quantity'] = str_replace(',','',$this->data['Trade']['quantity']);
			$this->data['Trade']['consideration'] = str_replace(',','',$this->data['Trade']['consideration']);
			$this->data['Trade']['notional_value'] = str_replace(',','',$this->data['Trade']['notional_value']);
			$this->data['Trade']['accrued'] = str_replace(',','',$this->data['Trade']['accrued']);
			
			//case of dividend and coupon income
			$type = $this->Trade->TradeType->read('trade_type', $this->data['Trade']['trade_type_id']);
			$type = $type['TradeType']['trade_type'];
			if ((substr($type,0,4) == 'Coup') || (substr($type,0,4) == 'Divi')) {
				if (stripos($type,'income') > 0) {
					$sign = 1;
				}
				else {
					$sign = -1;
				}
				$this->data['Trade']['execution_price'] = 1;
				$this->data['Trade']['executed'] = 1;
				$this->data['Trade']['reason_id'] = 999;	//undefined
				$this->data['Trade']['broker_id'] = 35;	//undefined
				$this->data['Trade']['quantity'] = $sign * abs($this->data['Trade']['quantity']);
				$this->data['Trade']['consideration'] = $sign * abs($this->data['Trade']['quantity']);
			}
			
			//put in trade date and settlement date
			if (empty($this->data['Trade']['trade_date_input'])) {
				$this->data['Trade']['trade_date'] = Date('Y-m-d');
			}
			else {
				$this->data['Trade']['trade_date'] = $this->data['Trade']['trade_date_input'];
			}
			$this->data['Trade']['settlement_date'] = $this->data['Trade']['settlement_date_input'];
			$this->data['Trade']['decision_time'] = $this->data['Trade']['decision_time_date'].' '.$this->data['Trade']['decision_time_time'];
			$this->data['Trade']['order_time'] = $this->data['Trade']['order_time_date'].' '.$this->data['Trade']['order_time_time'];
			
			if ($this->Trade->save($this->data)) {
				//Do a second update to the same record to set the oid and act fields
				$id = $this->Trade->id;
				if ($this->Trade->saveField('act',1) && $this->Trade->saveField('oid',$id)) {
					$this->update_report_table();
					$this->Session->setFlash('Your trade has been saved.');
					//$this->disableCache();	//clear cache for AJAX calls
					$this->redirect(array('action' => 'add'));
				}
			}
			
		}
	}
	
	
	function copy($id) {
		$this->setchoices();
		
		if (isset($this->params['data']['Trade'])) {
			//remove any commas from quantity, consideration and notional value
			$this->params['data']['Trade']['quantity'] = str_replace(',','',$this->params['data']['Trade']['quantity']);
			$this->params['data']['Trade']['consideration'] = str_replace(',','',$this->params['data']['Trade']['consideration']);
			$this->params['data']['Trade']['notional_value'] = str_replace(',','',$this->params['data']['Trade']['notional_value']);
			$this->params['data']['Trade']['accrued'] = str_replace(',','',$this->params['data']['Trade']['accrued']);
		
			//put in trade date and settlement date
			$this->params['data']['Trade']['trade_date'] = $this->params['data']['Trade']['trade_date_input'];
			$this->params['data']['Trade']['settlement_date'] = $this->params['data']['Trade']['settlement_date_input'];
			
			unset($this->params['data']['Trade']['id']);	//remove id so that Cake will create a new model record
			$this->params['data']['Trade']['act'] = 1;
			$this->params['data']['Trade']['crd'] = DboSource::expression('NOW()');	//weird DEFAULT TIMESTAMP not working
			$this->Trade->create();
			
			if ($this->Trade->save($this->params['data'])) {
				//Do a second update to the same record to set the oid field
				$id = $this->Trade->id;
				$this->Trade->create();
				$this->Trade->read(null,$id);
				$this->Trade->set(array(
					'oid' => $id
				));
				
				if ($this->Trade->save()) {
					$this->update_report_table();
					$this->Session->setFlash('Your trade has been saved.');
					//$this->disableCache();	//clear cache for AJAX calls
					$this->redirect(array('action' => 'index'));
				}
			}
		}
		else {
			$this->Trade->id = $id;
			$this->data = $this->Trade->read();
			$this->Session->setFlash('Copy trade ID='.$id);
			$this->render('add');
		}
	}
	
	
	
	function edit($id = null) {
		$this->setchoices();
		$this->Trade->id = $id;
		
		if (empty($this->data)) {
			$this->data = $this->Trade->read();
			
			//replace execution quantity and price with order quantity and price if zero
			if ($this->data['Trade']['quantity'] == 0) {
				$this->data['Trade']['quantity'] = $this->data['Trade']['order_quantity'];
			}
			if ($this->data['Trade']['execution_price'] == 0) {
				$this->data['Trade']['execution_price'] = $this->data['Trade']['price'];
			}
			
			//populate trade date and settlement date
			$this->data['Trade']['trade_date_input'] = $this->data['Trade']['trade_date'];
			$this->data['Trade']['settlement_date_input'] = $this->data['Trade']['settlement_date'];
			$this->data['Trade']['decision_time_date'] = date('Y-m-d', strtotime($this->data['Trade']['decision_time']));
			$this->data['Trade']['decision_time_time'] = date('H:i', strtotime($this->data['Trade']['decision_time']));
			$this->data['Trade']['order_time_date'] = date('Y-m-d', strtotime($this->data['Trade']['order_time']));
			$this->data['Trade']['order_time_time'] = date('H:i', strtotime($this->data['Trade']['order_time']));
			
		} else {
			//remove any commas from quantity, consideration and notional value
			$this->data['Trade']['quantity'] = str_replace(',','',$this->data['Trade']['quantity']);
			$this->data['Trade']['consideration'] = str_replace(',','',$this->data['Trade']['consideration']);
			$this->data['Trade']['notional_value'] = str_replace(',','',$this->data['Trade']['notional_value']);
			$this->data['Trade']['accrued'] = str_replace(',','',$this->data['Trade']['accrued']);
		
			//put in trade date and settlement date
			$this->data['Trade']['trade_date'] = $this->data['Trade']['trade_date_input'];
			$this->data['Trade']['settlement_date'] = $this->data['Trade']['settlement_date_input'];
			$this->data['Trade']['decision_time'] = $this->data['Trade']['decision_time_date'].' '.$this->data['Trade']['decision_time_time'];
			$this->data['Trade']['order_time'] = $this->data['Trade']['order_time_date'].' '.$this->data['Trade']['order_time_time'];
			
			//is this an ammendment to an order or execution of this order
			if ($this->params['form']['Submit'] == 'Update') {
				//write order and price back to the order fields and reset the execution fields back to zero
				$this->data['Trade']['order_quantity'] = $this->data['Trade']['quantity'];
				$this->data['Trade']['price'] = $this->data['Trade']['execution_price'];
				$this->data['Trade']['quantity'] = 0;
				$this->data['Trade']['execution_price'] = 0;
			}
			else {
				$this->data['Trade']['executed'] = 1;
				$executed = true;
			}
			
			//first try to deactive this current trades, if it doesn't succeed, then don't create a new trade, this has been a persistent bug
			$oid = $this->data['Trade']['oid'];
			$result = $this->Trade->updateAll(array('Trade.act' => 0,
													'Trade.cancelled' => 1), 
											  array('Trade.oid =' => $oid));
			
			//if successful, then go on to create a new trade with these details, else report an error
			if ($result) {
				unset($this->data['Trade']['id']);	//remove id so that Cake will create a new model record
				
				$this->Trade->create();
				$this->data['Trade']['act'] = 1;
				$this->data['Trade']['crd'] = DboSource::expression('NOW()');	//weird DEFAULT TIMESTAMP not working
			
				if ($this->Trade->save($this->data)) {
					$this->update_report_table();
					
					//need to create a balance order?
					if (($this->data['Trade']['create_balance'] == 1) && isset($executed)) {
						$balance_quantity = $this->data['Trade']['order_quantity'] - $this->data['Trade']['quantity'];
						$orig_id = $this->data['Trade']['oid'];
					
						unset($this->data['Trade']['id']);
						$this->Trade->create();
						$this->data['Trade']['order_quantity'] = $balance_quantity;
						$this->data['Trade']['price'] = $this->data['Trade']['execution_price'];
						$this->data['Trade']['quantity'] = 0;
						$this->data['Trade']['execution_price'] = 0;
						$this->data['Trade']['executed'] = 0;
						$this->data['Trade']['commission'] = 0;
						$this->data['Trade']['tax'] = 0;
						$this->data['Trade']['other_costs'] = 0;
						$this->data['Trade']['consideration'] = 0;
						$this->data['Trade']['notional_value'] = 0;
						$this->data['Trade']['act'] = 1;
						$this->data['Trade']['crd'] = DboSource::expression('NOW()');	//weird DEFAULT TIMESTAMP not working
						$this->data['Trade']['notes'] = "Linked trade:".$orig_id;
						
						if ($this->Trade->save($this->data)) {
							//Do a second update to the same record to set the oid field
							$thisid = $this->Trade->id;
							$this->Trade->create();
							$this->Trade->read(null,$thisid);
							$this->Trade->set(array(
								'oid' => $thisid
							));
							
							if ($this->Trade->save()) {
								$this->Session->setFlash('Partial fill has been executed and new balance order created');
								$this->redirect(array('action' => 'index'));
							}
						}
					}
					
					$this->Session->setFlash('Your order has been updated');
					$this->redirect(array('action' => 'index'));
				}
				else {
					$this->Session->setFlash('Problem with adding the new edited trade to the database. Please try again. (The previous one has already been cancelled)');
					$this->redirect(array('action' => 'edit', $id));
				}
			}
			else {
				$this->Session->setFlash('Could not deactivate trade id='.$id.' below. Edit operation aborted.');
				$this->redirect(array('action' => 'edit', $id));
			}
		}
		
	}
	
	//If a trade has been added or changed, then deactivate any reports which have a run_date on or after the trade date of this trade.
	//This is to make sure that any future run reports do not depend on these saved reports which could now be invalid.
	function update_report_table() {
		/*
		App::import('model','Report');
		$report = new Report();
		$report->run_date = date('Y-m-d',mktime(0,0,0,$this->data['Trade']['trade_date']['month'],$this->data['Trade']['trade_date']['day'],$this->data['Trade']['trade_date']['year']));
		$report->fund_id = $this->data['Trade']['fund_id'];
		$report->deactivate();
		*/
	}
	
	
	function view($oid = null) {
		$conditions=array(
			'Trade.oid =' => $oid
		);
	
		$params=array(
			'conditions' => $conditions, //array of conditions
			'limit' => 1,
			'order' => array('Trade.crd DESC') //string or array defining order
		);
			
		$this->paginate = $params;
		$data=$this->paginate('Trade');
		$this->set(compact('data'));
	}
	
	
	//this function handles the case when we don't immediately know if the trade id we are passing is either an ordinary trade ID or an OID
	function viewSafe($id) {
		$oid = $this->Trade->read('oid', $id);
		$oid = $oid['Trade']['oid'];
		if ($oid <> $id) {
			$this->redirect(array('action' => 'view', $oid));
		}
		else {
			$this->redirect(array('action' => 'view', $id));
		}
	}
	
	
	function order() {
		$this->setchoices();
		$this->set('tradeTypes', array(1=>'Buy Long', 2=>'Buy Short', 3=>'Sell Long', 4=>'Sell Short'));
	
		if (!empty($this->data)) {	
			//remove any commas from quantity, consideration and notional value
			$this->data['Trade']['order_quantity'] = str_replace(',','',$this->data['Trade']['order_qty']);
			$this->data['Trade']['price'] = str_replace(',','',$this->data['Trade']['order_price']);
			$this->data['Trade']['quantity'] = 0;	//non null field
			
			$this->data['Trade']['trade_date'] = $this->data['Trade']['trade_date_input'];
			$this->data['Trade']['decision_time'] = $this->data['Trade']['decision_time_date'].' '.$this->data['Trade']['decision_time_time'];
			$this->data['Trade']['order_time'] = $this->data['Trade']['order_time_date'].' '.$this->data['Trade']['order_time_time'];			
			
			if ($this->Trade->save($this->data)) {
				//Do a second update to the same record to set the oid and act fields
				$id = $this->Trade->id;
				if ($this->Trade->saveField('act',1) && $this->Trade->saveField('oid',$id)) {
					$this->update_report_table();
					$this->Session->setFlash('Your order has been saved.');
					$this->redirect(array('action' => 'order'));
				}
			}
			
		}
	}
	
	
	function setchoices() {
		//Could be a lot of securities so cache this list
		if (($secsCACHE = Cache::read('secs')) === false) {
			$secsCACHE = $this->Trade->Sec->find('list', array('fields'=>array('Sec.sec_name'),'order'=>array('Sec.sec_name'),'conditions'=>array('Sec.act =' => 1)));
			Cache::write('secs', $secsCACHE);
		}

		$this->set('secs', $secsCACHE);
		$this->set('funds', $this->Trade->Fund->find('list', array('fields'=>array('Fund.fund_name'),'order'=>array('Fund.fund_name'))));
		$this->set('tradeTypes', $this->Trade->TradeType->find('list', array('fields'=>array('TradeType.trade_type'),'conditions'=>array('TradeType.category ='=>'Trading'),'order'=>array('TradeType.id'))));
		$this->set('reasons', $this->Trade->Reason->find('list', array('fields'=>array('Reason.reason_desc'),'order'=>array('Reason.reason_desc'))));
		$this->set('brokers', $this->Trade->Broker->find('list', array('fields'=>array('Broker.broker_name'),'order'=>array('Broker.broker_name'))));
		$this->set('traders', $this->Trade->Trader->find('list', array('fields'=>array('Trader.trader_name'),'order'=>array('Trader.trader_name'))));
		$this->set('currencies', $this->Trade->Currency->find('list', array('fields'=>array('Currency.currency_iso_code'),'order'=>array('Currency.currency_iso_code'))));
		$this->set('custodians', $this->Trade->Custodian->find('list', array('fields'=>array('Custodian.custodian_name'),'order'=>array('Custodian.custodian_name'))));
	}
	
	
	function ajax_ccydropdown() {
		// Fill select form field after Ajax request.
		//The following caches a [Security Id, Currency] table for use when the user selects from the security dropdown list
		if (($secid_ccyCACHE = Cache::read('secid_ccy')) === false) {
			$sec_ccy = $this->Trade->Sec->find('all', array('fields' => 'Sec.id, Currency.currency_iso_code'));
			$secid_ccyCACHE = array(); 
			foreach($sec_ccy as $c) { 
				$secid_ccyCACHE[$c['Sec']['id']] = $c['Currency']['currency_iso_code']; 
			}
			Cache::write('secid_ccy', $secid_ccyCACHE);
		}
		
		//cache the currency list
		if (($currenciesCACHE = Cache::read('currencies')) === false) {
			$currenciesCACHE = $this->Trade->Currency->find('list', array('fields'=>array('Currency.currency_iso_code'),'order'=>array('Currency.currency_iso_code')));
			Cache::write('currencies', $currenciesCACHE);
		}
		
		//Retrieve the id of the security selected
		$secid = $this->params['url']['data']['Trade']['sec_id'];
		$this->set('selected', $secid_ccyCACHE[$secid]);
		$this->set('options', $currenciesCACHE);
		$this->render('/elements/ajax_dropdown', 'ajax');
	}
	
	//work out the broker's commission
	function ajax_commission() {	
		$qty = str_replace(',','',$this->params['data']['Trade']['quantity']);
		$price = $this->params['data']['Trade']['execution_price'];
		$secid = $this->params['data']['Trade']['sec_id'];
		$brokerid = $this->params['data']['Trade']['broker_id'];
		$valpoint = $this->Trade->Sec->find('first', array('conditions'=> array('Sec.id =' => $secid)));
		$brokercomm = $this->Trade->Broker->find('first', array('conditions'=> array('Broker.id =' => $brokerid)));
		
		if ($this->Trade->Sec->is_deriv($secid) || $this->Trade->Sec->is_bond($secid) || $this->Trade->Sec->is_cash($secid)) {
			$this->set('commission', '0.00');
		}
		else {
			$this->set('commission', round(abs($qty) * $price * $valpoint['Sec']['valpoint'] * $brokercomm['Broker']['commission_rate'],2));
		}
		$this->render('/elements/ajax_commission', 'ajax');
	}

	//tax costs, specifically stamp duty on purchases in the UK
	function ajax_tax() {
		$qty = str_replace(',','',$this->params['data']['Trade']['quantity']);
		$price = $this->params['data']['Trade']['execution_price'];
		$secid = $this->params['data']['Trade']['sec_id'];
		$ccyid = $this->params['data']['Trade']['currency_id'];
		$ttid = $this->params['data']['Trade']['trade_type_id'];
		$valpoint = $this->Trade->Sec->find('first', array('conditions'=> array('Sec.id =' => $secid)));
		$ccy = $this->Trade->Currency->find('first', array('conditions'=> array('Currency.id =' => $ccyid)));
		$tt = $this->Trade->TradeType->find('first', array('conditions'=> array('TradeType.id =' => $ttid)));
		
		if ((strtolower(substr($tt['TradeType']['trade_type'],0,3)) == 'buy') &&
			(strtolower(substr($ccy['Currency']['currency_iso_code'],0,3)) == 'gbp') &&
			(!$this->Trade->Sec->is_deriv($secid)) && 
			($this->Trade->Sec->is_equity($secid))
			) {
				$this->set('tax', round(abs($qty) * $price * $valpoint['Sec']['valpoint'] * 0.005,2));
		}
		else {
			$this->set('tax', '0.00');
		}
		
		$this->render('/elements/ajax_tax', 'ajax');
	}
	
	
	//other costs, most notably the PTM Levy in the UK
	function ajax_othercosts() {
		$qty = str_replace(',','',$this->params['data']['Trade']['quantity']);
		$price = $this->params['data']['Trade']['execution_price'];
		$secid = $this->params['data']['Trade']['sec_id'];
		$ccyid = $this->params['data']['Trade']['currency_id'];
		$valpoint = $this->Trade->Sec->find('first', array('conditions'=> array('Sec.id =' => $secid)));
		$ccy = $this->Trade->Currency->find('first', array('conditions'=> array('Currency.id =' => $ccyid)));
		
		if ((abs($qty * $price * $valpoint['Sec']['valpoint']) > 10000) &&
			(strtolower(substr($ccy['Currency']['currency_iso_code'],0,3)) == 'gbp') &&
			(!$this->Trade->Sec->is_deriv($secid)) &&
			($this->Trade->Sec->is_equity($secid))
			) {
				$this->set('othercosts', 1);
		}
		else {
			$this->set('othercosts', '0.00');
		}
		
		$this->render('/elements/ajax_othercosts', 'ajax');
	}
	
	//If the trade type is a sell, then make sure that the quantity is a negative number
	function ajax_quantity() {
		$ttid = $this->params['form']['tradetype'];
		$qty = str_replace(',','',$this->params['form']['quantity']);
		$tt = $this->Trade->TradeType->find('all', array('fields'=>array('TradeType.trade_type'), 'conditions'=>array('TradeType.id ='=>$ttid)));		
		$tt = $tt['0']['TradeType']['trade_type'];
		
		//$is_sell = ($ttid == 3) || ($ttid == 4);	//trade type id 3 or 4 are the trading Sells. The line below fails for coupons and dividends.
		//$is_sell = ($this->Trade->TradeType->find('count', array('conditions'=>array('TradeType.id =' => $ttid, 'TradeType.trade_type LIKE' => 'sell%'))) > 0);
		$is_sell = ($tt == 'Sell Long') || ($tt == 'Sell Short') || ($tt == 'Coupon Expense') || ($tt == 'Dividend Expense');
	
		if ($is_sell) {
			$quantity = -abs($qty);
		}
		else {
			$quantity = abs($qty);
		}
	
		$this->set('quantity', number_format($quantity, 2, '.', ','));
		$this->render('/elements/ajax_quantity', 'ajax');
	}
	
	
	//Calculate the total consideration figure
	//If the security is a derivative type instrument then calculate the notional value too
	function ajax_consid() {
		$commission = $this->params['data']['Trade']['commission'];
		$tax = $this->params['data']['Trade']['tax'];
		$othercosts = $this->params['data']['Trade']['other_costs'];
		$qty = str_replace(',','',$this->params['data']['Trade']['quantity']);
		$price = $this->params['data']['Trade']['execution_price'];
		$ttid = $this->params['data']['Trade']['trade_type_id'];
		$secid = $this->params['data']['Trade']['sec_id'];
		$accrued = str_replace(',','',$this->params['data']['Trade']['accrued']);
		
		if (!empty($secid) && !empty($ttid)) {
			//cache valpoints for speed
			if (($valpointCACHE = Cache::read('valpoint')) === false) {
				$readdb = $this->Trade->Sec->find('all', array('fields' => array('Sec.id','Sec.valpoint')));
				$valpointCACHE = array(); 
				foreach($readdb as $c) { 
					$valpointCACHE[$c['Sec']['id']] = $c['Sec']['valpoint']; 
				}
				Cache::write('valpoint', $valpointCACHE);
			}
			$valpoint = $valpointCACHE[$secid];
		
			//Check to see if this trade-type is a credit/debit to the trading cash ledger
			if (($creditCACHE = Cache::read('trade_type_credit')) === false) {
				$readdb = $this->Trade->TradeType->find('all', array('fields' => array('TradeType.id', 'TradeType.credit_account_id')));
				$creditCACHE = array(); 
				foreach($readdb as $c) { 
					$creditCACHE[$c['TradeType']['id']] = $c['TradeType']['credit_account_id']; 
				}
				Cache::write('trade_type_credit', $creditCACHE);
			}
			$credit = $creditCACHE[$ttid];

			$notional = 0;	//This will be calculated for derivative type instruments
			$consid = 0;
			
			if (!$this->Trade->Sec->is_deriv($secid)) {
				//handle cashflow differently for buys and sells, credit account 2 is cash
				if ($credit == 2) {
					$consid = -abs($qty * $price * $valpoint) - $commission - $tax - $othercosts - $accrued;
				}
				else {
					$consid = abs($qty * $price * $valpoint) - $commission - $tax - $othercosts + $accrued;
				}
			}
			else {
				$consid = - $commission - $tax - $othercosts;
				if ($credit == 2) {
					$notional = -abs($qty * $price * $valpoint);
				}
				else {
					$notional = abs($qty * $price * $valpoint);
				}
			}
			
			$consid = round($consid, 4);
			$notional = round($notional, 4);
			$this->set('consid', number_format($consid,2).'|'.number_format($notional,2));
			$this->render('/elements/ajax_consid', 'ajax');
		}
		else {
			$this->autoRender=false;
		}
	}
	
	//calculate settlement date
	function ajax_settdate() {
		$td = strtotime($this->params['data']['Trade']['trade_date_input']);
		$sec_id = $this->params['data']['Trade']['sec_id'];
	
		if (!empty($sec_id)) {
			//Cache data needed from the secs table to improve speed
			if (($settdateCACHE = Cache::read('settdate')) === false) {
				$sett = $this->Trade->Sec->find('all', array('fields' => 'Sec.id, Sec.sec_type_id, Sec.country_id'));
				$settdateCACHE = array(); 
				foreach($sett as $s) { 
					$settdateCACHE[$s['Sec']['id']]['sec_type_id'] = $s['Sec']['sec_type_id'];
					$settdateCACHE[$s['Sec']['id']]['country_id'] = $s['Sec']['country_id'];
				}
				Cache::write('settdate', $settdateCACHE);
			}
			$sec_sectype_id =  $settdateCACHE[$sec_id]['sec_type_id'];
			$sec_country_id = $settdateCACHE[$sec_id]['country_id'];
		
			//Look up the settlement date for this $sec_sectype and $sec_country (from the Settlement model).
			App::import('model','Settlement');
			$settle = new Settlement();
			
			//Find the default settlement rule for this sec type
			$default_settle = $settle->SecType->default_settlement($sec_sectype_id);
			
			//Find any exceptions in the settlements table
			$params=array(
				'conditions' => array(  'Settlement.sec_type_id =' => $sec_sectype_id, 
										'Settlement.country_id =' => $sec_country_id),
				'fields' => array('Settlement.settlement_days')
			);
			$sett_days_find = $settle->find('all', $params);
			if (empty($sett_days_find)) {
				$sett_days = $default_settle;
			}
			else {
				$sett_days = $sett_days_find['0']['Settlement']['settlement_days'];
			}
			
			//Find all holiday dates relevant to the security country
			App::import('model','Holiday');
			$hol = new Holiday();
			$hols = $hol->find('all', array('conditions'=>array('Holiday.country_id =' => $sec_country_id), 'fields'=>array('Holiday.holiday_day','Holiday.holiday_month')));
			$holidays = array();
			foreach ($hols as $h) {
				$holidays[$h['Holiday']['holiday_month']][$h['Holiday']['holiday_day']] = 1;
			}
			
			//Loop through each day starting from the trade date for the required settlement period, skipping any weekends and holidays.
			$final_settle_date = $td;
			while ($sett_days > 0) {
				$final_settle_date = strtotime(date("Y-m-d", $final_settle_date) . " +1 day");
				
				if ((date('l', $final_settle_date) == 'Saturday') || (date('l', $final_settle_date) == 'Sunday')) {
					$sett_days++;
				}
				
				if (isset($holidays[date('m', $final_settle_date)][date('d', $final_settle_date)])) {
					$sett_days++;
				}
				$sett_days--;
			}
			
			
			$this->set('settdate', date('Y-m-d', $final_settle_date));
			
		}
		else {
			$this->set('settdate', null);
		}
		
		$this->render('/elements/ajax_settdate', 'ajax');
	}
	
	
	//check that the trade date just chosen is not a holiday or a weekend day
	function ajax_checktradedate() {	
		$trade_date = strtotime($this->params['form']['trade_date']);
		$sec_id = $this->params['form']['sec_id'];
		$datecheck = 0;
		
		//check date is not a weekend day
		if ((date('l', $trade_date) == 'Saturday') || (date('l', $trade_date) == 'Sunday')) {
			$datecheck = 1;
		}
	
		//check date is not a holiday, first find country of selected security
		$country_id = $this->Trade->Sec->read('country_id', $sec_id);
		$country_id = $country_id['Sec']['country_id'];
		App::import('model','Holiday');
		$holmodel = new Holiday();
		$hols = $holmodel->find('count', array('conditions'=>array('Holiday.country_id =' => $country_id,
																   'Holiday.holiday_day =' => date('d', $trade_date) ,
																   'Holiday.holiday_month =' => date('m', $trade_date))));
		if ($hols > 0) {
			$datecheck = 2;
		}
		
		$this->set('data', $datecheck);
		$this->render('/elements/ajax_common', 'ajax');		
	}
	
	
	//Check price entered is not too far away from a price in the price history table
	function ajax_checkprice() {
		$exec_price = $this->params['data']['Trade']['execution_price'];
		$trade_date = $this->params['data']['Trade']['trade_date_input'];
		$sec_id = $this->params['data']['Trade']['sec_id'];
		$this->set('checkprice', null);
		
		if (!empty($exec_price) && !empty($sec_id)) {
			//Check price using Price Model
			App::import('model','Price');
			$price = new Price();
			$stored_price = $price->get_price($sec_id, $trade_date);
			
			if ($stored_price) {
				//Check to see if the entered price is more than 10% away from the stored price.
				if (abs(($stored_price-$exec_price)/$stored_price) >0.1) {
					$this->set('checkprice', 'Warning: The execution price entered is more than 10% away from the price history table.');
				}
			}
		}
		
		$this->render('/elements/ajax_checkprice', 'ajax');
	}
	
	//work out the accrued interest
	function ajax_accrued() {
		$settdate = $this->params['data']['Trade']['settlement_date_input'];
		$secid = $this->params['data']['Trade']['sec_id'];
		$qty = str_replace(',','',$this->params['data']['Trade']['quantity']);
			
		$return = $this->Trade->Sec->accrued($secid, $settdate);
		if ($return['code'] == 0) {
			$accrued = $return['accrued'];
			$this->set('data', number_format(abs($qty) * $accrued / 100,2));
		}
		elseif ($return['code'] == 1) {
			$this->set('data', 'error:'.$return['message']);
		}
		
		$this->render('/elements/ajax_common', 'ajax');
	}
	
	//link to view security
	function ajax_seclink() {
		$secid = $this->params['data']['Trade']['sec_id'];
		$this->set('data', $secid);
		$this->render('/elements/ajax_seclink', 'ajax');
	}
}

?>
