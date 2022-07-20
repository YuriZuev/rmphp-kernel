<?php
/**
 * Created by PhpStorm.
 * User: Zuev Yuri
 * Date: 26.03.2021
 * Time: 14:40
 */

namespace Rmphp\Kernel;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Rmphp\Foundation\TemplateInterface;

class Main {

	private static ContainerInterface $container;
	private static TemplateInterface $template;
	private static LoggerInterface $logger;
	private static Globals $globals;
	private static array $innerDump = [];
	private static bool $debugMode = false;


	/**
	 * @param ServerRequestInterface $request
	 * @param ResponseInterface $response
	 * @return void
	 */
	protected function setGlobals(ServerRequestInterface $request, ResponseInterface $response) : void {
		self::$globals = new Globals($request, $response);
	}

	/**
	 * @param ContainerInterface $container
	 * @return void
	 */
	protected function setContainer(ContainerInterface $container) : void {
		self::$container = $container;
	}

	/**
	 * @param TemplateInterface $template
	 * @return void
	 */
	protected function setTemplate(TemplateInterface $template) : void {
		self::$template = $template;
	}

	/**
	 * @param LoggerInterface $logger
	 * @return void
	 */
	protected function setLogger(LoggerInterface $logger) : void {
		self::$logger = $logger;
	}



	/**
	 * @return Globals
	 */
	final public function globals() : Globals {
		return self::$globals;
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
	 * @return array
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