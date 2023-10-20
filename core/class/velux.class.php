<?php

/* * ***************************Includes********************************* */
require_once __DIR__  . '/../../../../core/php/core.inc.php';

class velux extends eqLogic {
	/*     * *************************Attributs****************************** */


	/*     * ***********************Methode static*************************** */

	/*
	 * Permet d'indiquer des éléments supplémentaires à remonter dans les informations de configuration
	 * lors de la création semi-automatique d'un post sur le forum community
	 public static function getConfigForCommunity() {
		return "les infos essentiel de mon plugin";
	 }
	 */

	/*
	 * Fonction appelée par les listener lors des changements d'état (ouverture, fermeture
	 * ou arrêt) de la fenêtre ou du store
	 */
	public static function listenerHandler($_option) {
		// S'agit-il d'un changement de mouvement
		$cmd =  cmd::byId($_option['event_id']);
		if (substr($cmd->getLogicalId(),-6) == ":state") {

			// Arret de l'eq
			if ($_option['value'] == 2) {
				log::add("velux","info","┌listenerHandler: " . json_encode($_option));
				$velux = self::byId($_option['id']);
				log::add("velux","debug","│cmd: " . $cmd->getLogicalId());
				$velux->refresh();
				sleep(1);
				$target = $velux->getCache('target');
				$positions = $velux->getPositions();
				$consignes = $velux->getConsignes();
				$logicalId = $cmd->getLogicalId();
				$eq = substr($logicalId,0,strpos($logicalId,":"));
				log::add("velux","debug","│eq: " . $eq);
				log::add("velux","debug","│consigne: " . $consignes[$eq]);
				log::add("velux","debug","│position: " . $positions[$eq]);
				log::add("velux","debug","│target: " . $target);
				if ($target < 0) {
					// Le dernier mouvement n'a pas été provoqué par Jeedom
					log::add("velux","info","└" . __("Le mouvement n'a pas été lancé par le plugin, mise en pause du velux.",__FILE__));
					$velux->setPause(1);
					$velux->setConsignes(["w"=>-1, "s"=>-1]);
				} else {
					if (($positions[$eq] != $consignes[$eq]) and ($positions[$eq] != $target)) {
						log::add("velux","info","└" . __("L'équipement a été arrêté avant d'atteindre la position cible, mise en pause du velux.",__FILE__));
						$velux->setPause(1);
						$velux->setCache('target',-1);
						$velux->setConsignes(["w"=>-1, "s"=>-1]);
						$velux->refresh();
					} else {
						log::add("velux","info","└" . __("OK pour prochain mouvement",__FILE__));
						$velux->setCache('target',-1);
						$velux->doMove();
					}
				}
			} else {
				log::add("velux","info","─listenerHandler: " . json_encode($_option));
			}
		}
	}

	public static function deadCmd() {
		$return = array();
		foreach (eqLogic::byType('velux') as $velux) {
			foreach ($velux->getCmd() as $cmd) {
				preg_match_all("/#([0-9]+?)#/", $cmd->getConfiguration('linkedCmd'), $matches);
				foreach ($matches[1] as $cmd_id) {
					if (!cmd::byId(str_replace('#', '', $cmd_id))) {
						$return[] = array('detail' => sprintf(__('%s dans la commande "%s"',__FILE__), $velux->getHumanName(), $cmd->getName()), 'help' => __('Commande liée',__FILE__), 'who' => '#' . $cmd_id . '#');
					}
				}
				preg_match_all("/#([0-9]+?)#/", $cmd->getConfiguration('calcul'), $matches);
				foreach ($matches[1] as $cmd_id) {
					if (!cmd::byId(str_replace('#', '', $cmd_id))) {
						$return[] = array('detail' => sprintf(__('%s dans la commande "%s"',__FILE__), $velux->getHumanName(), $cmd->getName()), 'help' => __('Calcul',__FILE__), 'who' => '#' . $cmd_id . '#');
					}
				}
			}
		}
		return $return;
	}

	/*
	 * Retourne une liste des équipements Velux (fenêtre ou store) configurés
	 * dans le plugin hkControl (homekit)
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

	/*
	 * Retourne la liste de commandes d'un équipement HK pouvant être associées
	 * à chacune des commandes d'un équipement du plugin "velux"
	 */
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
	 * Fonction exécutée automatiquement avant la sauvegarde (création ou mise à jour) de l'équipement
	 */
	public function preSave() {
		if ($this->getConfiguration('windowsLimit') == '') {
			$this->setConfiguration('windowsLimit',"7");
		}
		if ($this->getConfiguration('shuttersLimit') == '') {
			$this->setConfiguration('shuttersLimit',"55");
		}
		$limit = $this->getConfiguration('windowsLimit');
		if (!(ctype_digit($limit)) or ($limit < 0) or ($limit > 100)) {
			throw new Exception(__("La position limite de la fenêtre doit être un entier compris en 0 et 100.",__FILE__));
		}
		$limit = $this->getConfiguration('shuttersLimit');
		if (!(ctype_digit($limit)) or ($limit < 0) or ($limit > 100)) {
			throw new Exception(__("La position limite du volet roulant doit être un entier compris en 0 et 100.",__FILE__));
		}

	}

	/*
	 * Fonction exécutée automatiquement après la sauvegarde (création ou mise à jour) de l'équipement
	 */
	public function postSave() {
		$cmdFile = __DIR__ . "/../config/cmds.json";
		$configs = json_decode(file_get_contents($cmdFile),true);
		$cmd = $this->getCmd('action', 'refresh');
		if (! is_object($cmd)) {
			$cmd = new veluxCmd();
			$cmd->setLogicalId('refresh');
			$cmd->setIsVisible(1);
			$cmd->setName(__('Rafraîchir',__FILE__));
			$cmd->setType('action');
			$cmd->setSubType('other');
			$cmd->setEqLogic_id($this->getId());
			$cmd->setOrder(1);
			$cmd->save();
		}

		$cmd = $this->getCmd('info', 'pause');
		if (! is_object($cmd)) {
			$cmd = new veluxCmd();
			$cmd->setLogicalId('pause');
			$cmd->setIsVisible(1);
			$cmd->setName(__('En pause',__FILE__));
			$cmd->setType('info');
			$cmd->setSubType('binary');
			$cmd->setEqLogic_id($this->getId());
			$cmd->setConfiguration('returnStateTime',60);
			$cmd->setConfiguration('returnStateValue',0);
			$cmd->setOrder(2);
			$cmd->save();
		}

		$cmd = $this->getCmd('action','pause_on');
		if (! is_object($cmd)) {
			$cmd = new veluxCmd();
			$cmd->setLogicalId('pause_on');
			$cmd->setIsVisible(1);
			$cmd->setName(__('Pause ON',__FILE__));
			$cmd->setType('action');
			$cmd->setSubType('other');
			$cmd->setEqLogic_id($this->getId());
			$cmd->setTemplate('dashboard','default');
			$cmd->setTemplate('mobile','default');
			$cmd->setOrder(3);
			$cmd->save();
		}

		$cmd = $this->getCmd('action','pause_off');
		if (! is_object($cmd)) {
			$cmd = new veluxCmd();
			$cmd->setLogicalId('pause_off');
			$cmd->setIsVisible(1);
			$cmd->setName(__('Pause OFF',__FILE__));
			$cmd->setType('action');
			$cmd->setSubType('other');
			$cmd->setEqLogic_id($this->getId());
			$cmd->setTemplate('dashboard','default');
			$cmd->setTemplate('mobile','default');
			$cmd->setOrder(4);
			$cmd->save();
		}

		$cmd = $this->getCmd('info', 'rain');
		if (! is_object($cmd)) {
			$cmd = new veluxCmd();
			$cmd->setLogicalId('rain');
			$cmd->setIsVisible(1);
			$cmd->setName(__('Pluie',__FILE__));
			$cmd->setType('info');
			$cmd->setSubType('binary');
			$cmd->setEqLogic_id($this->getId());
			$cmd->setOrder(5);
			$cmd->save();
		}

		$cmd_pause = $this->getCmd('info', 'pause');
		$cmd_pause_on = $this->getCmd('action', 'pause_on');
		$cmd_pause_on->setValue($cmd_pause->getId());
		$cmd_pause_on->save();
		$cmd_pause_off = $this->getCmd('action', 'pause_off');
		$cmd_pause_off->setValue($cmd_pause->getId());
		$cmd_pause_off->save();

		$this->setListener();
	}

	/*
	 * Fonction exécutée automatiquement avant la suppression de l'équipement
	 */
	public function preRemove() {
		$this->removeListener();
	}

	/*
	 * Fonction exécutée automatiquement après la sauvegarde de l'équipement et
	 * de ses commandes via l'interface WEB
	 */
	public function postAjax() {
		$cmdFile = __DIR__ . "/../config/cmds.json";
		$configs = json_decode(file_get_contents($cmdFile),true);

		foreach (['w','s'] as $vtype) {
			if ($this->getConfiguration($vtype . ":hkId") == '') {
				foreach ($this->getCmd() as $cmd) {
					if (strpos($cmd->getLogicalId(), $vtype . ':') === 0) {
						$cmd->remove();
					}
				}
			}
		}
		foreach ($configs as $logicalId => $config) {
			if (! array_key_exists('value',$config)) {
				continue;
			}
			$cmd = $this->getCmd(null, $logicalId);
			if (is_object($cmd)) {
			$valueCmd = $this->getCmd(null, $config['value']);
				if ($cmd->getValue() != $valueCmd->getid()) {
					$cmd->setValue($valueCmd->getId());
					$cmd->save();
				}
			}
		}
		$this->setListener();
		if ($this->getIsEnable() == 1) {
			$this->refresh();
		}
	}

	/*
	 * Fonctions pour la gestion du listener
	 */
	private function getListener() {
		return listener::byClassAndFunction(__CLASS__, 'listenerHandler', array('id' => $this->getId()));
	}

	private function removeListener() {
		$listener = $this->getListener();
		if (is_object($listener)) {
			$listener->remove();
		}
	}

	private function setListener() {
		if ($this->getIsEnable() == 0) {
			$this->removeListener();
			return;
		}
		$listener = $this->getListener();
		if (!is_object($listener)) {
			$listener = new listener();
			$listener->setClass(__CLASS__);
			$listener->setFunction('listenerHandler');
			$listener->setOption(array('id' => $this->getId()));
		}
		$listener->emptyEvent();
		foreach (['w', 's'] as $type) {
			$logicalId = $type . ':state';
			$cmd = $this->getCmd('info',$logicalId);
			if (is_object($cmd)) {
				$listener->addEvent($cmd->getId());
			}
			$listener->save();
		}
	}

	public function refresh() {
		$cmd = $this->getCmd('action','refresh');
		if (is_object($cmd)) {
			$cmd->execCmd();
		}
	}

	public function setPause ($pause) {
		$this->checkAndUpdateCmd('pause',$pause);
	}

	public function getPause() {
		$cmd = $this->getCmd('info','pause');
		return ($cmd->execCmd() == 1);
	}

	public function isRaining() {
		return $this->getCmd('info','rain')->execCmd() == 1;
	}

	/*
	 * Retoune les consignes de position qui ont été enregistrées dans le
	 * cache de l'équipement
	 */
	public function getConsignes () {
		$consignes = $this->getCache('consignes');
		return $consignes;
	}

	/*
	 * Modification d'une consigne de position. Les nouvelles consignes
	 * sont mise dans le cache
	 */
	public function setConsignes ($_consignes) {
		log::add("velux","debug","┌setConsignes (". json_encode($_consignes) . ")");
		$consignes = $this->getConsignes();
		if (!is_array($consignes)) {
			$consignes = [
				'w' => -1,
				's' => -1
			];
		}
		foreach ($_consignes as $eq => $value) {
			$consignes[$eq] = $_consignes[$eq];
		}
		$this->setCache('consignes',$consignes);
		log::add("velux","debug","└Consignes: " . json_encode($consignes));
	}

	/*
	 * Indique si un eq (fenêtre ou store) est en mouvement
	 */
	public function isMoving($_eq = null) {
		if ($_eq == null) {
			$eqs = array('w','s');
		} elseif (is_array($_eq)) {
			$eqs = $_eq;
		} else {
			$eqs = array($_eq);
		}
		foreach ($eqs as $eq) {
			$cmd = $this->getCmd('info',$eq . ":state");
			if ( $cmd->execCmd() != 2) {
				return true;
			}
		}
		return false;
	}

	/*
	 * Retourne la position des eq (fenêtre et store)
	 */
	public function getPositions() {
		$positions = [
			'w' => null,
			's' => null
		];
		foreach (['w', 's'] as $eq) {
			$cmd = $this->getCmd('info',$eq . ':position');
			if (is_object($cmd)) {
				$positions[$eq] = $cmd->execCmd();
			}
		}
		return $positions;
	}

	public function moveEq($eq, $position) {
		$veluxCmd = $this->getCmd('action',$eq . ":target_action");
		$eqCmd_id = str_replace('#','', $veluxCmd->getConfiguration('linkedCmd'));
		$eqCmd = hkControlCmd::byId($eqCmd_id);
		$this->setCache("target",$position);
		$eqCmd->execCmd(['slider'=>$position]);
	}

	/*
	 * Sélectionne et lance un mouvement en fonction des consignes
	 */
	public function doMove() {
		log::add("velux","info","┌doMove()");
		if ($this->getPause()) {
			log::add("velux","info","└" . sprintf(__('%s est en pause',__FILE__),$this->getHumanName()));
			return;
		}
		$consigne = $this->getConsignes();
		$position = $this->getPositions();
		log::add("velux","debug","│consignes: " . json_encode($consigne));
		log::add("velux","debug","│positions: " . json_encode($position));

		foreach (['s', 'w'] as $eq) {
			if ($consigne[$eq] == $position[$eq]) {
				$consigne[$eq] = -1;
			}
		}

		if ($consigne['s'] >= 0) {
			if ($position['s'] == $consigne['s']) {
				log::add("velux","info","│" . __("Pas de mouvement nécessaire pour le store.",__FILE__));
				$this->setConsignes(['s' => -1]);
			} else {
				// Le fenêtre est suffisament fermée pour un mouvement libre du store
				if ($position['w'] <= $this->getConfiguration('windowsLimit')) {
					log::add("velux","info","└" . __("La fenêtre est fermée, on déplace le store.",__FILE__));
					$this->moveEq('s',$consigne['s']);
					return;
				}
				// Le mouvement du store ne sera pas gêné par la fenêtre
				if ($position['s'] > $this->getConfiguration('shuttersLimit') and $consigne['s'] > $this->getConfiguration('shuttersLimit')) {
					log::add("velux","info","└" . __("La fenêtre est ouverte, on peut déplacer le store dans la partie suppérieure.",__FILE__));
					$this->moveEq('s',$consigne['s']);
					return;
				}
				// La fenêtre doit être fermée pour permettre le mouvement du store dans la partie inférieure
				log::add("velux","info","└" . __("On doit fermer la fenêtre avant de pouvoir déplacer le store dans la partie inférieure.",__FILE__));
				$this->setCache('w_return_to_position',$position['w']);
				$this->moveEq('w',$this->getConfiguration('windowsLimit'));
				return;
			}
		} else {
			log::add("velux","info","│" . __("Pas de mouvement requis pour le store.",__FILE__));
		}

		if ($consigne['w'] >= 0) {
			if ($position['w'] == $consigne['w']) {
				log::add("velux","info","└" . __("Pas de mouvement nécessaire pour la fenêtre.",__FILE__));
				$this->setConsignes(['s' => -1]);
			} else {
				log::add("velux","info","└" . __("Positionnement de la fenêtre.",__FILE__));
				$this->moveEq('w',$consigne['w']);
			}
		} else {
			log::add("velux","info","└" . __("Pas de mouvement requis pour la fenêtre.",__FILE__));
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
		if (substr($this->getLogicalId(),-12) == ':target_info'){
			$this->setConfiguration('repeatEventManagement','always');
		}
		if ($this->getType() == 'info') {
			$string = $this->getConfiguration('linkedCmd') . $this->getConfiguration('calcul');
			preg_match_all("/#([0-9]+)#/", $string, $matches);
			$added_value = [];
			$value = '';
			foreach ($matches[1] as $cmd_id) {
				if (is_numeric($cmd_id)) {
					$cmd = self::byId($cmd_id);
					if (is_object($cmd) && $cmd->getType() == 'info') {
						if (isset($added_value[$cmd_id])){
							continue;
						}
						$value .= '#' . $cmd_id . '#';
						$added_value[$cmd_id] = $cmd_id;
					}
				}
			}
			preg_match_all("/variable\((.+?)\)/", $string, $matches);
			foreach ($matches[1] as $variable) {
				if(isset($added_value['#variable(' . $variable . ')#'])){
					continue;
				}
				$value .= '#variable(' . $variable . ')#';
				$added_value['#variable(' . $variable . ')#'] = '#variable(' . $variable . ')#';
			}
			$this->setValue($value);
		}
	}

	private function splitEqLogicId() {
		$logicalId = $this->getLogicalId();
		if (strpos($logicalId,":")  === false) {
			return null;
		}
		$tokens = explode(":",$logicalId,2);
		$result = [
			"eq" => $tokens[0],
			"name" => $tokens[1]
		];
		return $result;
	}

	// Exécution d'une commande
	public function execute($_options = array()) {
		log::add("velux","debug","execute cmd " . $this->getHumanName());
		$info = $this->splitEqLogicId();
		switch ($this->getType()) {
		case 'info':
			if ($this->getLogicalId() == 'rain') {
				$eqLogic = $this->getEqLogic();
				$result = jeedom::evaluateExpression($this->getConfiguration('calcul'));
				if (is_numeric($result)) {
					if ($result != 0) {
						$result = 1;
					} else {
						$result = 0;
					}
				} elseif (is_string($result)){
					$result = str_replace('"', '', $result);
					$result = $result == '0' ? 0 : 1;
				} elseif (is_bool($result)) { 
					$result = $result ? 1 : 0;
				} else {
					$result = 1;
				}
				if ($result == 1) {
					$wPosLimit = $eqLogic->getConfiguration('windowsLimit');
					$need2Close = false;
					if ($eqLogic->getPositions()['w'] > $wPosLimit) {
						$need2Close = true;
					} elseif ($eqLogic->getConsignes()['w'] > $wPosLimit) {
						$need2Close = true;
					}
					if ($need2Close) {
						$cmd = $eqLogic->getCmd('action','w:target_action');
						if (is_object($cmd)){
							$_options['slider'] = $wPosLimit;
							$cmd->execute($_options);
						}
					}

				}
				return $result;
			}
			return jeedom::evaluateExpression($this->getConfiguration('linkedCmd'));
			break;
		case 'action':
			if ($this->getLogicalId() == 'refresh') {
				$eqConfiguration = $this->getEqLogic()->getConfiguration();
				/* Refresh de chaque equipement HK */
				foreach ($eqConfiguration as $key => $value) {
					if ( substr($key, -5) != ':hkId' ) {
						continue;
					}
					$value = str_replace('#','', str_replace('eqLogic','',$value));
					$eqHk = hkControl::byId($value);
					if (is_object($eqHk)) {
						$cmd = $eqHk->getCmd('action','refresh');
						if (is_object($cmd)) {
							$cmd->execute($_options);
						}
					}
				}
				/* Synchronisation des cmd de type 'info' avec la commande liée */
				try {
					foreach ($this->getEqLogic()->getCmd('info') as $cmd) {
						if ($cmd->getConfiguration('calcul') == '' && $cmd->getConfiguration('linkedCmd') == '') {
							continue;
						}
						$value = $cmd->execute();
						if ($cmd->execCmd() != $cmd->formatValue($value)) {
							$cmd->event($value);
						}
					}

				} catch (Exception $exc) {
					log::add('velux','error',__('Erreur pour',__FILE__) . ' ' . $this->getEqLogic()->getHumanName() . ' : ' . $exc->getMessage());
				}
				return;
			};
			if ($this->getLogicalId() == 'pause_on') {
				$this->getEqLogic()->setPause(1);
				$this->getEqLogic()->setConsignes(['s'=>-1,'w'=>-1]);
				return;
			}
			if ($this->getLogicalId() == 'pause_off') {
				$this->getEqLogic()->setPause(0);
				$this->getEqLogic()->refresh();
				return;
			}
			if ($info['name'] == 'target_action') {
				$eqLogic = $this->getEqLogic();

				// Limitation de l'ouverture de la fenêtre en cas de pluie
				if ($info['eq'] == 'w') {
					$wPosLimit = $eqLogic->getConfiguration('windowsLimit');
					if ($eqLogic->isRaining() and ($_options['slider'] > $wPosLimit)) {
						$_options['slider'] = $wPosLimit;
						$cmd = $eqLogic->getCmd('info','w:target_info');
						if (is_object($cmd)) {
							$cmd->event($wPosLimit);
						}
					}
				}

				$eqLogic->setConsignes([$info['eq'] => $_options['slider']]);
				$eqLogic->doMove();
				return;
			}
			if ($info['name'] == 'identify') {
				$cmd_id = str_replace("#","",$this->getConfiguration('linkedCmd'));
				$cmd = cmd::byId($cmd_id);
				if (is_object($cmd)) {
					$cmd->execCmd();
				}
			}
			if ($this->getLogicalId() == 'target') {
				$eqLogic = $this->getEqLogic();
				$target = [];
				foreach (['s','w'] as $eq) {
					$pos = $this->getConfiguration($eq . ':target');
					if ($pos !== '') {
						$cmd = $eqLogic->getCmd('action',$eq . ':target_action');
						if (is_object($cmd)){
							$_options['slider'] = $pos;
							$cmd->execute($_options);
						}
					}
				}
				return;
			}
		}
	}

	/*     * **********************Getteur Setteur*************************** */
}

