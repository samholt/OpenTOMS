<?php
class SecsController extends AppController {
	var $helpers = array ('Html','Form');
	var $name = 'Secs';

	function index() {		
		if (!empty($this->params['pass'])) {
			$a = $this->params['pass'][0];
		} else {
			$a = 'A';
		};
		
		$conditions=array(
			'Sec.sec_name LIKE ' => $a.'%'
		);
	
		$params=array(
			'conditions' => $conditions, 
			'fields' => array('Sec.id', 'Sec.sec_name', 'ticker', 'tradarid', 'Currency.currency_iso_code', 'valpoint'),
			'order' => array('Sec.sec_name ASC') 
		);
		
		$this->set('secs', $this->Sec->find('all', $params));
	}
	
	function add() {
		$this->setchoices();
		
		if (!empty($this->data)) {
			if ($this->Sec->save($this->data)) {
				$this->Session->setFlash('Security has been saved.');
				$this->clearcache();
				$this->redirect(array('action' => 'view', $this->Sec->id));
			}
		}
	}
	
	function edit($id = null) {
		$this->Sec->id = $id;
		$this->setchoices();
		
		if (empty($this->data)) {
			$this->data = $this->Sec->read();
		} else {
			if ($this->Sec->save($this->data)) {
				$this->Session->setFlash('Security has been updated.');
				$this->clearcache();
				$this->redirect(array('action' => 'view',$id));
			}
		}
	}
	
	function view($id = null) {
		$this->Sec->id = $id;
		$this->set('sec', $this->Sec->read());
	}
	
	function setchoices() {
		$this->set('secTypes', $this->Sec->SecType->find('list', array('fields'=>array('SecType.sec_type_name'),'order'=>array('SecType.sec_type_name'))));
		$this->set('countries', $this->Sec->Country->find('list', array('fields'=>array('Country.country_name'),'order'=>array('Country.country_name'))));
		$this->set('exchanges', $this->Sec->Exchange->find('list', array('fields'=>array('Exchange.exchange_name'),'order'=>array('Exchange.exchange_name'))));
		$this->set('industries', $this->Sec->Industry->find('list', array('fields'=>array('Industry.industry_name'),'order'=>array('Industry.industry_name'))));
		$this->set('currencies', $this->Sec->Currency->find('list', array('fields'=>array('Currency.currency_iso_code'),'order'=>array('Currency.currency_iso_code'))));
	}
	
	function clearcache() {
		Cache::delete('secs');	//clear cache
		Cache::delete('secid_ccy');	//clear cache
		Cache::delete('valpoint');	//clear cache
		Cache::delete('settdate');	//clear cache
	}
}
?>