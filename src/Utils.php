<?php
/**
 * Created by PhpStorm.
 * User: Zuev Yuri
 * Date: 01.10.2021
 * Time: 2:31
 */

namespace Rmphp\Kernel;


class Utils {

	public static function addShutdownInfo() : void {
		register_shutdown_function(function(){
			$finish = array_sum(explode(' ', microtime()));
			echo "<pre>"; print_r(error_get_last()); echo "</pre>";
			echo "Время генерации: ".substr((string)($finish-$_SERVER['REQUEST_TIME_FLOAT']), 0, 10)."&nbsp;сек.<br>";
			echo "Объем памяти: ".round((memory_get_usage()),2)."&nbsp;байт.<br>";
			echo "Выделено памяти в пике: ".round((memory_get_peak_usage()),2)."&nbsp;байт.<br>";
		});
	}

}