<?php
/*
Plugin Name: FluidPlayer
Plugin URI: https://niraeth.com/
Description: A fluidplayer plugin that integrates with your sites seamlessly
Version: 1.0
Author: Niraeth
Author URI: https://niraeth.com/
License: GPL2
*/

include "FluidPlayerCore.php";

$fpcore = new FluidPlayerCore();
$fpcore->init();

require_once __DIR__ . '/plugin-update-checker/plugin-update-checker.php';
$myUpdateChecker = Puc_v4_Factory::buildUpdateChecker(
	'http://webprojects.devenv/test/fluidplayer/plugin.json',
	__FILE__, //Full path to the main plugin file or functions.php.
	'fluidplayer'
);
?>