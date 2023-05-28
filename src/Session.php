<?php

namespace Rmphp\Kernel;


class Session {

	public function __construct(string $name = "usi") {
		if(session_status() == PHP_SESSION_NONE) {
			session_name($name);
			session_start();
		}
	}

	/**
	 * @return array
	 */
	public function getSession() : array {
		return $_SESSION;
	}

	/**
	 * @param string $name
	 * @param $value
	 */
	public function setSession(string $name, $value = null) : void {
		$_SESSION[$name] = $value;

	}

	/**
	 * @param string|null $name
	 * @return void
	 */
	public function clearSession(string $name = null) : void {
		if (isset($name)) unset($_SESSION[$name]);
		else $_SESSION = [];
	}
}