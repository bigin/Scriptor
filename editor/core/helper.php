<?php 
namespace Scriptor;

class Helper 
{
	public static function isCallable($f) 
	{
		return (is_string($f) && function_exists($f)) || (is_object($f) && ($f instanceof \Closure));
	}

	/**
	 * Get the classname without the namespace
	 */
	public static function rawClassName($classname)
	{
		if($pos = strrpos($classname, '\\')) return substr($classname, $pos + 1);
		return $pos;
	}   
}