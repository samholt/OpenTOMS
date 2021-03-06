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

class SettlementsController extends AppController {
	var $helpers = array ('Html','Form');
	var $name = 'Settlements';

	function index() {	
	
		if (isset($this->params['pass']['0'])) {
			$sectype_id = $this->params['pass']['0'];
		}
		elseif (isset($this->data['Settlement']['sec_type_id'])) {
			$sectype_id = $this->data['Settlement']['sec_type_id'];
		}
		else {
			$sectype_id = 13;	//Equity
		}
		
		$params=array(
			'conditions' => array('Settlement.sec_type_id =' => $sectype_id),
			'order' => array('Country.country_name')
		);
		
		$this->set('settlements', $this->Settlement->find('all', $params));
		$this->set('sectype_id', $sectype_id);
		$this->set('secTypes', $this->Settlement->SecType->find('list', array('conditions'=>array('SecType.act ='=>1),'fields'=>array('SecType.sec_type_name'),'order'=>array('SecType.sec_type_name'))));
		$this->set('countries', $this->Settlement->Country->find('list', array('fields'=>array('Country.country_name'),'order'=>array('Country.country_name'))));
		
		//Find the default settlement rule for this sec type
		$this->set('default_settlement', $this->Settlement->SecType->default_settlement($sectype_id));
	}
	
	function add() {
		if (!empty($this->data)) {
			if ($this->Settlement->save($this->data)) {
				$this->Session->setFlash('Settlement Rule has been saved.');
				$this->redirect(array('action' => 'index', $this->data['Settlement']['sec_type_id']));
			}
		}
	}
	
	function delete($id, $sectype_id) {
		$this->Settlement->delete($id);
		$this->redirect(array('action' => 'index', $sectype_id));
	}
}
?>
