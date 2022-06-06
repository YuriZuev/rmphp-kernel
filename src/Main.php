<?php
/**
 * Created by PhpStorm.
 * User: Zuev Yuri
 * Date: 26.03.2021
 * Time: 14:40
 */

namespace Rmphp\Kernel;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Rmphp\Foundation\TemplateInterface;

class Main {

	protected static ContainerInterface $container;
	protected static TemplateInterface $template;
	protected static LoggerInterface $logger;
	protected static ServerRequestInterface $request;
	private static array $innerDump = [];
	private static bool $debugMode = false;

	/**
	 * @return ServerRequestInterface
	 */
	final public function request() : ServerRequestInterface {
		return self::$request;
	}

	/**
	 * @return ContainerInterface
	 */
	final public function container() : ContainerInterface {
		if(empty(self::$container)) self::debug("Application config without countainer", "error");
		return self::$container;
	}

	// TODO: Нужно организовать возможность использования других шаблонизаторов (сейчас это возможно только через адаптер)
	/**
	 * @return TemplateInterface
	 */
	final public function template() : TemplateInterface {
		if(empty(self::$template)) self::debug("Application config without template", "error");
		return self::$template;
	}

	/**
	 * @return LoggerInterface
	 */
	final public function logger() : LoggerInterface {
		if(empty(self::$logger)) self::debug("Application config without logger", "error");
		return self::$logger;
	}

	/**
	 * @param $innerDump
	 * @param string $key
	 */
	final public function debug($innerDump, string $key = ""): void {
		if(self::$debugMode){
			if(empty($key)) $key = get_class($this);
			self::$innerDump[$key][]=$innerDump;
		}
	}

	/**
	 * @param string $key
	 * @return array|null
	 */
	final public function getDebugList(string $key = ""): array {
		$out = [];
		if(self::$debugMode){
			if(!empty($key)){
				$out[$key] = (empty(self::$innerDump[$key])) ? [] : ((count(self::$innerDump[$key])==1) ? self::$innerDump[$key] : self::$innerDump[$key][0]);
			} else {
				foreach (self::$innerDump as $key => $dumpItem){
					$out[$key] = (count($dumpItem)==1) ? $dumpItem[0] : $dumpItem;
				}
			}
		}
		return $out;
	}

	/**
	 * @param bool $debugMode
	 */
	final public function setDebugMode(bool $debugMode): void {
		self::$debugMode = $debugMode;
	}

	/**
	 * @return bool
	 */
	final public function isDebugMode(): bool {
		return self::$debugMode;
	}
}