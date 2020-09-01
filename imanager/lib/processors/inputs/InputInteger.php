<?php namespace Imanager;

class InputInteger extends InputText implements InputInterface
{
    private $unsigned = false;
	/**
	 * InputLongtext constructor.
	 *
	 * @param Field $field
	 */
	public function __construct(Field $field) {
        parent::__construct($field);
        if(isset($this->field->configs) && isset($this->field->configs->unsigned)) {
            $this->unsigned = $this->field->configs->unsigned;
        }
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
	public function prepareInput($value, $sanitize = false) {
        // Sanitize input
		if($sanitize) {
            $this->value = $this->sanitize($value);
            // Sanitizer has wiped the value?
            if(!$this->value) {
                $this->errorCode = self::WRONG_VALUE_FORMAT;
                return false;
            }
		} else {
			$this->value = $value;
        }
        
        return true;
    }
    
	public function prepareOutput(){ return $this->value; }

    protected function sanitize($value) 
    { 
        if($this->unsigned) return imanager('sanitizer')->intUnsigned($value); 
        else return imanager('sanitizer')->int($value); 
    }
}