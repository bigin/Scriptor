<?php namespace Imanager;

class InputDecimal extends InputText implements InputInterface
{

	/**
	 * InputLongtext constructor.
	 *
	 * @param Field $field
	 */
	public function __construct(Field $field) {
		parent::__construct($field);
	}


	/**
	 * This method checks the field inputs and sets the field contents.
	 * If an error occurs, the method creates an error code.
	 *
	 * @param $value
	 * @param bool $sanitize
	 *
	 * @return boolean
	 */
    public function prepareInput($value, $sanitize = false) 
    {    
        // Set empty value, the input isn't required
		if(empty($value) && !$this->required) {
			$this->value = !isset($value) ? (float) $this->default : $value;
			return true;
		} else {
            $this->value = $value;
        }

		// Check input required
		if(($this->required) && empty($value)) {
			$this->errorCode = self::EMPTY_REQUIRED;
			return false;
		}

		// Sanitize input
		if($sanitize) {
            // let's change value into float format
            $this->value = $this->toDecimal($this->value);
            $this->value = $this->sanitize($this->value);
            // Sanitizer has wiped the value?
            if(!$this->value) {
                $this->errorCode = self::WRONG_VALUE_FORMAT;
                return false;
            }
		} else {
			$this->value = $value;
		}

		// Check min value length
		if($this->minLen > 0 && mb_strlen($this->value, 'UTF-8') < (int) $this->minLen) {
			$this->errorCode = self::ERR_MIN_LENGTH;
			return false;
		}

		// Check max value length
		if($this->maxLen > 0 && mb_strlen($this->value, 'UTF-8') > (int) $this->maxLen) {
			$this->errorCode = self::ERR_MAX_LENGTH;
			return false;
		}

		return true;
	}

	public function prepareOutput(){ return $this->value; }

	public static function toDecimal($value)
	{
        $minus = (strpos($value, '-') !== false) ? '-' : '';
		$cleanString = preg_replace('/([^0-9\.,])/i', '', $value);
		$onlyNumbersString = preg_replace('/([^0-9])/i', '', $value);
		$separatorsCountToBeErased = strlen($cleanString) - strlen($onlyNumbersString) - 1;
		$stringWithCommaOrDot = preg_replace('/([,\.])/', '', $cleanString, $separatorsCountToBeErased);
		$removedThousendSeparator = preg_replace('/(\.|,)(?=[0-9]{3,}$)/', '',  $stringWithCommaOrDot);
        return $minus. (float) str_replace(',', '.', $removedThousendSeparator);
	}

	protected function sanitize($value) { return imanager('sanitizer')->float($value); }
}