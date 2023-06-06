<?php

namespace Rmphp\Kernel;

use Psr\Log\LoggerInterface;

class Logger implements LoggerInterface {

	public const DEBUG     = 'DEBUG';
	public const INFO      = 'INFO';
	public const NOTICE    = 'NOTICE';
	public const WARNING   = 'WARNING';
	public const ERROR     = 'ERROR';
	public const CRITICAL  = 'CRITICAL';
	public const ALERT 	   = 'ALERT';
	public const EMERGENCY = 'EMERGENCY';

	private static array $logs = [];

	/**
	 * Levels numbers defined in RFC 5424
	 */
	private const RFC_5424_LEVELS = [
		7 => self::DEBUG,
		6 => self::INFO,
		5 => self::NOTICE,
		4 => self::WARNING,
		3 => self::ERROR,
		2 => self::CRITICAL,
		1 => self::ALERT,
		0 => self::EMERGENCY,
	];

	/**
	 * @param \Stringable|string $message
	 * @param array $context
	 * @return void
	 */
	public function emergency(\Stringable|string $message, array $context=[]): void {
		$this->log(self::EMERGENCY, $message, $context);
	}

	/**
	 * @param \Stringable|string $message
	 * @param array $context
	 * @return void
	 */
	public function alert(\Stringable|string $message, array $context=[]): void {
		$this->log(self::ALERT, $message, $context);
	}

	/**
	 * @param \Stringable|string $message
	 * @param array $context
	 * @return void
	 */
	public function critical(\Stringable|string $message, array $context=[]): void {
		$this->log(self::CRITICAL, $message, $context);
	}

	/**
	 * @param \Stringable|string $message
	 * @param array $context
	 * @return void
	 */
	public function error(\Stringable|string $message, array $context=[]): void {
		$this->log(self::ERROR, $message, $context);
	}

	/**
	 * @param \Stringable|string $message
	 * @param array $context
	 * @return void
	 */
	public function warning(\Stringable|string $message, array $context=[]): void {
		$this->log(self::WARNING, $message, $context);
	}

	/**
	 * @param \Stringable|string $message
	 * @param array $context
	 * @return void
	 */
	public function notice(\Stringable|string $message, array $context=[]): void {
		$this->log(self::NOTICE, $message, $context);
	}

	/**
	 * @param \Stringable|string $message
	 * @param array $context
	 * @return void
	 */
	public function info(\Stringable|string $message, array $context=[]): void {
		$this->log(self::INFO, $message, $context);
	}

	/**
	 * @param \Stringable|string $message
	 * @param array $context
	 * @return void
	 */
	public function debug(\Stringable|string $message, array $context=[]): void {
		$this->log(self::DEBUG, $message, $context);
	}

	/**
	 * @param $level
	 * @param \Stringable|string $message
	 * @param array $context
	 * @return void
	 */
	public function log($level, \Stringable|string $message, array $context=[]): void {
		if(is_numeric($level) && isset(self::RFC_5424_LEVELS[$level])){
			$level = self::RFC_5424_LEVELS[$level];
		}
		if(!empty((string)$message))$in[] = $message;
		if(!empty($context)) $in[] = $context;
		if(isset($in)) self::$logs[$level][] = (count($in)==1) ? $in[0] : $in;
	}

	/**
	 * @param $level
	 * @param mixed $context
	 * @return void
	 */
	public function dump($level, mixed $context) : void {
		self::$logs[$level][] = $context;
	}

	/**
	 * @param string $key
	 * @return array
	 */
	public function getLogs($level = null) : array {
		if(is_numeric($level) && isset(self::RFC_5424_LEVELS[$level])){
			$level = self::RFC_5424_LEVELS[$level];
		}
		$out = [];
		foreach (self::$logs as $key => $logField){
			$out[$key] = (count($logField)==1) ? $logField[0] : $logField;
		}
		return isset($level) ? $out[$level] : $out;
	}
}