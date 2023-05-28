<?php

namespace Rmphp\Kernel;


class Utils {

	private static int $idcount = 0;
	private static string $objid;
	private static int $deep = 0;

	private static function generateCSSBlock() : void {
		?>
		<style>
		.hide-<?=self::$objid?>{display: none}
        .s-<?=self::$objid?>-h{padding: 5px 0; box-sizing: border-box; background: #0F0F0A; font: 11px Tahoma; color:#868746; font-weight: bold; border-bottom: 1px solid #323334;}
        .s-<?=self::$objid?>-h span{cursor: pointer;}
        .s-<?=self::$objid?>-b{border-left: 1px solid #606060; border-bottom: 0; padding: 0 0 0 20px; margin: 3px 0 0 0; position: relative}
        .s-<?=self::$objid?>-t{display: table; border-collapse: collapse;}
        .s-<?=self::$objid?>-tr{background: #000000; display: table-row; border-bottom: 1px solid #323334;}
        .s-<?=self::$objid?>-tс{display:table-cell; padding: 5px 25px 5px 0; font: 11px Tahoma; color:#a2a399; font-weight: bold}
        .s-<?=self::$objid?>-tс:nth-child(1){min-width:10%;}
        .s-<?=self::$objid?>-tс:nth-child(2){min-width:10%;}
        .s-<?=self::$objid?>-tс:nth-child(3){width:100%;}
        .s-<?=self::$objid?>-trs{background: #000000; display: table-row;}
        .s-<?=self::$objid?>-tсs:nth-child(1){display:table-cell; padding: 0; font: 11px Tahoma; color:#a2a399; font-weight: bold}
        .s-<?=self::$objid?>-sc{font: 11px Tahoma; color:#a2a399; font-weight: bold}
        </style>
        <?php
	}

	private static function generateJSBlock() : void{
		?>
		<script type="text/javascript">
		    document.querySelectorAll('.s-<?=self::$objid?>-h>span').forEach(i => {
                if(document.getElementById('b-'+i.id)) {
                    i.querySelector('span').innerHTML = (document.getElementById('b-'+i.id).classList.contains("hide-<?=self::$objid?>")) ? '&nbsp;&#9658;' : '&nbsp;&#9660;'
                    i.addEventListener('click', ()=>{
                        document.getElementById('b-'+i.id).classList.toggle("hide-<?=self::$objid?>");
                        i.querySelector('span').innerHTML = (document.getElementById('b-'+i.id).classList.contains("hide-<?=self::$objid?>")) ? '&nbsp;&#9658;' : '&nbsp;&#9660;'
                    })
                }
		    })
        </script>
        <?php
	}

	private static function generateStartLine(): void {
		?>
		<div style="position:relative; box-sizing: border-box; width:100%; padding:10px; background: #000000; z-index: 10000; overflow: auto;">
		<?php
	}

    private static function fromatValue(mixed $val) : mixed {
        if(is_bool($val)) return (!empty($val)) ? 'true' : 'false';
        if(is_int($val)) return htmlspecialchars($val);
        if(is_float($val)) return htmlspecialchars($val);
        if(is_string($val)) return htmlspecialchars($val);
        if(!isset($val)) return '-';
        return $val;
    }

	private static function generateHTMLBlock ($data, $title = "array"){
        if(is_array($data) || is_object($data)){
            self::$deep++;
            $id = 'id'.self::$objid.self::$idcount++;
            $display = true;
            if(is_array((array)$data) && count((array)$data) > 10) $display = false;
            if (in_array(self::$deep,[3,5])) $display = false;
            ?>
            <div class="s-<?=self::$objid?>-h">
                <span id="<?=$id?>"><?=str_replace(mb_chr(0), "-", $title)?> (<?=count((array)$data)?>) <span>&nbsp;</span></span>
            </div>
            <?php if(count((array)$data)): ?>
                <div id="b-<?=$id?>" class="s-<?=self::$objid?>-b <?=((!$display)?'hide-'.self::$objid:'')?>">
                    <table class="s-<?=self::$objid?>-t">
                        <?php foreach ((array)$data as $key => $val) : ?>
                            <?php if(is_array($val) || is_object($val)):?>
                                <tr class="s-<?=self::$objid?>-trs">
                                    <td class="s-<?=self::$objid?>-tсs" colspan="3"><?php self::generateHTMLBlock($val, "[".$key."]");?></td>
                                </tr>
                            <?php else: ?>
                                <tr class="s-<?=self::$objid?>-tr">
                                    <td class="s-<?=self::$objid?>-tс">[<?=htmlspecialchars($key)?>]</td>
                                    <td class="s-<?=self::$objid?>-tс"><?=gettype($val)?></td>
                                    <td class="s-<?=self::$objid?>-tс"><?=self::fromatValue($val)?></td>
                                </tr>
                            <?php endif;?>
                        <?php endforeach;?>
                    </table>
                </div>
            <?php endif;
            self::$deep--;
        } else {
            ?>
            <div class="s-<?=self::$objid?>-sc">
                <div>(<?=gettype($data)?>) <?=self::fromatValue($data)?></div>
            </div>
            <?php
        }
	}

	public static function addShutdownInfo(array $exData = []) : void {
		register_shutdown_function(function() use ($exData) {
			$finish = array_sum(explode(' ', microtime()));
			$info[] = error_get_last();
			$info[] = "Время генерации: ".substr((string)($finish-$_SERVER['REQUEST_TIME_FLOAT']), 0, 10)." сек.";
			$info[] = "Объем памяти: ".round((memory_get_usage()),2)." байт.";
			$info[] = "Выделено памяти в пике: ".round((memory_get_peak_usage()),2)." байт.";
            $exData['info'] = array_diff($info, array(null));

            if(in_array(\PHP_SAPI, ['cli', 'phpdbg'], true)){
                var_dump($exData);
            } else {
                self::$objid = substr(md5(time()),0,5);
                self::generateCSSBlock();
                self::generateStartLine();
                self::generateHTMLBlock($exData, "dump info");
                echo '</div>';
                self::generateJSBlock();
            }
		});
	}

    public static function dd(mixed $exData) : void {
		register_shutdown_function(function() use ($exData) {
            if(in_array(\PHP_SAPI, ['cli', 'phpdbg'], true)){
                var_dump($exData);
            } else {
                self::$objid = substr(md5(time()),0,5);
                self::generateCSSBlock();
                self::generateStartLine();
                self::generateHTMLBlock($exData);
                echo '</div>';
                self::generateJSBlock();
            }
		});
        exit;
	}
}