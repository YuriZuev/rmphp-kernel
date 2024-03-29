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
use Rmphp\Foundation\TemplateInterface;
use Rmphp\Foundation\MatchObject;


class App extends Main {

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
			$this->init($request, $response);
			$this->syslogger()->dump("Request", $request);
			$this->syslogger()->dump("Router", $this->router);
			$this->syslogger()->dump("routes", $this->appRoutes);

			foreach ($this->appRoutes as $appRouteKey => $appHandler){

				if(!$appHandler instanceof MatchObject) continue;
				$response = null;

				if(!empty($appHandler->className)){
					if(!class_exists($appHandler->className)) {
						$this->syslogger()->log("handlers", "Err - Class ".$appHandler->className." is not exists");
						continue;
					}
					$controllers[$appRouteKey] = ($this->container() instanceof ContainerInterface) ? $this->container()->get($appHandler->className) : new $appHandler->className;
					$log = "Class ".$appHandler->className;

					if(!empty($appHandler->methodName)){
						if(!method_exists($appHandler->className, $appHandler->methodName)) {
							$this->syslogger()->log("handlers", "Err - Method ".$appHandler->className."/".$appHandler->methodName." is not exists");
							continue;
						}
						$response = (!empty($appHandler->params)) ? $controllers[$appRouteKey]->{$appHandler->methodName}(...$appHandler->params) : $controllers[$appRouteKey]->{$appHandler->methodName}();
						$log = "Method ".$appHandler->className."/".$appHandler->methodName;
					}
					$this->syslogger()->log("handlers", "OK - ".$log);

					if($response instanceof ResponseInterface) {
						return $response;
					}
					elseif($response === false) break;
				}
			}
			/**
			 * Отдаем пустой результат
			 */
			return $this->defaultPage(404);
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
			if($this->logger()) $this->logger()->warning($appError->getMessage()." on ".$appError->getFile().":".$appError->getLine());
			$this->syslogger()->error("Error: ".$appError->getMessage()." : ".$appError->getFile()." : ".$appError->getLine());
		}
		catch (\Error $error) {
			if($this->logger()) $this->logger()->error($error->getMessage()." on ".$error->getFile().":".$error->getLine());
			$this->syslogger()->error("Error: ".$error->getMessage()." : ".$error->getFile()." : ".$error->getLine());
		}
		/**
		 * Отдаем после ошибки
		 */
		return $this->defaultPage(501);
	}

	/**
	 * @param int $code
	 * @return ResponseInterface
	 */
	private function defaultPage(int $code) : ResponseInterface{
		if(is_file($this->baseDir.'/'.getenv("PAGE".$code))){
			$this->globals()->response()->getBody()->write(file_get_contents($this->baseDir.'/'.getenv("PAGE".$code)));
		}
		return $this->globals()->response()->withStatus($code);
	}

	/**
	 * @param ServerRequestInterface $request
	 * @param ResponseInterface $response
	 * @return void
	 * @throws AppException
	 */
	private function init(ServerRequestInterface $request, ResponseInterface $response) : void {

		$this->setGlobals($request, $response);

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
						case ($componentObject instanceof TemplateInterface): $this->setTemplate($componentObject); break;
						case ($componentObject instanceof LoggerInterface): $this->setLogger($componentObject); break;
						case ($componentObject instanceof RouterInterface): $this->router = $componentObject; break;
					}
				}
			}
		}

		// app nodes
		if(is_file($this->baseDir."/".getenv("APP_NODES_FILE"))){
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
			$mountKey = (!empty($appNode['key'])) ? $appNode['key'] : '/';

			// если url начинается не с точки монтирования смотрим далее
			if (0 !== (strpos($this->globals()->request()->getUri()->getPath(), $mountKey))) continue;

			if(!empty($appNode['action'])){
				$className  = $appNode['action'];
				$methodName = $appNode['method'];
				$params     = (!empty($appNode['params']) && is_string($appNode['params'])) ? explode(",",str_replace(" ", "", $appNode['params'])) : [];
				$this->appRoutes[] = new MatchObject($className, $methodName, $params);
			}
			elseif(!empty($appNode['router']) && file_exists($this->baseDir."/".$appNode['router'])){

				if(empty($this->router)) throw AppError::invalidRequiredObject("Application config without router");
				$this->router->setStartPoint($mountKey);

				if(pathinfo($this->baseDir."/".$appNode['router'])['extension'] == "php") {
					$this->router->withRules(include_once $this->baseDir."/".$appNode['router']);
				}
				elseif(pathinfo($this->baseDir."/".$appNode['router'])['extension'] == "yaml") {
					$this->router->withRules(yaml_parse_file($this->baseDir."/".$appNode['router']));
				}

				$routes = $this->router->match($this->globals()->request()) ?? [];
				foreach ($routes as $route){
					$this->appRoutes[] = $route;
				}
			}
		}
	}
}