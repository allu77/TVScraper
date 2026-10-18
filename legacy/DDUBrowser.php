<?php

#define('DDU_LOGINURL', 'http://dduniverse.net/ita/ucp.php?mode=login');
define('DDU_LOGINURL', 'https://ddunlimited.net/ucp.php?mode=login');

define('COOKIES_DDUBASENAME', 'DDUBrowser_');

require_once('SimpleBrowser.php');


class DDUBrowser extends SimpleBrowser {

	protected $username;
	protected $password;
	
	protected $lastLoginLink;


	public function setLogin($user, $pass) {
		$this->username = $user;
		$this->password = $pass;

		$this->setCookiesFile(COOKIES_DDUBASENAME . md5($user) .'.txt');
		$this->log("Cookies file set to " . $this->cookiesFile);
	}

	private function getXpath($page) {
		$dom = new DOMDocument();
		if (!@$dom->loadHTML($page)) {
			trigger_error('Cannot load HTML page', E_USER_ERROR);
		}
		$xpath = new DOMXPath($dom);
		if (! $xpath) {
			trigger_error('Cannot create XPATH handler', E_USER_ERROR);
		}

		return $xpath;
	}

	private function needsLogin($xpath) {
/*		$loginForm = $xpath->evaluate('/html/body//form[@id="login"]');
		if ($loginForm->length > 0) {
			return true;
		} else {
			$registerLink = $xpath->evaluate('/html/body//a[contains(text(), "link visibili solo agli utenti registrati")]');
			if ($registerLink->length > 0) {
				return true;
			}
		}*/
		
		$loginLink = $xpath->evaluate('/html/body//div[@id="page-header"]//a[@title="Login"]');
		if ($loginLink->length > 0) {
			$this->lastLoginLink = $loginLink->item(0)->getAttribute('href');
			return true;
		}
		
		return false;
	}

	private function getLoginParams($xpath) {
		//TODO: Handle no xpath, handle dynamic form target
		$inputElements = $xpath->evaluate('/html/body//form[@id="login"]//input');
		$params = Array();
		$params['username'] = $this->username;
		$params['password'] = $this->password;
		for ($i = 0; $i < $inputElements->length; $i++) {
			$input = $inputElements->item($i);
			$this->log('Found INPUT name=' . $input->getAttribute('name') . ' type=' . $input->getAttribute('type'));
			if ($input->getAttribute('type') == 'hidden' || $input->getAttribute('type') == 'submit') {
				$this->log("INPUT is added");
				$aName = $input->getAttribute('name');
				$aValue = $input->getAttribute('value');
				if (! array_key_exists($aName, $params)) {
					$params[$aName] = $aValue;
				} else {
					 if (! is_array($params[$aName])) {
						 $params[$aName] = array($params[$aName]);
					 }
					 $params[$aName][] = $aValue;
				}
			}
		}
		return $params;
	}

	private function login($xpath) {
	
		$loginPage = parent::get(DDU_LOGINURL, false);
		$loginXpath = $this->getXpath($loginPage);
		$params = $this->getLoginParams($loginXpath);
		
		$page = $this->post(DDU_LOGINURL, $params);

		if (! file_exists($this->cookiesFile)) {
			$this->log("Cookies file " . $this->cookiesFile . " does not exists. Can't log in.", LOGGER_ERROR);
			return false;
		}

		$this->log("User " . md5($this->username) . " login", LOGGER_INFO);

		$newXpath = $this->getXpath($page);
		return !$this->needsLogin($newXpath);
	}

	protected function getCacheFileNameOnly ($url) {
		// Per User Cache
		return md5($this->username) . '_' . parent::getCacheFileNameOnly($url);
	}

	public function get($url, $perGetUseCache = true) {
		$page = parent::get($url, $perGetUseCache);
		$xpath = $this->getXpath($page);

		if (! $this->needsLogin($xpath)) {
			$this->log("Page doesn't need re-authentication");
			return $page;
		} else {
			$this->log("Page needs re-authentication");

			$this->resetCacheForURL($url);
			if (!$this->login($xpath)) {
				$this->log("Couldn't log in", LOGGER_ERROR);
				return false;
			} else {

				$page = parent::get($url);
				$xpath = $this->getXpath($page);

				if (! $this->needsLogin($xpath)) {
					return $page;
				} else {
					$this->resetCacheForURL($url);
					$this->log("Unauthenticated page received after login. Severe error", LOGGER_ERROR);
					return false;
				}
			}
		}
	}	
}

?>
