<?php

class Balance extends AppModel {
    var $name = 'Balance';
	var $belongsTo ='Account, Currency, Fund, Sec';
	
	//calculate the month end balances, using the last month end balances and this month's general ledger
	function calc($fund, $date) {
		//first get the date when the last balance was calculated.
		$prevdate = $this->getPrevBalanceDate($fund, $date);
				
		//get the last balance data, else use a null array
		if (empty($prevdate)) {
			$baldata = array();
		}
		else {
			$baldata = $this->find('all', array('conditions'=>array('Balance.act =' => 1, 
																	'Balance.balance_date =' => $prevdate, 
																	'Balance.fund_id =' => $fund)));
		}
				
		//get this month's ledger entries
		App::import('model','Ledger');
		$ledger = new Ledger();
		$ledgdata = $ledger->find('all', array('conditions'=>array('Ledger.act =' => 1, 
																   'Ledger.ledger_date =' => $date, 
																   'Ledger.fund_id =' => $fund,
																   'Ledger.sec_id >' => 0), 
											   'order'=>array('Ledger.trade_crd ASC')));
		
		//Aggregate these two sets together, GROUP BY (account_id, sec_id)
		$newbal = array();
		foreach ($baldata as $b) {
			$newbal[$b['Balance']['account_id']][$b['Balance']['sec_id']][] = array('ledger_debit'=>$b['Balance']['balance_debit'],
																					'ledger_credit'=>$b['Balance']['balance_credit'],
																					'quantity'=>$b['Balance']['balance_quantity'],
																					'currency_id'=>$b['Balance']['currency_id'],
																					'cfd'=>$b['Balance']['balance_cfd'],
																					'trinv'=>$b['Balance']['trinv']);
		}
				
		foreach ($ledgdata as $l) {
			$newbal[$l['Ledger']['account_id']][$l['Ledger']['sec_id']][] = array(  'ledger_debit'=>$l['Ledger']['ledger_debit'],
																					'ledger_credit'=>$l['Ledger']['ledger_credit'],
																					'quantity'=>$l['Ledger']['ledger_quantity'],
																					'currency_id'=>$l['Ledger']['currency_id'],
																					'cfd'=>$l['Ledger']['ledger_cfd'],
																					'trinv'=>$l['Ledger']['trinv'],
																					'trade_date'=>$l['Ledger']['trade_date'],
																					'trade_id'=>$l['Ledger']['trade_id']);
		}
		
		ksort($newbal);	//make sure that the stock book (id=1) is the first to be processed, throw pnl off to the cash book below
		
		//deactivate all previous balances for this month end
		$result = $this->updateAll( array('Balance.act' => 0), 
										array(	'Balance.balance_date =' => $date,
												'Balance.fund_id =' => $fund,
												'Balance.locked =' => 0,
												'Balance.act =' => 1));
		
		if (!$result) { return false; }
		
		$pnl_acc__id = $this->Account->getNamed('Profit And Loss');
		$cash_acc_id = $this->Account->getNamed('Cash');
		//$accrued_acc_id = $this->Account->getNamed('Accrued Interest');
		
		//we have a two-dimensional array of aggregated data, save it to the table now
		foreach ($newbal as $acc=>&$n1) {
			foreach ($n1 as $sec=>$n2) {
				$totdeb = 0;
				$totcred = 0;
				$totqty = 0;
				$ccy = 0;
				$pnl = 0;
				$trinv = '';
				$ref_id = '';
				foreach ($n2 as $d) {
					$totdeb += $d['ledger_debit'];
					$totcred += $d['ledger_credit'];
					$totqty += $d['quantity'];
					$ccy = $d['currency_id'];
					$cfd = $d['cfd'];
					$tri = $d['trinv'];
					if (isset($d['ref_id'])) {
						$ref_id = $ref_id.$d['ref_id'];
					}
					if (isset($d['trade_date'])) {
						$td = $d['trade_date'];
					}
					else {
						$td = null;
					}
					if (isset($d['trade_id'])) {
						$tid = $d['trade_id'];
					}
					else {
						$tid = null;
					}
					
					//only work out realised P&L for securities, not cash
					if ($acc == 1) {
						$result = $this->fifo($trinv, $tri);
						$pnl = $result[0];
						$trinv = $result[1];
						
						//process any PnL thrown off this security this month
						if ($cfd) {
							//for cfd types need to add the pnl to cash, double-entry to the opposite side in the PnL account
							
							//also, for the use of the cash ledger screen, for the benefit of other functions, record the trade details in the ref_id column under the Profit and Loss Account entries
							//the format of the ref_id "atoms" are sec_id:cfd:debit amt:credit amt:quantity;
							if ($pnl > 0) {
								$newbal[$cash_acc_id][$this->Currency->getsecid($ccy)][]=array('ledger_debit'=>$pnl,
																						 'ledger_credit'=>0,
																						 'quantity'=>$pnl,
																						 'currency_id'=>$ccy,
																						 'cfd'=>0,
																						 'trinv'=>'');
								$newbal[$pnl_acc__id][$this->Currency->getsecid($ccy)][]=array('ledger_debit'=>0,
																							 'ledger_credit'=>$pnl,
																							 'quantity'=>0,
																							 'currency_id'=>$ccy,
																							 'cfd'=>0,
																							 'trinv'=>'',
																							 'ref_id'=>
																										$sec.':'.$cfd.':'.$tid.':'.$td.':'.'0'.':'.$pnl.':'.$pnl.';');
							}
							else if ($pnl < 0) {
								$newbal[$cash_acc_id][$this->Currency->getsecid($ccy)][]=array('ledger_debit'=>0,
																						 'ledger_credit'=>abs($pnl),
																						 'quantity'=>$pnl,
																						 'currency_id'=>$ccy,
																						 'cfd'=>0,
																						 'trinv'=>'');
								$newbal[$pnl_acc__id][$this->Currency->getsecid($ccy)][]=array('ledger_debit'=>abs($pnl),
																							 'ledger_credit'=>0,
																							 'quantity'=>0,
																							 'currency_id'=>$ccy,
																							 'cfd'=>0,
																							 'trinv'=>'',
																							 'ref_id'=>
																										$sec.':'.$cfd.':'.$tid.':'.$td.':'.abs($pnl).':'.'0'.':'.$pnl.';');
							}
						}
						else {
							//for non-cfd types need to add pnl back to security line, double-entry to the opposite side in the PnL account
							if ($pnl > 0) {
								$totdeb += $pnl;
								$newbal[$pnl_acc__id][$this->Currency->getsecid($ccy)][]=array('ledger_debit'=>0,
																							 'ledger_credit'=>$pnl,
																							 'quantity'=>0,
																							 'currency_id'=>$ccy,
																							 'cfd'=>0,
																							 'trinv'=>'',
																							 'ref_id'=>
																										$sec.':'.$cfd.':'.$tid.':'.$td.':'.'0'.':'.$pnl.':'.$pnl.';');
							}
							else if ($pnl < 0) {
								$totcred += abs($pnl);
								$newbal[$pnl_acc__id][$this->Currency->getsecid($ccy)][]=array('ledger_debit'=>abs($pnl),
																							 'ledger_credit'=>0,
																							 'quantity'=>0,
																							 'currency_id'=>$ccy,
																							 'cfd'=>0,
																							 'trinv'=>'',
																							 'ref_id'=>
																										$sec.':'.$cfd.':'.$tid.':'.$td.':'.abs($pnl).':'.'0'.':'.$pnl.';');
							}
						}
					}
				}
				
				//write this result line to the database, only if the position is non-zero though
				if (!(($acc == 1) && ($totqty == 0) && (abs($totdeb - $totcred) < 0.01))) {		
					$data['Balance'] = array('act' => 1,
											 'locked' => 0,
											 'crd'=>DboSource::expression('NOW()'),
											 'fund_id' => $fund,
											 'account_id'=>$acc,
											 'balance_date'=>$date,
											 'balance_debit'=>$totdeb,
											 'balance_credit'=>$totcred,
											 'balance_cfd'=>$cfd,
											 'currency_id'=>$ccy,
											 'balance_quantity'=>$totqty,
											 'sec_id'=>$sec,
											 'trinv'=>$trinv,
											 'ref_id'=>$ref_id);
					$this->create($data);
					$this->save();
				}
				
				
				/*
				//if this is a bond, add in accrued interest which should be calculated from the last
				//balance calculation date to the journal posting date
				App::import('model','Sec');
				$sec = new Sec();
				$result = $sec->ledger_accrued($secid, $prevdate, $date);
				if (isset($result['accrued'])) {
					
				
				
					$data = array(	'act' => 1,
									'crd' => DboSource::expression('NOW()'),
									'fund_id' => $fund,
									'account_id' => $accrued_acc_id,
									'ledger_date' => $date,
									'trade_date' => $date,
									'trade_id' => $tid,
									'trade_crd' => $tcrd,
									'ledger_debit' => $cons2,
									'ledger_credit' => 0,
									'ledger_cfd' => $cfd2,
									'currency_id' => $ccy2,
									'ledger_quantity' => $qty2,
									'sec_id' => $secid2,
									'trinv' => $tr2);
					$this->create($data);
					$this->save();
				} */
				
			}
		}
		
		return true;
	}
	
	
	//put prices and fx rates next to balance items by left joining onto the prices table
	function attachprices($fund, $date) {
		$this->unBindModel(array('belongsTo' => array('Currency')));
		
		$params=array(	'fields' => array(	'Fund.fund_name',
											'Account.id',
											'Account.account_name',
											'Balance.balance_debit',
											'Balance.balance_credit',
											'Currency.currency_iso_code',
											'Sec.sec_name',
											'Balance.balance_quantity',
											'Price.price',
											'PriceFX.fx_rate',
											'Price.sec_id',
											'Balance.sec_id',
											'Sec.id',
											'Currency.sec_id',
											'Sec.sec_type_id',
											'Balance.currency_id',
											'Sec.valpoint',
											'SecType.cfd',
											'Balance.trinv'),
						'joins' => array(
										array('table'=>'currencies',
											  'alias'=>'Currency',
											  'type'=>'inner',
											  'foreignKey'=>false,
											  'conditions'=>
													array(	'Currency.id=Balance.currency_id')
											  ),
										array('table'=>'prices',
											  'alias'=>'Price',
											  'type'=>'left',
											  'foreignKey'=>false,
											  'conditions'=>
													array(	'Price.sec_id=Balance.sec_id',
															"Price.price_date='".$date."'")
											  ),
										array('table'=>'prices',
											  'alias'=>'PriceFX',
											  'type'=>'left',
											  'foreignKey'=>false,
											  'conditions'=>
													array(	'PriceFX.sec_id=Currency.sec_id',
															"PriceFX.price_date='".$date."'")
											  ),
										array('table'=>'secs',
											  'alias'=>'Sec2',
											  'type'=>'inner',
											  'foreignKey'=>false,
											  'conditions'=>
													array(	'Balance.sec_id=Sec2.id')
											  ),
										array('table'=>'sec_types',
											  'alias'=>'SecType',
											  'type'=>'inner',
											  'foreignKey'=>false,
											  'conditions'=>
													array(	'Sec2.sec_type_id=SecType.id')
											  )
										),
						'conditions' => array('Balance.act ='=>1, 'Balance.fund_id ='=>$fund, 'Balance.balance_date ='=>$date),
						'order' => array('Balance.account_id')
					);		
		return ($this->find('all', $params));
	}
	
	
	//lock month end
	function lock($fund, $date) {
		$result = $this->updateAll( array('Balance.locked' => 1), 
										array(	'Balance.balance_date =' => $date,
												'Balance.fund_id =' => $fund,
												'Balance.act =' => 1));
		return ($result);
	}
	
	//unlock month end
	function unlock($fund, $date) {
		$result = $this->updateAll( array('Balance.locked' => 0), 
										array(	'Balance.balance_date =' => $date, 
												'Balance.fund_id =' => $fund,
												'Balance.act =' => 1));
		//unlock all future month ends
		$result2 = $this->updateAll( array('Balance.locked' => 0), 
										array(	'Balance.balance_date >' => $date, 
												'Balance.fund_id =' => $fund,
												'Balance.act =' => 1));
		return ($result && $result2);
	}
	
	//is this month locked?
	//the better way would be to have a record dates table with a locked status field, maybe do this for a future version.
	function islocked($fund, $date) {
		$result = $this->find('first', array('conditions'=>array('Balance.fund_id ='=>$fund, 'Balance.balance_date ='=>$date, 'Balance.act ='=>1), 'fields'=>array('Balance.locked')));
		
		if (empty($result['Balance']['locked'])) {
			return false;
		}
		else if ($result['Balance']['locked'] == 1) {
			return true;
		}
		else {
			return false;
		}
	}
	
	
	//clear all the balance data for this fund
	//!Warning, use with extreme caution!
	function wipe($fund) {
		$result = $this->updateAll( array('Balance.locked' => 0,
										  'Balance.act' => 0), 
										array(	'Balance.fund_id =' => $fund));
		return $result;
	}
	
	
	//get the previous balance date for the fund, PHP value of 0=false, anything else=true
	function getPrevBalanceDate($fund, $date) {
		$fetch = $this->find('first', array('conditions'=>array('Balance.fund_id ='=>$fund, 'Balance.balance_date <' => $date, 'Balance.act ='=>1), 'order'=>'Balance.balance_date DESC'));
		if (empty($fetch)) {
			return null;
		}
		else {
			return $fetch['Balance']['balance_date'];
		}
	}
	
	//get the next balance date for the fund, PHP value of 0=false, anything else=true
	function getNextBalanceDate($fund, $date) {
		$fetch = $this->find('first', array('conditions'=>array('Balance.fund_id ='=>$fund, 'Balance.balance_date >' => $date, 'Balance.act ='=>1), 'order'=>'Balance.balance_date ASC'));
		if (empty($fetch)) {
			return null;
		}
		else {
			return $fetch['Balance']['balance_date'];
		}
	}
	
	//get date of last locked balance date, PHP value of 0=false, anything else=true
	function getPrevLockedDate($fund) {
		$fetch = $this->find('first', array('conditions'=>array('Balance.fund_id ='=>$fund, 'Balance.locked =' => 1, 'Balance.act ='=>1), 'order'=>'Balance.balance_date DESC'));
		if (empty($fetch)) {
			return null;
		}
		else {
			return $fetch['Balance']['balance_date'];
		}
	}
	
	
	//check to see if there is a balance calculation for the given date
	function balanceExists($fund, $date) {
		$count = $this->find('count', array('conditions'=>array('Balance.fund_id ='=>$fund, 'Balance.balance_date =' => $date, 'Balance.act ='=>1)));
		if ($count > 0) {
			return true;
		}
		else {
			return false;
		}
	}
	
	
	//check to see if a more recent journal posting has happened since a balance calculation
	function needsRecalc($fund, $date) {
		App::import('model','Ledger');
		$ledger = new Ledger();
		
		$lcrd = $ledger->find('first', array('conditions'=>array('Ledger.fund_id ='=>$fund, 'Ledger.ledger_date =' => $date, 'Ledger.act ='=>1)));
		if (empty($lcrd)) {
			return false;	//no journal for this date so balance calc must be up to date
		}
		else {
			$lcrd = strtotime($lcrd['Ledger']['crd']);
		}
	
		$bcrd = $this->find('first', array('conditions'=>array('Balance.fund_id ='=>$fund, 'Balance.balance_date =' => $date, 'Balance.act ='=>1)));
		if (empty($bcrd)) {
			return true;	//no balance records found, but a journal posting exists for this date, ergo balance not up to date
		}
		else {
			$bcrd = strtotime($bcrd['Balance']['crd']);
			if ($bcrd > $lcrd) {
				return false;
			}
			else {
				return true;
			}
		}
	
	}
	
	
	//this function processes a new trade against a base sequence of historic trades using the fifo convention
	function fifo($base, $new) {
		$b = $this->decode($base);
		$n = $this->decode($new);
		ksort($b);
		ksort($n);
		
		$pnl = 0;
		foreach ($n as $dt=>$m) {
			$qty = $m['quantity'];
			$pr = $m['price'];
			$vp = $m['valpoint'];
		
			//go through each segment of the trade history and see if the new trade could be offset against it or not
			//N.B. if the new trade is a buy (+ve quantity), then look for previous sells (-ve quantity) to offset against and vice versa
			foreach ($b as $date=>$c) {
				$qtyp = $c['quantity'];
				$prp = $c['price'];
				
				//echo debug(array('status'=>'before\\\\\\\\\\\\\\\\\\\\\\\\\\','qty'=>$qty, 'pr'=>$pr, 'vp'=>$vp, 'qtyp'=>$qtyp, 'prp'=>$prp, 'pnl'=>$pnl));
				
				if ($qty * $qtyp < 0) {
					if (abs($qty) > abs($qtyp)) {
						if ((($qty < 0) && ($pr >= $prp)) || (($qty > 0) && ($pr < $prp))) {
							$pnl = $pnl + abs($qtyp)*abs($pr-$prp)*$vp;
						}
						else {
							$pnl = $pnl - abs($qtyp)*abs($pr-$prp)*$vp;
						}
						$qty = $qty + $qtyp;
						unset($b[$date]);
					}
					else if (abs($qty) == abs($qtyp)) {
						if ((($qty < 0) && ($pr >= $prp)) || (($qty > 0) && ($pr < $prp))) {
							$pnl = $pnl + abs($qty)*abs($pr-$prp)*$vp;
						}
						else {
							$pnl = $pnl - abs($qty)*abs($pr-$prp)*$vp;
						}
						$qty = 0;
						unset($b[$date]);
						break;
					}
					else {
						if ((($qty < 0) && ($pr >= $prp)) || (($qty > 0) && ($pr < $prp))) {
							$pnl = $pnl + abs($qty)*abs($pr-$prp)*$vp;
						}
						else {
							$pnl = $pnl - abs($qty)*abs($pr-$prp)*$vp;
						}
						$b[$date]['quantity'] = $qtyp + $qty;
						$qty = 0;
						break;
					}
				}
				
				//echo debug(array('status'=>'atfer\\\\\\\\\\\\\\\\\\\\\\\\\\','qty'=>$qty, 'pr'=>$pr, 'vp'=>$vp, 'qtyp'=>$qtyp, 'prp'=>$prp, 'pnl'=>$pnl));
				
			}
			
			if ($qty <> 0) {
				$b = $b + array($dt=>array('quantity'=>$qty, 'price'=>$pr, 'valpoint'=>$vp));
			}
		}
		return array($pnl, $this->encode($b));
	}
	
	//encodes a working array into the "Trinv" standard format
	function encode($tr) {
		$trinv = '';
		foreach ($tr as $date=>$t) {
			$trinv = $trinv.$date.':'.$t['quantity'].':'.$t['price'].':'.$t['valpoint'].';';
		}
		return $trinv;
	}
	
	//converts a string in the "Trinv" format into an array for easier processing
	function decode($tr) {
		$arr = array();
		$sp1 = explode(";", $tr);
		foreach ($sp1 as $sp2) {
			if (!empty($sp2)) {
				$sp3 = explode(':', "$sp2:::");
				$arr[$sp3[0]] = array('quantity'=>$sp3[1], 'price'=>$sp3[2], 'valpoint'=>$sp3[3]);
			}
		}
		return $arr;
	}
	
	//converts a string in the "ref_id" format into an array
	//used by the CashLedger controller
	function decodeRefID($ref_id) {
		$arr = array();
		$sp1 = explode(";", $ref_id);
		foreach ($sp1 as $sp2) {
			if (!empty($sp2)) {
				$sp3 = explode(':', "$sp2::::::");
				$arr[] = array(	'sec_id'=>$sp3[0],
								'cfd'=>$sp3[1],
								'trade_id'=>$sp3[2],
								'trade_date'=>$sp3[3],
								'debit'=>$sp3[4], 
								'credit'=>$sp3[5], 
								'quantity'=>$sp3[6]);
			}
		}
		return $arr;
	}
}

?>