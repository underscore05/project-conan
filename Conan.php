<?php

require_once("globelabs/GlobeApi.php");
require_once("Lbs.php");
require_once("C_DB.php");

class Conan extends GlobeApi {

	private $_appConfig = array();
	
	private $_db = null;
	private $_auth = null;
	private $_sms = null;

	private $_allowedActions = array('new_sub_with_code', 'new_sub_with_token', 'seed_refcode', 'charge', 'get_messages', 'sendsms', 'register', 'locate');

	public function __construct($appId, $dbConfig) {
		parent::__construct();
		$this->_db = C_DB::get();
		$this->_db->connect("egglabs", $dbConfig['host'], $dbConfig['uname'], $dbConfig['pass']);
		$this->_db->use_db($dbConfig['db']);
		$this->setAppDetails($appId);
	}

	public function process($act, $data) {
		if(!in_array($act, $this->_allowedActions)) {
			return array('error'=>'Invalid Action');
		}
		$res = array();
		switch($act) {
			case 'new_sub_with_code':
				$res = $this->decodeSubscribersCode($data['code']);
			break;
			case 'new_sub_with_token':
				$sub = array(
					'msisdn' => $data['subscriber_number'],
					'access_token' => $data['access_token'],
				);
				$res = $this->saveAccessToken($sub);
			break;
			case 'seed_refcode':
				$res = array('refCode' => $this->generateTransid());
			break;
			case 'charge':
				$msisdn = $data['msisdn'];
				$amt = floatVal($data['amt']);
				$refCode = isset($data['refCode']) ? $data['refCode'] : $this->generateTransid();
				if($refCode) {
					$res = $this->charge($msisdn, $refCode, $amt, $data['desc']);
					if(is_string($res)) {
						$res = array('error'=>$res);
					}
					$res['refCode'] = $refCode; 	
				} else {
					$res = array('error'=>"Reference code required");
				}
			break;
			case 'sendsms':
				$msisdn = $data['msisdn'];
				$msg = $data['msg'];
				$clientCorrelator = isset($data['refCode']) ? $data['refCode'] : $this->generateTransid();
				$res = $this->sendMessage($msisdn, $msg, $clientCorrelator);
			break;
			case 'register':
				$this->register();
			break;
			case 'locate':
				$sub = $this->getSubDetails($data['msisdn']);
				$lbs = new Lbs($sub['msisdn'], $sub['access_token'], 'v1');
				return $lbs->locate();
			break;
			default:
				$res = "Invalid action";
		}
		return $res;
	}

	protected function register() {
		if($this->_auth == null) {
			$this->_auth = $this->auth($this->_appConfig['key'], $this->_appConfig['secret']);
		}
		header("location:" .$this->_auth->getLoginUrl());
		exit();
	}

	protected function generateTransid() {
		$appId = intVal($this->_appConfig['id']);
		// Important: Please take note that we wrap our new value inside LAST_INSERT_ID function
		$this->_db->update("apps", "lastRefCode=LAST_INSERT_ID(lastRefCode+1)", "id={$appId}");
		$t = mysql_insert_id();
		/*
		$this->_db->update("apps", "lastRefCode=lastRefCode+1", "id={$appId}");
		$this->_db->select("apps", "lastRefCode", "id={$appId}");
		$app = $this->_db->get_data();
		$t = $app['lastRefCode'];
		*/

		$this->_db->insert("seeds", array('transid'=>$t));
		return substr($this->_appConfig['shortcode'], 4).$t;
	}

	protected function setAppDetails($appId) {
		$this->_db->select("apps", "*", "id={$appId}");
		$this->_appConfig = $this->_db->get_data();
	}

	protected function decodeSubscribersCode($code) {
		$code = mysql_escape_string($code);
		$this->_db->select("subscribers", "*", "code='{$code}'");
		if($this->_db->get_total() == 0) {
			if($this->_auth == null) {
				$this->_auth = $this->auth($this->_appConfig['key'], $this->_appConfig['secret']);
			}
			$res = $this->_auth->getAccessToken($code);
			if(in_array('error', array_keys($res))) {
				return $res;
			} else {
				$data = array(
					'msisdn' => $res['subscriber_number'],
					'access_token' => $res['access_token'],
					'code' => $code
				);
				return $this->saveAccessToken($data);
			}
		} else {
			return "Already subscribed";
		}
	}

	protected function saveAccessToken($data) {
		$data['app_id'] = $this->_appConfig['id'];
		$this->_db->insert("subscribers", $data);
		return "success";
	}

	protected function getSubDetails($msisdn) {
		$msisdn = mysql_escape_string($msisdn);
		$appId = $this->_appConfig['id'];
		$this->_db->select("subscribers", "*", "msisdn='{$msisdn}' AND app_id={$appId}");
		$sub = $this->_db->get_data();
		return $sub;
	}

	protected function sendMessage($msisdn, $message, $clientCorrelator = null) {
		$sub = $this->getSubDetails($msisdn);
		if($sub) {
			if(!$this->_sms) {
				$this->_sms = $this->sms(substr($this->_appConfig['shortcode'], 4));
			}
			if($clientCorrelator) {
				$this->_sms->clientCorrelator = $clientCorrelator; 
			}
			return $this->_sms->sendMessage($sub['access_token'], $sub['msisdn'], $message);
		}
	}

	protected function charge($msisdn, $refCode, $amt, $desc = null) {
		$sub = $this->getSubDetails($msisdn);
		if($sub) {
			$payment = $this->payment($sub['access_token'], $sub['msisdn']);
			if($desc) {
				$payment->description = $desc;
			}
			return $payment->charge($amt, $refCode);
		} else {
			return "Mobile number is not registered";
		}
	}
}





