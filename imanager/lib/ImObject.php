<?php namespace Imanager;

use \AllowDynamicProperties;

#[AllowDynamicProperties]
class ImObject
{
	protected $imanager = null;

	public function ___init() 
	{
		$this->imanager = imanager();
	}
}