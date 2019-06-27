<?php namespace Imanager;

class FieldArray implements FieldInterface
{
	/**
	 * @var ItemManager
	 */
	protected $imanager;

	/**
	 * @var null|string - Real field name
	 */
	public $name = null;

	public $type = null;

	/**
	 * @var null|string - CSS-Class of the field
	 */
	public $class = null;

	/**
	 * @var null|string - CSS-ID of the field
	 */
	public $id = null;

	/**
	 * @var null|int - Real field id
	 */
	public $fieldid = null;

	/**
	 * @var FieldConfigs|null
	 */
	public $configs = null;

	/**
	 * @var null|mixed - Field value
	 */
	public $value = null;

	/**
	 * @var null|int - Field size attribute
	 */
	public $size = null;

	public $required = null;

	public $maxlength = null;


	public $readonly = null;

	public $disabled = null;

	/**
	 * @var null|int - Category id
	 */
	public $categoryid = null;

	/**
	 * @var null|int - Item id
	 */
	public $itemid = null;

	/**
	 * @var array Default configs
	 */
	protected $defaults = array(

	);

	/**
	 * FieldText constructor
	 */
	public function __construct()
	{
		$this->imanager = imanager();
		$this->setDefaults();
	}

	protected function setDefaults()
	{
		if(!isset($this->configs)) { $this->configs = new FieldConfigs();}
		foreach($this->defaults as $key => $value) {
			if(!isset($this->configs->{$key})) { $this->configs->{$key} = $value; }
		}
	}

	public function set($name, $value, $sanitize = true) {
		$this->{strtolower($name)} = ($sanitize) ? $this->imanager->sanitizer->text($value) : $value;
	}


	public function render($sanitize = false)
	{
		if(!$this->name) {
			trigger_error('name expected, null given', E_USER_WARNING);
			return false;
		}

		if($sanitize) {
			$class = (($this->class) ? ' class="'.$this->sanitize($this->class).'"' : '');
			$id = (($this->id) ? ' id="'.$this->sanitize($this->id).'"' : '');
			$value = $this->sanitize($this->value);
			$size = ($this->size) ? ' size="'.(int) $this->size.'"' : '';
			$maxlen = ($this->maxlength) ? ' maxlength="'.(int)$this->maxlength.'"' : '';

		} else {
			$class = (($this->class) ? ' class="'.$this->class.'"' : '');
			$id = (($this->id) ? ' id="'.$this->id.'"' : '');
			$value = $this->value;
			$size = ($this->size) ? ' size="'.$this->size.'"' : '';
			$maxlen = ($this->maxlength) ? ' maxlength="'.$this->maxlength.'"' : '';
		}

		// Type & name should always be sanitized
		$type = ($this->type) ? $this->imanager->sanitizer->fieldName($this->type) : 'text';
		$name = $this->imanager->sanitizer->fieldName($this->name);
		$required = ($this->required) ? ' required' : '';
		$readonly = ($this->readonly) ? ' readonly' : '';
		$disabled = ($this->disabled) ? ' disabled' : '';

		return $this->imanager->templateParser->render('fields/text', array(
			'type' => $type,
			'class' => $class,
			'id' => $id,
			'name' => $name,
			'value' => $value,
			'size' => $size,
			'required' => $required,
			'maxlen' => $maxlen,
			'readonly' => $readonly,
			'disabled' => $disabled,
			), array(), true
		);
	}

	protected function sanitize($value){ return $this->imanager->sanitizer->text($value); }

	public function getConfigFieldtype(){}
}