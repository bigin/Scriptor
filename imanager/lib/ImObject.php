<?php namespace Imanager;

class ImObject
{
	protected $imanager = null;

	public function ___init() {
		$this->imanager = imanager();
	}
}