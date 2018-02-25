<?php namespace Imanager;

class Object
{
	protected $imanager = null;

	public function ___init() {
		$this->imanager = imanager();
	}
}