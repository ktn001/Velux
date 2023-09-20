<?php
/* This file is part of Jeedom.
*
* Jeedom is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* Jeedom is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
*/

/* * ***************************Includes********************************* */
require_once __DIR__  . '/../../../../core/php/core.inc.php';

class velux extends eqLogic {
	/*     * *************************Attributs****************************** */

	/*
	* Permet de définir les possibilités de personnalisation du widget (en cas d'utilisation de la fonction 'toHtml' par exemple)
	* Tableau multidimensionnel - exemple: array('custom' => true, 'custom::layout' => false)
	public static $_widgetPossibility = array();
	*/

	/*
	* Permet de crypter/décrypter automatiquement des champs de configuration du plugin
	* Exemple : "param1" & "param2" seront cryptés mais pas "param3"
	public static $_encryptConfigKey = array('param1', 'param2');
	*/

	/*     * ***********************Methode static*************************** */

	/*
	* Fonction exécutée automatiquement toutes les minutes par Jeedom
	public static function cron() {}
	*/

	/*
	* Fonction exécutée automatiquement toutes les 5 minutes par Jeedom
	public static function cron5() {}
	*/

	/*
	* Fonction exécutée automatiquement toutes les 10 minutes par Jeedom
	public static function cron10() {}
	*/

	/*
	* Fonction exécutée automatiquement toutes les 15 minutes par Jeedom
	public static function cron15() {}
	*/

	/*
	* Fonction exécutée automatiquement toutes les 30 minutes par Jeedom
	public static function cron30() {}
	*/

	/*
	* Fonction exécutée automatiquement toutes les heures par Jeedom
	public static function cronHourly() {}
	*/

	/*
	* Fonction exécutée automatiquement tous les jours par Jeedom
	public static function cronDaily() {}
	*/

	/*
	* Permet de déclencher une action avant modification d'une variable de configuration du plugin
	* Exemple avec la variable "param3"
	public static function preConfig_param3( $value ) {
		// do some checks or modify on $value
		return $value;
	}
	*/

	/*
	* Permet de déclencher une action après modification d'une variable de configuration du plugin
	* Exemple avec la variable "param3"
	public static function postConfig_param3($value) {
		// no return value
	}
	*/

	/*
	 * Permet d'indiquer des éléments supplémentaires à remonter dans les informations de configuration
	 * lors de la création semi-automatique d'un post sur le forum community
	 public static function getConfigForCommunity() {
		return "les infos essentiel de mon plugin";
	 }
	 */

	public static function getHkEqLogics ($model=null, $onlyUnUsed=True) {
		$hkEqType = 'hkControl';
		$hkEqLogics = array();
		if ($model == null) {
			$hkEqLogics = eqLogic::byType($hkEqType);
		} else {
			$cfg = ['model'=>'VELUX '.$model];
			$hkEqLogics = eqLogic::byTypeAndSearchConfiguration($hkEqType, $cfg);
		}
		if ($onlyUnUsed) {
			log::add("velux","warning","TODO: implémenter 'onlyUnUsed'");
		}
		return $hkEqLogics;
	}

	public static function getCmdAssociationPropositions ($hkEq_id) {
		$hkEq = hkControl::byId($hkEq_id);
		if (!is_object($hkEq)) {
			log::add("velux","error",sprintf(__("Equipement homekit avec l'id %s introuvable",__FILE__),$hkEq_id));
			return null;
		}
		if (! $hkEq instanceof hkControl) {
			log::add("velux","error",sprintf(__("L'équipement %s n'est pas un équipement du plugin hkControl",__FILE__),$hkEq_id));
			return null;
		}
		if (substr_compare($hkEq->getConfiguration('model'),'VELUX',0,5,true) != 0){
			log::add("velux","error",sprintf(__("L'équipement %s n'est pas un équipement Velux",__FILE__),$hkEq->getHumanName()));
			return null;
		}
		if (strtolower($hkEq->getConfiguration('model')) == 'velux gateway') {
			log::add("velux","error",__("Les gateways Homekit de Velux ne peuvent pas être gérés par ce plugin",__FILE__));
			return null;
		}

		$hkCmds = $hkEq->getCmd(null, null, null, true);
		$inConfig = config::byKey('hkCmds_' . $hkEq_id, 'velux');
		$propositions = [];
		
		$cmds = [
			'refresh' => [
				'type' => 'action',
				'subType' => 'other',
			],
			'identify' => [
				'type' => 'action',
				'subType' => 'other',
			],
			'target_info' => [
				'type' => 'info',
				'subType' => 'numeric',
			],
			'target_action' => [
				'type' => 'action',
				'subType' => 'slider',
			],
			'position' => [
				'type' => 'info',
				'subType' => 'numeric',
			],
			'state' => [
				'type' => 'info',
				'subType' => 'numeric',
			]
			
		];
		foreach ($cmds as $logicalId => $cmd) {
			$actual = 0;
			if (array_key_exists($logicalId,$inConfig)) {
				$actual = $inConfig[$logicalId];
			}
			$propositions[$logicalId] = [];
			$selectedOK = false;
			foreach ($hkCmds as $hkCmd) {
				if ($hkCmd->getType() == $cmd['type'] and $hkCmd->getSubType() == $cmd['subType']) {
					$values = [
						'id' => $hkCmd->getId(),
						'name' => $hkCmd->getname(),
						'humanName' => $hkCmd->getHumanName(),
						'selected' => 0,
						'logicalId' => $hkCmd->getLogicalId(),
					];
					if ($values['id'] == $actual) {
						$values['selected'] = 1;
						$selectedOK = true;
					}
					$propositions[$logicalId][] = $values;
				}
			}
			if (! $selectedOK) {
				foreach ($propositions[$logicalId] as &$proposition) {
					if ($proposition['logicalId'] == $logicalId) {
						$proposition['selected'] = 1;
					}
				}
			}
		}
		return $propositions;
	}

	/*
	* Permet de crypter/décrypter automatiquement des champs de configuration des équipements
	* Exemple avec le champ "Mot de passe" (password)
	public function decrypt() {
		$this->setConfiguration('password', utils::decrypt($this->getConfiguration('password')));
	}
	public function encrypt() {
		$this->setConfiguration('password', utils::encrypt($this->getConfiguration('password')));
	}
	*/

	/*
	* Permet de modifier l'affichage du widget (également utilisable par les commandes)
	public function toHtml($_version = 'dashboard') {}
	*/

	/*     * *********************Méthodes d'instance************************* */

	// Fonction exécutée automatiquement avant la création de l'équipement
	public function preInsert() {
	}

	// Fonction exécutée automatiquement après la création de l'équipement
	public function postInsert() {
	}

	// Fonction exécutée automatiquement avant la mise à jour de l'équipement
	public function preUpdate() {
	}

	// Fonction exécutée automatiquement après la mise à jour de l'équipement
	public function postUpdate() {
	}

	// Fonction exécutée automatiquement avant la sauvegarde (création ou mise à jour) de l'équipement
	public function preSave() {
	}

	// Fonction exécutée automatiquement après la sauvegarde (création ou mise à jour) de l'équipement
	public function postSave() {
		$cmdFile = __DIR__ . "/../config/cmds.json";
		$configs = json_decode(file_get_contents($cmdFile),true);
		foreach ($configs as $logicalId => $config) {
			$cmd = $this->getCmd(null, $logicalId);
			if (is_object($cmd)) {
				continue;
			}
			$cmd = new veluxCmd();
			$cmd->setLogicalId($logicalId);
			$cmd->setIsVisible($config['visible']);
			$cmd->setName(translate::exec($config['name'],$cmdFile));
			$cmd->setType($config['type']);
			$cmd->setSubType($config['subType']);
			$cmd->setEqLogic_id($this->getId());
			$cmd->setOrder($config['order']);
			$cmd->save();

		}
	}

	// Fonction exécutée automatiquement avant la suppression de l'équipement
	public function preRemove() {
	}

	// Fonction exécutée automatiquement après la suppression de l'équipement
	public function postRemove() {
	}

	public function postAjax() {
		$cmdFile = __DIR__ . "/../config/cmds.json";
		$configs = json_decode(file_get_contents($cmdFile),true);
		foreach ($configs as $logicalId => $config) {
			if (! array_key_exists('value',$config)) {
				continue;
			}
			$cmd = $this->getCmd(null, $logicalId);
			$valueCmd = $this->getCmd(null, $config['value']);
			if ($cmd->getValue() != $valueCmd->getid()) {
				$cmd->setValue($valueCmd->getId());
				$cmd->save();
			}
		}
	}

	/*     * **********************Getteur Setteur*************************** */
}

class veluxCmd extends cmd {
	/*     * *************************Attributs****************************** */

	/*
	public static $_widgetPossibility = array();
	*/

	/*     * ***********************Methode static*************************** */


	/*     * *********************Methode d'instance************************* */

	/*
	* Permet d'empêcher la suppression des commandes même si elles ne sont pas dans la nouvelle configuration de l'équipement envoyé en JS
	public function dontRemoveCmd() {
		return true;
	}
	*/

	public function preSave() {
		if ($this->getType() == 'info') {
			$value = $this->getConfiguration('linkedCmd');
			if ($value != '') {
				$this->setValue($value);
			}
		}
	}

	// Exécution d'une commande
	public function execute($_options = array()) {
		switch ($this->getType()) {
		case 'info':
			return jeedom::evaluateExpression($this->getConfiguration('linkedCmd'));
			break;
		case 'action':
			log::add("velux","info","11111111");
			if ($this->getConfiguration('linkedCmd') != '') {
				$cmd = cmd::byId(str_replace('#', '', $this->getConfiguration('linkedCmd')));
				return $cmd->execcmd($_options);
			}
		}
	}

	/*     * **********************Getteur Setteur*************************** */
}
