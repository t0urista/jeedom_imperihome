﻿<?php

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
//require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';

class imperihome {
	/*     * *************************Attributs****************************** */

	/*     * ***********************Methode static*************************** */

	public static function generateISSTemplate() {
		$ISSStructure = json_decode(file_get_contents(dirname(__FILE__) . "/../config/ISS-Structure.json"), true);
		$template = array('devices' => array());
		$cache = cache::byKey('issConfig');
		$issConfig = json_decode($cache->getValue('{}'), true);
		foreach ($issConfig as $cmd_id => $value) {
			if (!isset($value['cmd_transmit']) || $value['cmd_transmit'] != 1) {
				continue;
			}
			$cmd = cmd::byId($cmd_id);
			if (!is_object($cmd)) {
				continue;
			}
			if ($cmd->getType() != 'info') {
				continue;
			}
			$alreadyUsed[$cmd_id] = true;
			$eqLogic = $cmd->getEqLogic();
			if (!is_object($eqLogic)) {
				continue;
			}
			$object = $eqLogic->getObject();

			if (method_exists($cmd, 'imperihomeGenerate')) {
				$info_device = $cmd->imperihomeGenerate($ISSStructure, $alreadyUsed);
			} else {
				$info_device = array(
					"id" => $cmd->getId(),
					"name" => $eqLogic->getName() . ' ' . $cmd->getName(),
					"room" => (is_object($object)) ? $object->getId() : 99999,
					"type" => self::convertType($cmd),
					'params' => array(),
				);
				if (isset($value['devtype'])) {
					$info_device['type'] = $value['devtype'];
				} else {
					$info_device['type'] = self::convertType($cmd);
				}
				$cmd_params = self::generateParam($cmd, $info_device['type'], $ISSStructure);
				$info_device['params'] = $cmd_params['params'];
			}
			$template['devices'][] = $info_device;
		}
		$cache = new cache();
		$cache->setKey('issTemplate');
		$cache->setValue(json_encode($template));
		$cache->setLifetime(0);
		$cache->save();
	}

	public static function devices() {
		$cache = cache::byKey('issTemplate');
		$return = cmd::cmdToValue(json_decode($cache->getValue('{}'), true), false, true);
		foreach ($return['devices'] as &$device) {
			if ($device['type'] == 'DevRGBLight') {
				foreach ($device['params'] as &$param) {
					if ($param['key'] == 'color') {
						$param['value'] = 'FF' . str_replace(array('#', '"'), '', $param['value']);
					}
					if ($param['key'] == 'status') {
						if ($param['value'] != '"#000000"') {
							$param['value'] = 1;
						} else {
							$param['value'] = 0;
						}
					}
				}
			}
			foreach ($device['params'] as &$param) {
				if ($param['type'] == 'infoBinary' && ($param['value'] > 0 || $param['value'])) {
					$param['value'] = 1;
				}
			}
			if ($device['type'] == 'DevMultiSwitch') {
				$value = $device['params'][0]['value'];
				$choice = $device['params'][1]['value'];
				if (strpos($choice, $value) === false) {
					$choices = explode(',', $choice);
					if (isset($choices[$device['params'][0]['value'] - 1])) {
						$device['params'][0]['value'] = $choices[$device['params'][0]['value'] - 1];
					}
				}
			}
		}
		return json_encode($return);
	}

	public static function action($_cmd_id, $_action, $_value) {
		$cmd = cmd::byId($_cmd_id);
		if (method_exists($cmd, 'imperihomeAction')) {
			$cmd->imperihomeAction($_action, $_value);
			return;
		}
		if ($_action == 'setChoice') {

			if (!is_object($cmd)) {
				return;
			}
			if ($cmd->getEqType() == 'presence') {
				$eqlogic = $cmd->getEqLogic();
				$action = $eqlogic->getCmd('action', $_value);
				if (is_object($action)) {
					$action->execCmd();
				}
			}
			return;
		}
		$actions = cmd::byValue($_cmd_id, 'action');
		if (count($actions) > 0) {
			foreach ($actions as $action) {
				if ($action->getSubtype() == 'color') {
					if ($_action == 'setColor') {
						$action->execCmd(array('color' => '#' . substr($_value, 2)));
						return;
					}
					if ($_action == 'setStatus') {
						if ($_value == 0) {
							$action->execCmd(array('color' => '#000000'));
							return;
						} else {
							$action->execCmd(array('color' => '#FFFFFF'));
							return;
						}
					}
				}

				if ($action->getSubtype() == 'slider') {
					if ($_action == 'setLevel') {
						$_value = ($_value > 99) ? 99 : $_value;
						$action->execCmd(array('slider' => $_value));
						return;
					}
					if ($_action == 'setStatus') {
						if ($_value == 0) {
							$action->execCmd(array('slider' => 0));
							return;
						} else {
							$action->execCmd(array('slider' => 99));
							return;
						}
					}
				}
				if ($_action == 'setStatus' && $action->getSubtype() == 'other') {
					if ($_value == 0 && strpos(strtolower($action->getName()), 'off') !== false) {
						$action->execCmd();
						return;
					}
					if ($_value == 1 && strpos(strtolower($action->getName()), 'on') !== false) {
						$action->execCmd();
						return;
					}
				}
			}
		}
	}

	public static function generateParam($cmd, $cmdType, $ISSStructure) {
		$eqLogic = $cmd->getEqLogic();
		$return = array('params' => $ISSStructure[$cmdType]['params']);

		foreach ($return['params'] as &$param) {
			if ($param['type'] == 'optionBinary') {
				continue;
			}
			$param['value'] = ($cmd->getType() == 'info') ? '#' . $cmd->getId() . '#' : '';
			if (isset($param['unit'])) {
				$param['unit'] = $cmd->getUnite();
			}
			if (isset($param['graphable'])) {
				$param['graphable'] = ($cmd->getIsHistorized() == 1) ? true : false;
			}
			if (($cmdType == 'DevSwitch' || $cmdType == 'DevRGBLight' || $cmdType == 'DevDimmer') && $param['key'] == 'energy') {
				$param['value'] = 0;
				foreach ($cmd->getEqLogic()->getCmd('info') as $info) {
					if (strtolower($info->getUnite()) == 'w') {
						$param['unit'] = $info->getUnite();
						$param['value'] = '#' . $info->getId() . '#';
						$param['graphable'] = false;
						break;
					}
				}
			}
			if ($cmdType == 'DevElectricity' && $param['key'] == 'consototal') {
				$param['value'] = 0;
				foreach ($cmd->getEqLogic()->getCmd('info') as $info) {
					if (strtolower($info->getUnite()) == 'kwh') {
						$param['unit'] = $info->getUnite();
						$param['value'] = '#' . $info->getId() . '#';
						$param['graphable'] = false;
						break;
					}
				}
			}
			if ($cmd->getEqType() == 'presence' && $param['key'] == 'choices') {
				$param['value'] = 'Présent,Absent,Nuit,Travail,Vacances';
			}
		}
		return $return;
	}

	public function convertType($cmd) {
		switch ($cmd->getEqType()) {
			case "thermostat":
				return 'DevThermostat';
			case "presence":
				return 'DevMultiSwitch';
			case "camera":
				return 'DevCamera';
			case 'Store':
				return 'DevShutter';
		}
		if (strpos(strtolower($cmd->getName()), 'off') !== false) {
			return 'DevSwitch';
		}
		if (strpos(strtolower($cmd->getTemplate('dashboard')), 'door') !== false) {
			return 'DevDoor';
		}
		if (strpos(strtolower($cmd->getTemplate('dashboard')), 'window') !== false) {
			return 'DevDoor';
		}
		if (strpos(strtolower($cmd->getTemplate('dashboard')), 'porte_garage') !== false) {
			return 'DevDoor';
		}
		if (strpos(strtolower($cmd->getTemplate('dashboard')), 'presence') !== false) {
			return 'DevMotion';
		}
		if (strpos(strtolower($cmd->getTemplate('dashboard')), 'store') !== false) {
			return 'DevShutter';
		}
		if (strpos(strtolower($cmd->getTemplate('dashboard')), 'fire') !== false) {
			return 'DevSmoke';
		}
		if (strpos(strtolower($cmd->getTemplate('dashboard')), 'light') !== false) {
			return 'DevDimmer';
		}
		if (strpos(strtolower($cmd->getName()), __('humidité', __FILE__)) !== false) {
			return 'DevHygrometry';
		}
		if (strtolower($cmd->getName()) == __('uv', __FILE__)) {
			return 'DevUV';
		}
		$eqlogic = $cmd->getEqLogic();
		if (strpos(strtolower($cmd->getName()), __('etat', __FILE__)) !== false) {
			if (strpos(strtolower($eqlogic->getName()), __('fenêtre', __FILE__)) !== false || strpos(strtolower($eqlogic->getName()), __('fenetre', __FILE__)) !== false || strpos(strtolower($eqlogic->getName()), __('porte', __FILE__)) !== false) {
				return 'DevDoor';
			}
		}

		switch ($cmd->getSubtype()) {
			case 'numeric':
				switch (strtolower($cmd->getUnite())) {
				case '°c':
						return 'DevTemperature';
				case '%':
						return 'DevDimmer';
				case 'pa':
						return 'DevPressure';
				case 'db':
						return 'DevNoise';
				case 'km/h':
						return 'DevWind';
				case 'mm/h':
						return 'DevRain';
				case 'mm':
						return 'DevRain';
				case 'ppm':
						return 'DevCO2';
				case 'lux':
						return 'DevLuminosity';
				case 'w':
						return 'DevElectricity';
				case 'kwh':
						return 'DevElectricity';
				}
				return 'DevGenericSensor';
			case 'binary':
				return 'DevSwitch';

		}
		foreach ($eqlogic->getCmd() as $cmd) {
			if ($cmd->getSubtype() == 'color') {
				return 'DevRGBLight';
			}
		}
		if ($cmd->getType() == 'action') {
			return 'DevSwitch';
		}
		return 'DevGenericSensor';
	}

	public function rooms() {
		$response = array();
		foreach (object::all() as $object) {
			$response[] = array(
				'id' => $object->getId(),
				'name' => $object->getName(),
			);
		}
		$response[] = array(
			'id' => 99999,
			'name' => __('Aucun', __FILE__),
		);
		return json_encode(array("rooms" => $response));
	}

	public function system() {
		return json_encode(array('id' => config::byKey('api'), 'apiversion' => "1"));
	}

	public function history($_cmd_id, $_paramKey, $_startdate, $_enddate) {
		$cmd = cmd::byId($_cmd_id);
		$history = array();
		foreach ($cmd->getHistory(date('Y-m-d H:i:s', ($_startdate / 1000)), date('Y-m-d H:i:s', ($_enddate / 1000))) as $histoItem) {
			$history[] = array('value' => floatval($histoItem->getValue()), 'date' => strtotime($histoItem->getDatetime()) * 1000);
		}
		return array('values' => $history);
	}

/*     * **********************Getteur Setteur*************************** */
}