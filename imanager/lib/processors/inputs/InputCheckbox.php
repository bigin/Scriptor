<?php namespace Imanager;

class InputCheckbox implements InputInterface
{
	public $value;

	protected $field;

	public $errorCode = null;

	public function __construct(Field $field)
	{
		$this->field = $field;
		$this->value = null;
	}

	public function prepareInput($value, $sanitize = false) {
		$this->value = ($value) ? true : false;
		return true;
	}

	public function prepareOutput() { return (boolean) $this->value; }

	protected function sanitize($value) { return (boolean) $value; }
}