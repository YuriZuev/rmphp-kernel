<?php

namespace Rmphp\Kernel;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Rmphp\Foundation\TemplateInterface;

class Main {

	private static ?Logger $syslogger = null;
	private static ?Globals $globals = null;
	private static ?ContainerInterface $container = null;
	private static ?TemplateInterface $template = null;
	private static ?LoggerInterface $logger = null;

	/**
	 * @return Logger
	 */
	final public function syslogger() : Logger {
		if(!isset(self::$syslogger) && class_exists(Logger::class)) {
			self::$syslogger = new Logger();
		}
		return self::$syslogger;
	}

	/**
	 * @param ServerRequestInterface $request
	 * @param ResponseInterface $response
	 * @return void
	 */
	protected function setGlobals(ServerRequestInterface $request, ResponseInterface $response) : void {
		if(!isset(self::$globals) && class_exists(Globals::class)) {
			self::$globals = new Globals($request, $response);
		}
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
	 * @return Globals|null
	 */
	final public function globals() : ?Globals {
		return self::$globals;
	}

	/**
	 * @return ContainerInterface|null
	 */
	final public function container() : ?ContainerInterface {
		if(empty(self::$container)) $this->syslogger()->warning("Application config without countainer");
		return self::$container;
	}

	/**
	 * @return TemplateInterface|null
	 */
	final public function template() : ?TemplateInterface {
		if(empty(self::$template)) $this->syslogger()->warning("Application config without template");
		return self::$template;
	}

	/**
	 * @return LoggerInterface|null
	 */
	final public function logger() : ?LoggerInterface {
		if(empty(self::$logger)){
			$this->syslogger()->warning("Application config without logger");
		}
		return self::$logger;
	}
}