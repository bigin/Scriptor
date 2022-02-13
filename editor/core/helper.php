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

	/**
	 * Comparison function 
	 * used in sorting methods
	 * 
	 * @param array $a Operand
	 * @param array $b Operand
	 * 
	 * @return integer
	 */
	public static function order($a, $b)
	{
		if($a['position'] == $b['position']) return 0;
		return ($a['position'] < $b['position']) ? -1 : 1;
	}
}