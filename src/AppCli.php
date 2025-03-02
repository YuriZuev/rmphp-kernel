<?php

declare(strict_types = 1);

namespace Rmphp\Kernel;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Rmphp\Foundation\Exceptions\AppError;
use Rmphp\Foundation\Exceptions\AppException;
use Rmphp\Foundation\RouterInterface;
use Rmphp\Foundation\MatchObject;


class AppCli extends Main {

	private string $baseDir;
	private array $appRoutes = [];
	private RouterInterface $router;


	public function __construct() {
		$this->baseDir = dirname(__DIR__, 4);
	}

	/**
	 * @param ServerRequestInterface $request
	 * @param ResponseInterface $response
	 * @return ResponseInterface
	 */
	public function handler(ServerRequestInterface $request, ResponseInterface $response) : ResponseInterface {
		try{
			$this->setGlobals($request, $response);
			$this->init();
			$this->syslogger()->dump("Request", $request);
			$this->syslogger()->dump("Router", $this->router);
			$this->syslogger()->dump("routes", $this->appRoutes);

			foreach ($this->appRoutes as $appRouteKey => $appHandler){

				if(!$appHandler instanceof MatchObject) continue;
				$handlerResponse = null;

				if(!empty($appHandler->className)){
					if(!class_exists($appHandler->className)) {
						$logs[] = "Err - Class ".$appHandler->className." is not exists";
						continue;
					}
					$controllers[$appRouteKey] = ($this->container() instanceof ContainerInterface) ? $this->container()->get($appHandler->className) : new $appHandler->className;
					$log = "Class ".$appHandler->className;

					if(!empty($appHandler->methodName)){
						if(!method_exists($appHandler->className, $appHandler->methodName)) {
							$logs[] = "Err - Method ".$appHandler->className."/".$appHandler->methodName." is not exists";
							continue;
						}
						$handlerResponse = (!empty($appHandler->params)) ? $controllers[$appRouteKey]->{$appHandler->methodName}(...$appHandler->params) : $controllers[$appRouteKey]->{$appHandler->methodName}();
						$log = "Method ".$appHandler->className."/".$appHandler->methodName;
					}
					$logs[] = "OK - ".$log;

					if($handlerResponse instanceof ResponseInterface) {
						return $handlerResponse;
					}
					elseif($handlerResponse === false) break;
				}
			}
			if(!isset($handlerResponse)) {
				return (isset($logs)) ? $this->defaultPage(implode(PHP_EOL, $logs)) : $this->defaultPage("Скрипт не найден");
			}
			return $this->globals()->response();
		}
		catch (AppException $appException){
			if($this->logger()) $this->logger()->warning($appException->getMessage()." on ".$appException->getFile().":".$appException->getLine());
			$this->syslogger()->warning("AppException: ".$appException->getMessage());
		}
		catch (\Exception|ContainerExceptionInterface $exception) {
			if($this->logger()) $this->logger()->warning($exception->getMessage()." on ".$exception->getFile().":".$exception->getLine());
			$this->syslogger()->warning("Exception: ".$exception->getMessage()." : ".$exception->getFile()." : ".$exception->getLine());
		}
		catch (AppError $appError){
			if($this->logger()) $this->logger()->error($appError->getMessage()." on ".$appError->getFile().":".$appError->getLine());
			$this->syslogger()->error("Error: ".$appError->getMessage()." : ".$appError->getFile()." : ".$appError->getLine());
		}
		catch (\Error $error) {
			if($this->logger()) $this->logger()->error($error->getMessage()." on ".$error->getFile().":".$error->getLine());
			$this->syslogger()->error("Error: ".$error->getMessage()." : ".$error->getFile()." : ".$error->getLine());
		}
		/**
		 * Отдаем после ошибки
		 */
		return $this->defaultPage('Ошибка при выполнении');
	}

	/**
	 * @param string $error
	 * @return ResponseInterface
	 */
	private function defaultPage(string $error) : ResponseInterface{
		$this->globals()->response()->getBody()->write($error);
		return $this->globals()->response();
	}

	/**
	 * @return void
	 * @throws AppException
	 */
	private function init() : void {
		// init factories
		if(is_file($this->baseDir."/".getenv("APP_COMPONENTS_FILE"))){
			$components = include_once $this->baseDir."/".getenv("APP_COMPONENTS_FILE");
			if(!empty($components) && is_array($components)){
				foreach ($components as $componentName => $componentValue){
					if(empty($componentValue)) {
						continue;
					}
					elseif(is_object($componentValue)){
						$componentObject = $componentValue;
					}
					elseif(!file_exists($this->baseDir.'/'.$componentValue) || !is_object($componentObject = require $this->baseDir.'/'.$componentValue)){
						throw AppException::invalidObject($componentValue);
					}
					switch (true){
						case ($componentObject instanceof ContainerInterface): $this->setContainer($componentObject); break;
						case ($componentObject instanceof LoggerInterface): $this->setLogger($componentObject); break;
						case ($componentObject instanceof RouterInterface): $this->router = $componentObject; break;
					}
				}
			}
		}
		// app nodes
		if(is_file($this->baseDir."/".getenv("CLI_NODES_FILE"))){
			$nodes = include_once $this->baseDir."/".getenv("CLI_NODES_FILE");
		}
		elseif(is_file($this->baseDir."/".getenv("APP_NODES_FILE"))){
			$nodes = include_once $this->baseDir."/".getenv("APP_NODES_FILE");
		}
		if(empty($nodes) || !is_array($nodes)) throw AppException::emptyAppNodes();
		$this->getActions($nodes);
	}

	/**
	 * @param array $appNodes
	 */
	private function getActions(array $appNodes) : void {

		foreach ($appNodes as $appNode){

			// по умолчанию точка монтирования от корня
			$mountKey = (!empty($appNode['key'])) ? $appNode['key'] : "";

			// если url начинается не с точки монтирования смотрим далее
			if (0 !== (strpos($this->globals()->request()->getServerParams()['argv'][1], $mountKey))) continue;

			if(!empty($appNode['action'])){
				$className  = $appNode['action'];
				$methodName = $appNode['method'];
				$this->appRoutes[] = new MatchObject($className, $methodName);
			}
			elseif(!empty($appNode['router']) && is_array($appNode['router'])){
				if(empty($this->router)) throw AppError::invalidRequiredObject("Application config without router");
				$this->router->setStartPoint($mountKey);
				$this->router->withSet($appNode['router']);

				$routes = $this->router->matchByArgv($this->globals()->request()) ?? [];
				foreach ($routes as $route){
					$this->appRoutes[] = $route;
				}
			}
			elseif(!empty($appNode['router']) && file_exists($this->baseDir."/".$appNode['router'])){
				if(empty($this->router)) throw AppError::invalidRequiredObject("Application config without router");
				$this->router->setStartPoint($mountKey);
				ob_start(); $routes = include_once $this->baseDir."/".$appNode['router']; ob_end_clean();
				if(is_array($routes)) $this->router->withSet($routes);

				$routes = $this->router->matchByArgv($this->globals()->request()) ?? [];
				foreach ($routes as $route){
					$this->appRoutes[] = $route;
				}
			}
		}
	}
}
