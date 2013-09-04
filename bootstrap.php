<?php
/**
 * Background
 *
 * Create the background proccess sliped out from application code. 
 *
 * @package    Background
 * @version    1.0
 * @author     Shintaro Ikezaki - @hackoh
 * @license    MIT License
 * @link       http://github.com/hackoh
 */

Autoloader::add_core_namespace('Background');

Autoloader::add_classes(array(
	'Background\\Background' => __DIR__ . '/classes/background.php',
));