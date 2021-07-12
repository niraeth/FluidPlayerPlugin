<?php
namespace FluidPlayerPlugin;

define('FP_BASE_PATH', realpath(dirname(__FILE__)));

if( !function_exists("fp_autoloader") ) 
{
	function fp_autoloader($class)
	{
		// make sure class is in its own namespace
		//echo "class Loading : " . $class . "<br/>";
		//echo "namespace : " . __NAMESPACE__ . "<br/>";
		
		if( stristr($class, 'FluidPlayerPlugin') ) {
			$class = str_replace('FluidPlayerPlugin\\', '', $class);
			//echo "final class loading : " . $class . "<br/>";
		} else {
			// The caller class doesn't belong to FluidPlayerPlugin namespace
			return; 
		}
		
		$subdirs = ['/', '/lib/', '/classes/'];
		foreach($subdirs as $subdir)
		{
			$filename = FP_BASE_PATH . $subdir . str_replace('\\', '/', $class) . '.php';
			//echo "FluidPlayerPlugin Loading : " . $filename . "<br/>";
			if( file_exists($filename) ) {
				include_once($filename);
				//echo "loaded <br/>";
			}		
		}
	}
	spl_autoload_register( __NAMESPACE__ . '\\fp_autoloader');
}

?>