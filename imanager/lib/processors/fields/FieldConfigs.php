<?php namespace Imanager;


class FieldConfigs
{
	public $accept_types;

	public $max_number_of_files;

	/**
	 *
	 * @param $an_array
	 *
	 * @return FieldConfigs object
	 */
	public static function __set_state($an_array)
	{
		$_instance = new FieldConfigs();
		foreach ($an_array as $key => $val) {
			if (is_array($val)) $_instance->{$key} = $val;
			else $_instance->{$key} = $val;
		}
		return $_instance;
	}
}