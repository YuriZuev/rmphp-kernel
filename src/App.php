<?php
/**
 * Created by PhpStorm.
 * User: Zuev Yuri
 * Date: 15.03.2021
 * Time: 3:04
 */

declare(strict_types = 1);

namespace Rmphp\Kernel;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\Response\HtmlResponse;
use Rmphp\Foundation\Exceptions\AppException;
use Rmphp\Foundation\RouterInterface;
use Rmphp\Foundation\TemplateInterface;
use Rmphp\Foundation\MatchObject;


class App extends Main {

	private string $baseDir;
	private string $configFile;
	private array $config = [];
	private array $appNodes = [];
	private RouterInterface $router;

	/**
	 * @param array $config
	 * @return array
	 */
	public function setConfig(array $config) : array {
		return $this->config = $config;
	}

	/**
	 * Front constructor.
	 * @param string $configFile
	 */
	public function __construct(string $configFile = "") {
		$this->baseDir = dirname(dirname(dirname(dirname(__DIR__))));
		$this->configFile = $configFile;
	}

	/**
	 * @param ServerRequestInterface $request
	 * @return ResponseInterface
	 */
	public function handler(ServerRequestInterface $request) : ResponseInterface {
		try{
			$this->init($request);
			foreach ($this->appNodes as $appNodeKey => $appHandler){

				if(!$appHandler instanceof MatchObject) continue;
				$response = null;

				if(!empty($appHandler->className)){
					if(!class_exists($appHandler->className)) {
						$this->debug("- failed -", "nodes");
						$this->debug("Err - Class ".$appHandler->className." is not exists", "error");
						continue;
					}
					$controllers[$appNodeKey] = new $appHandler->className;
					$log = "OK - Class ".$appHandler->className;

					if(!empty($appHandler->methodName)){
						if(!method_exists($appHandler->className, $appHandler->methodName)) {
							$this->debug("- failed -", "nodes");
							$this->debug("Err - Method ".$appHandler->className."/".$appHandler->methodName." is not exists", "error");
							continue;
						}
						$response = (!empty($appHandler->params)) ? $controllers[$appNodeKey]->{$appHandler->methodName}(...$appHandler->params) : $controllers[$appNodeKey]->{$appHandler->methodName}();
						$log = "OK - Method ".$appHandler->className."/".$appHandler->methodName;
					}
					$this->debug($log);

					// 1. Если на этапе итерации уже получен ответ ResponseInterface - досрочно отдаем результат в эмиттер
					if($response instanceof ResponseInterface) {
						return $response;
					}
					elseif(is_object($response)){
						$this->set($appHandler->className, $response);
					}
					elseif($response === false) break;
				}
			}
			// 2. Если итерации закончились и задан обьект Content им создаем результат для эмиттера
			if($this->template()){
				return (!empty($this->template()->getResponse())) ? new HtmlResponse($this->template()->getResponse()) : $this->defaultPage(404);
			}
			// 3. Отдаем пустой результат
			return $this->defaultPage(404);
		}
		catch (AppException $appException){
			$this->debug("AppException: ".$appException->getMessage(), "error");
		}
		catch (\Exception $exception) {
			$this->debug("Exception: ".$exception->getMessage()." : ".$exception->getFile()." : ".$exception->getLine(), "error");
		}
		catch (\Error $error) {
			$this->debug("Error: ".$error->getMessage()." : ".$error->getFile()." : ".$error->getLine(), "error");
		}
		// 4. Отдаем ошибку без шаблона
		return $this->defaultPage(500);
	}

	/**
	 * @param int $code
	 * @return ResponseInterface
	 */
	private function defaultPage(int $code) : ResponseInterface{
		if(!empty($this->config['defaultErrorPages']) && !empty($this->config['defaultErrorPages'][$code]) && file_exists($this->baseDir.'/'.$this->config['defaultErrorPages'][$code])){
			return new HtmlResponse(file_get_contents($this->baseDir.'/'.$this->config['defaultErrorPages'][$code]), $code);
		}
		return new EmptyResponse($code);
	}

	/**
	 * @param ServerRequestInterface $request
	 * @throws AppException
	 */
	private function init(ServerRequestInterface $request) : void {

		// request init
		$this->setRequest($request);

		// config init
		if(empty($this->config)){
			$configFile = $this->baseDir.'/'.$this->configFile;
			if(!file_exists($configFile)) throw new AppException('Invalid filename');

			if(empty($this->setConfig(require_once $configFile))) {
				throw AppException::emptyConfig();
			}
		}

		// debugMode
		if(isset($this->config['debugMode'])) $this->setDebugMode($this->config['debugMode']);

		// init factories
		if(!empty($this->config['componentFactories']) && is_array($this->config['componentFactories'])){
			foreach ($this->config['componentFactories'] as $factoryName => $factoryValue){
				if(empty($factoryValue)) continue;
				if(is_object($factoryValue)){
					$factoryObject = $factoryValue;
				}
				elseif(!file_exists($this->baseDir.'/'.$factoryValue) || !is_object($factoryObject = require $this->baseDir.'/'.$factoryValue)){
					throw new AppException("Invalid obgect factory ". $factoryValue);
				}
				switch (true){
					case ($factoryObject instanceof ContainerInterface): $this->setContainer($factoryObject); break;
					case ($factoryObject instanceof TemplateInterface): $this->setTemplate($factoryObject); break;
					case ($factoryObject instanceof LoggerInterface): $this->setLogger($factoryObject); break;
					case ($factoryObject instanceof RouterInterface): $this->router = $factoryObject; break;
				}
			}
		}

		// app nodes
		if(empty($this->config['appNodes'])) throw AppException::emptyAppNodes();
		$this->getActions($this->config['appNodes']);
	}

	/**
	 * @param array $appNodes
	 */
	private function getActions(array $appNodes) : void {
		foreach ($appNodes as $appNode){

			// по умолчанию точка монтирования ровна корню
			$mountKey = (!empty($appNode['key'])) ? $appNode['key'] : '/';

			// если url начинается не с точки монтирования смотрим далее
			if (0 !== (strpos($this->request()->getUri()->getPath(), $mountKey))) continue;

			if(!empty($appNode['action'])){
				list($className, $methodName, $params) = explode("/", $appNode['action']);
				$this->appNodes[] = new MatchObject($className, $methodName, $params);
			}
			elseif(!empty($appNode['router']) && file_exists($this->baseDir."/".$appNode['router'])){
				$router = $this->router();
				$router->setStartPoint($mountKey);
				//TODO: форматы файла
				$router->withRules(yaml_parse_file($this->baseDir."/".$appNode['router']));
				$this->appNodes[] = $router->match($this->request());
			}
		}
	}

	/**
	 * @return RouterInterface
	 */
	private function router() : RouterInterface {
		if(empty($this->router)) $this->debug("Application config without router", "error");
		return $this->router;
	}

	/**
	 * @param ServerRequestInterface $request
	 */
	private function setRequest(ServerRequestInterface $request) : void {
		self::$request = $request;
	}

	/**
	 * @param ContainerInterface $container
	 */
	private function setContainer(ContainerInterface $container) : void {
		self::$container = $container;
	}

	/**
	 * @param TemplateInterface $template
	 */
	private function setTemplate(TemplateInterface $template) : void {
		self::$template = $template;
	}

	/**
	 * @param LoggerInterface $logger
	 */
	private function setLogger(LoggerInterface $logger) : void {
		self::$logger = $logger;
	}


}