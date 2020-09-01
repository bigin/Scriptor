<?php namespace Imanager;

class FieldLongtext implements FieldInterface
{
	public $properties;

	public function __construct()
	{
		$this->tpl = $this->imanager->templateParser;
		$this->name = null;
		$this->class = null;
		$this->id = null;
		$this->value = null;
		$this->configs = new \stdClass();
	}


	public function render($sanitize=false)
	{
		if(is_null($this->name))
			return false;

		$itemeditor = $this->tpl->getTemplates('field');
		$textfield = $this->tpl->getTemplate('longtext', $itemeditor);
		$output = $this->tpl->render($textfield, array(
				'name' => $this->name,
				'class' => $this->class,
				'id' => $this->id,
				'value' => !empty($sanitize) ? $this->sanitize($this->value) : $this->value), true, array()
		);
		return $output;
	}
	protected function sanitize($value){return imanager('sanitizer')->textarea($value);}

	public function getConfigFieldtype(){}
}