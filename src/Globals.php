<?php

namespace Rmphp\Kernel;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;

class Globals {

	private ServerRequestInterface $request;
	private ResponseInterface $response;
	private Session $session;

	const INT = "INT";
	const STRING = "STRING";

	/**
	 * @param ServerRequestInterface $request
	 * @param ResponseInterface $response
	 */
	public function __construct(ServerRequestInterface $request, ResponseInterface $response) {
		$this->request = $request;
		$this->response = $response;
	}



	/**
	 * @return ServerRequestInterface
	 */
	public function request() : ServerRequestInterface {
		return $this->request;
	}

	/**
	 * @return ResponseInterface
	 */
	public function response() : ResponseInterface {
		return $this->response;
	}

	/**
	 * @param ServerRequestInterface $request
	 * @return ServerRequestInterface
	 */
	public function setReqest(ServerRequestInterface $request) : ServerRequestInterface {
		$this->request = $request;
		return $this->request;
	}

	/**
	 * @param ResponseInterface $response
	 * @return ResponseInterface
	 */
	public function setResponse(ResponseInterface $response) : ResponseInterface {
		$this->response = $response;
		return $this->response;
	}



	/**
	 * @param string $name
	 * @return bool
	 */
	public function isGet(string $name = "") : bool {
		return (!empty($name)) ? isset($this->request->getQueryParams()[$name]) : !empty($this->request->getQueryParams());
	}

	/**
	 * @param string $name
	 * @return bool
	 */
	public function isPost(string $name = "") : bool {
		return (!empty($name)) ? isset($this->request->getParsedBody()[$name]) : !empty($this->request->getParsedBody());
	}

	/**
	 * @param string $name
	 * @return bool
	 */
	public function isCookie(string $name = "") : bool {
		return (!empty($name)) ? isset($this->request->getCookieParams()[$name]) : !empty($this->request->getCookieParams());
	}

	/**
	 * @param string $name
	 * @return bool
	 */
	public function isSession(string $name = "") : bool {
		if(!class_exists(Session::class)) return false;
		if(!isset($this->session)) $this->session = new Session();
		return (!empty($name)) ? isset($this->session->getSession()[$name]) : !empty($this->session->getSession());
	}

	/**
	 * @param string $name
	 * @return bool
	 */
	public function isFile(string $name = "") : bool {
		return (!empty($name)) ? isset($this->request->getUploadedFiles()[$name]) : !empty($this->request->getUploadedFiles());
	}

	/**
	 * @return bool
	 */
	public function isStream() : bool {
		return !empty($this->request->getBody()->getContents());
	}



	/**
	 * @param string $name
	 * @param string $type
	 * @return array|int|string
	 */
	public function get(string $name = "", string $type = "") {
		return $this->onGlobal($this->request->getQueryParams(), $name, $type);
	}

	/**
	 * @param string $name
	 * @param string $type
	 * @return array|int|string
	 */
	public function post(string $name = "", string $type = "") {
		return $this->onGlobal($this->request->getParsedBody(), $name, $type);
	}

	/**
	 * @param string $name
	 * @param string $type
	 * @return array|int|string
	 */
	public function cookie(string $name = "", string $type = "") {
		return $this->onGlobal($this->request->getCookieParams(), $name, $type);
	}

	/**
	 * @param string $name
	 * @param string $type
	 * @return array|int|string
	 */
	public function session(string $name = "", string $type = "") {
		if(!class_exists(Session::class)) return null;
		if(!isset($this->session)) $this->session = new Session();
		return $this->onGlobal($this->session->getSession(), $name, $type);
	}

	/**
	 * @param string $name
	 * @return array|UploadedFileInterface|null
	 */
	public function files(string $name = "") {
		$name = strtolower($name);
		$var = $this->request->getUploadedFiles();
		if (!empty($name))
		{
			if (!isset($var[$name])) return null;
			return $var[$name];
		}
		return $var;
	}

	/**
	 * @return string|null
	 */
	public function stream() {
		return !empty($this->request->getBody()->getContents()) ? $this->request->getBody()->getContents(): null;
	}



	/**
	 * @param string $name
	 * @param string $value
	 * @return void
	 */
	public function addHeader(string $name, string $value) : void {
		$this->setResponse($this->response->withAddedHeader($name, $value));
	}

	/**
	 * @param string $name
	 * @param $value
	 * @return void
	 */
	public function setSession(string $name, $value = null) : void {
		if(class_exists(Session::class)) {
			if(!isset($this->session)) $this->session = new Session();
			$this->session->setSession($name, $value);
		}
	}

	/**
	 * @param string $name
	 * @param string $value
	 * @param int $expires
	 * @param string $path
	 * @param string $domain
	 * @param bool $secure
	 * @param bool $httponly
	 * @return void
	 */
	public function setCookie(string $name, string $value="", int $expires = 0, string $path = "", string $domain = "", bool $secure = false, bool $httponly = false) : void {
		$cookie = [];
		$cookie[] = $name."=".((!empty($value)) ? $value : "deleted");
		if($expires != 0) {
			$cookie[] = ($expires>time()) ? "expires=".date("D, d-M-Y H:i:s", $expires)." GMT; Max-Age=".($expires-time()) : "expires=".date("D, d-M-Y H:i:s", 0)." GMT; Max-Age=0";
		}
		if(!empty($path))    $cookie[] = "path=".$path;
		if(!empty($domain))  $cookie[] = "domain=".$domain;
		if($secure)   $cookie[] = "Secure";
		if($httponly) $cookie[] = "HttpOnly";
		$this->addHeader("Set-Cookie", implode("; ", $cookie));
	}

	/**
	 * @param string|null $name
	 * @return void
	 */
	public function clearSession(string $name = null) : void{
		if(class_exists(Session::class)) {
			if(!isset($this->session)) $this->session = new Session();
			$this->session->clearSession($name);
		}
	}

	/**
	 * @param string $name
	 * @param string $path
	 * @return void
	 */
	public function clearCookie(string $name, string $path = "") : void {
		$cookie = $name."=deleted; expires=".date("D, d-M-Y H:i:s", 0)." GMT; Max-Age=0";
		if(!empty($path)) $cookie.="; path=".$path;
		$this->addHeader("Set-Cookie", $cookie);
	}



	/**
	 * @param array $var
	 * @param string $name
	 * @param string $type
	 * @return array|int|string
	 */
	private function onGlobal(array $var, string $name, string $type = "") {
		$name = strtolower($name);
		if (!empty($name))
		{
			if (!isset($var[$name])) return null;

			if (empty($type)) {
				return $var[$name];
			}
			elseif ($type == self::STRING) {
				return (!empty($var[$name])) ? (string)$var[$name] : null;
			}
			elseif ($type == self::INT) {
				return (!empty((int)$var[$name]) || $var[$name]==0) ? (int)$var[$name] : null;
			}
		}
		return $var;
	}

}