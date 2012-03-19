<?php

class CashLedger extends AppModel {
    var $name = 'CashLedger';
	var $useTable = 'ledgers';
	var $belongsTo ='Account, Trade, Fund, Currency, Sec';
	
	
	//get the carried forward cash balance at $date on a settlement date basis
	function carry_forward($fund, $date, $ccy) {
		$debit = 0;
		$credit = 0;
		$quantity = 0;
		$cash_acc_id = $this->Account->getNamed('Cash');
		$pnl_acc__id = $this->Account->getNamed('Profit And Loss');
		App::import('model','Balance');
		$balmodel = new Balance();
		
		//get the total sum for that currency for that date
		$result = $balmodel->find('all', array( 'conditions'=>array('Balance.act ='=>1,
																	'Balance.fund_id ='=>$fund,
																	'Balance.balance_date ='=>$date,
																	'Balance.account_id ='=>$cash_acc_id,
																	'Balance.currency_id ='=>$ccy), 
												'fields'=>array('Balance.balance_debit', 'Balance.balance_credit', 'Balance.balance_quantity')));
		
		if (!empty($result)) {
			$debit = $result[0]['Balance']['balance_debit'];
			$credit = $result[0]['Balance']['balance_credit'];
			$quantity = $result[0]['Balance']['balance_quantity'];
		}
		
		//add back any unsettled trades
		$unsettled = $this->getUnsettled($fund, $date, $ccy);
		foreach ($unsettled as $u) {
			$debit = $debit - $u['CashLedger']['ledger_debit'];
			$credit = $credit - $u['CashLedger']['ledger_credit'];
			$quantity = $quantity - $u['CashLedger']['ledger_quantity'];
		}
				
		//now add back any realised P&L from cfd type instruments which have not settle yet at this $date
		//this information is held in the "unsettled" field in the balances table which stores all the
		//prior trades which are unsettled as of balance_date
		$result = $balmodel->find('all', array( 'conditions'=>array('Balance.act ='=>1,
																	'Balance.fund_id ='=>$fund,
																	'Balance.balance_date ='=>$date,
																	'Balance.account_id ='=>$pnl_acc__id,
																	'Balance.currency_id ='=>$ccy), 
												'fields'=>array('Balance.unsettled')));	
		
		if (!empty($result)) {
			$unsettled = $balmodel->decodeRefID($result[0]['Balance']['unsettled']);
			foreach ($unsettled as $u) {
				$debit = $debit - $u['credit'];	//swap debit and credit as P&L account has opposite Db/Cr entry to cash account.
				$credit = $credit - $u['debit'];
				$quantity = $quantity - $u['quantity'];
			}
		}
		
		return array($debit, $credit, $quantity);
	}
	
	
	//work out the cash ledger entries, also combining in any realised PnL for cfd type instruments
	function getCash($fund, $date, $ccy) {
		App::import('model','Balance');
		$balmodel = new Balance();
		$cash_acc_id = $this->Account->getNamed('Cash');
		$pnl_acc__id = $this->Account->getNamed('Profit And Loss');
		
		$cashdata = $this->find('all', array( 'fields'=>array(	'CashLedger.trade_date',
																'CashLedger.settlement_date',
																'CashLedger.ledger_debit',
																'CashLedger.ledger_credit',
																'CashLedger.ledger_quantity',
																'CashLedger.ledger_date',
																'Trade.id',
																'Sec2.sec_name'),
												'conditions'=>array('CashLedger.fund_id =' => $fund,
																	'CashLedger.account_id =' => $cash_acc_id,
																	'AND'=>array('CashLedger.ledger_date =' => $date,
																				 'CashLedger.settlement_date <=' => $date),
																	'CashLedger.currency_id =' => $ccy,
																	'CashLedger.act =' => 1,
																	'CashLedger.sec_id >'=> 0,
																	'(ABS(CashLedger.ledger_debit)+ABS(CashLedger.ledger_credit)+ABS(CashLedger.ledger_quantity)) >'=>0.0001), 
												'order'=> array('CashLedger.trade_date' => 'ASC', 
																'CashLedger.trade_crd' => 'ASC' ),
												'joins' => array(
																array('table'=>'secs',
																	  'alias'=>'Sec2',
																	  'type'=>'left',
																	  'foreignKey'=>false,
																	  'conditions'=>
																			array(	'CashLedger.ref_id=Sec2.id')
																	  ))));
		
		//add on the non-cfd trades which settle after the previous balance calculation date
		$prevdate = $balmodel->getPrevBalanceDate($fund, $date);
		if (!empty($prevdate)) {
			$unsettled = $this->getUnsettled($fund, $prevdate, $ccy);
			//change the trade date to the settlement date for each of these trades
			foreach ($unsettled as &$u) {
				$u['CashLedger']['trade_date'] = $u['CashLedger']['settlement_date'];
			}
			$cashdata = array_merge($cashdata, $unsettled);
			
			//also add in any cfd-type trades which settle after the previous balance date
			//this information is held in the "unsettled" field in the balances table which stores all the
			//prior trades which are unsettled as of the balance_date
			$result = $balmodel->find('all', array( 'conditions'=>array('Balance.act ='=>1,
																		'Balance.fund_id ='=>$fund,
																		'Balance.balance_date ='=>$prevdate,
																		'Balance.account_id ='=>$pnl_acc__id,
																		'Balance.currency_id ='=>$ccy), 
													'fields'=>array('Balance.unsettled')));
			
			if (!empty($result)) {
				$unsettled = $balmodel->decodeRefID($result[0]['Balance']['unsettled']);
				
				foreach ($unsettled as $n) {
					$sec_name = $this->Sec->read('sec_name', $n['sec_id']);
					$sec_name = $sec_name['Sec']['sec_name'];
					$cashdata[] = array('CashLedger' => array('trade_date'=>$n['settlement_date'],	//put the trade date equal to the settlement date, if this date is after $date, then it will be truncated near the end
															  'settlement_date'=>$n['settlement_date'],
															  'ledger_debit'=>$n['credit'],	//debits and credits are swapped around because the P&L account where these numbers
															  'ledger_credit'=>$n['debit'],	//come from is an expense type account whereas Cash is an asset type account
															  'ledger_quantity'=>$n['quantity']),
										'Trade' =>		array('id' => $n['trade_id']),
										'Sec2' =>		array('sec_name' => $sec_name));
				}
			}
		}
		
		//check to see if the balance calculation has been done or not. If not, then return the results so far, a red warning message will be displayed later.
		if (!$balmodel->balanceExists($fund, $date)) {
			return($this->sortByTradeDate($cashdata));
		}
		
		//add on any pnl generated by cfd type instruments
		//this will be recorded in the ref_id column of the balances table
		$addpnl = $balmodel->find('all', array(	'conditions'=>array('Balance.act ='=>1,
																	'Balance.fund_id ='=>$fund,
																	'Balance.balance_date ='=>$date,
																	'Balance.account_id ='=>$pnl_acc__id,
																	'Balance.currency_id ='=>$ccy),
												'fields'=>array('Balance.ref_id')));
		
		
		//the ref_id column contains all the information of trades contributing to the Profit and Loss account for the current period
		//merge the realised P&L from cfd type instruments as this is not included in the debit/credits, unlike non-cfd type instruments
		//N.B. only include realised gains which have been settled before $date.
		if (!empty($addpnl)) {
			$ref_id = $balmodel->decodeRefId($addpnl[0]['Balance']['ref_id']);
			foreach ($ref_id as $arr) {
				if (($arr['cfd'] == 1) && (strtotime($arr['settlement_date']) <= strtotime($date))) {	//see above
					$sec_name = $this->Sec->read('sec_name', $arr['sec_id']);
					$sec_name = $sec_name['Sec']['sec_name'];
					$cashdata[] = array('CashLedger' => array('trade_date'=>$arr['trade_date'],
															  'settlement_date'=>$arr['settlement_date'],
															  'ledger_debit'=>$arr['credit'],	//debits and credits are swapped around because the P&L account where these numbers
															  'ledger_credit'=>$arr['debit'],	//come from is an expense type account whereas Cash is an asset type account
															  'ledger_quantity'=>$arr['quantity']),
										'Trade' =>		array('id' => $arr['trade_id']),
										'Sec2' =>		array('sec_name' => $sec_name));
				}
			}
		}
		
		//remove all entries with trade date after $date, in case any were introduced by the code above
		foreach ($cashdata as $key=>$c) {
			if (strtotime($c['CashLedger']['trade_date']) > strtotime($date)) {
				unset($cashdata[$key]);
			}
		}
		
		//sort the merged results array by trade date and return back to controller
		return($this->sortByTradeDate($cashdata));
	}
	
	
	function sortByTradeDate(array $array) {
		$result = array();
		$values = array();
		foreach ($array as $id => $value) {
			$values[$id] = strtotime($value['CashLedger']['trade_date']);
		}
		   
		asort($values);
	
		foreach ($values as $key => $value) {
			$result[$key] = $array[$key];
		}
		   
		return $result;
	}
	
	
	//Get the unsettled trades as at $date
	function getUnsettled($fund, $date, $ccy) {
		//get id of the cash book
		$cash_acc_id = $this->Account->getNamed('Cash');
		
		//get the unsettled trades
		$cashdata = $this->find('all', array( 	'fields'=>array('CashLedger.trade_date',
																'CashLedger.settlement_date',
																'CashLedger.ledger_debit',
																'CashLedger.ledger_credit',
																'CashLedger.ledger_quantity',
																'CashLedger.ledger_date',
																'Trade.id',
																'Sec2.sec_name'),
												'conditions'=>array('CashLedger.fund_id =' => $fund,
																	'CashLedger.account_id =' => $cash_acc_id,
																	'AND'=>array('CashLedger.trade_date <=' => $date,
																				 'CashLedger.settlement_date >' => $date),
																	'CashLedger.currency_id =' => $ccy,
																	'CashLedger.act =' => 1,
																	'CashLedger.sec_id >'=> 0,	//ignore dummy lines
																	'(ABS(CashLedger.ledger_debit)+ABS(CashLedger.ledger_credit)+ABS(CashLedger.ledger_quantity)) >'=>0.0001), //ignore zero lines
												'joins' => array(
																array('table'=>'secs',
																	  'alias'=>'Sec2',
																	  'type'=>'left',
																	  'foreignKey'=>false,
																	  'conditions'=>
																			array(	'CashLedger.ref_id=Sec2.id')
																	  ))));	
		return($cashdata);
	}
}

?>