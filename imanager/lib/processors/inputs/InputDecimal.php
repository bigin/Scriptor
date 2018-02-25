<?php namespace Imanager;

class InputDecimal implements InputInterface
{
	protected $value;

	protected $field;

	public $errorCode = null;

	public function __construct(Field $field)
	{
		$this->field = $field;
		$this->value = null;
		settype($this->value, 'float');
	}

	public function prepareInput($value, $sanitize = false)
	{
		// check value, only numbers and thousand separators are permitted
		if(!preg_match('/^[0-9\., ]+$/', $value)) {
			$this->errorCode = self::WRONG_VALUE_FORMAT;
			return false;
		}

		// check input required
		if(!empty($this->field->required) && !$value) {
			$this->errorCode = self::EMPTY_REQUIRED;
			return false;
		}

		// let's change value into float format
		$this->value = $this->toDecimal($value);
		if(!$this->value) {
			$this->errorCode = self::EMPTY_REQUIRED;
			return false;
		}

		// check min value
		if(!empty($this->field->minimum) && $this->value < (int)$this->field->minimum) {
			$this->errorCode = self::ERR_MIN_LENGTH;
			return false;
		}
		// check input max value
		if(!empty($this->field->maximum) && $this->value > $this->field->maximum) {
			$this->errorCode = self::ERR_MAX_LENGTH;
			return false;
		}
		return true;
	}


	public function prepareOutput($sanitize = false){return ($sanitize) ? $this->sanitize($this->value) : $this->values;}

	public static function toDecimal($money)
	{
		$cleanString = preg_replace('/([^0-9\.,])/i', '', $money);
		$onlyNumbersString = preg_replace('/([^0-9])/i', '', $money);
		$separatorsCountToBeErased = strlen($cleanString) - strlen($onlyNumbersString) - 1;
		$stringWithCommaOrDot = preg_replace('/([,\.])/', '', $cleanString, $separatorsCountToBeErased);
		$removedThousendSeparator = preg_replace('/(\.|,)(?=[0-9]{3,}$)/', '',  $stringWithCommaOrDot);
		return (float) str_replace(',', '.', $removedThousendSeparator);
	}


	protected function sanitize($value) {
		//$storedValue = ini_get('serialize_precision');
		//ini_set('serialize_precision', $this->precision);
		return imanager('sanitizer')->float($value);
		//ini_set('serialize_precision', $storedValue);
	}
}