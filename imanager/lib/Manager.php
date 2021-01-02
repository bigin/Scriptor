<?php namespace Imanager;

class Manager
{
	/**
	 * @var Sanitizer|null - Sanitizer instance
	 */
	public $sanitizer = null;

	/**
	 * @var Config|null - Configuration class instance
	 */
	public $config = null;


	//public $admin = null;

	/**
	 * @var Input|null - Input class instance
	 */
	public $input = null;

	/**
	 * @since v 3.0
	 * Manager constructor.
	 */
	public function __construct()
	{
		spl_autoload_register(array($this, 'loader'));
		require_once(IM_SOURCEPATH.'processors/FieldInterface.php');
		require_once(IM_SOURCEPATH.'processors/InputInterface.php');
		include_once(IM_ROOTPATH.'imanager/phpthumb/ThumbLib.inc.php');
		$this->config = Util::buildConfig();
		$this->sanitizer = new Sanitizer();
		$this->input = new Input($this->config, $this->sanitizer);
	}

	/**
	 * @return null
	 */
	public function __get($name)
	{
		if(!isset($this->$name)) {
			$funcName = '_im' . ucfirst($name);
			if(method_exists($this, $funcName)) {
				$this->$name = $this->$funcName();
				return $this->$name;
			}
			return null;
		} else {
			return $this->$name;
		}
	}

	/**
	 * Autoload method
	 *
	 * @since v 3.0
	 * @param $lclass - Class pattern
	 */
	private function loader($lclass)
	{
		$classPattern = str_replace(__NAMESPACE__.'\\', '', $lclass);
		$classPath = IM_SOURCEPATH . $classPattern . '.php';
		$fieldsPath = IM_SOURCEPATH . 'processors/fields/' . $classPattern. '.php';
		$inputsPath = IM_SOURCEPATH . 'processors/inputs/' . $classPattern . '.php';
		if(file_exists($classPath)) include $classPath;
		elseif(file_exists($fieldsPath)) include $fieldsPath;
		elseif(file_exists($inputsPath)) include $inputsPath;
	}

	/**
	 * Auto-Callable
	 *
	 * @since v 3.0
	 * @return CategoryMapper
	 */
	protected function _imCategoryMapper() { return new CategoryMapper(); }

	/**
	 * Auto-Callable
	 *
	 * @since v 3.0
	 * @return FieldMapper
	 */
	protected function _imFieldMapper() { return new FieldMapper(); }

	/**
	 * Auto-Callable
	 *
	 * @since v 3.0
	 * @return ItemMapper
	 */
	protected function _imItemMapper() { return new ItemMapper(); }


	/**
	 * Auto-Callable
	 *
	 * @since v 3.0
	 * @return TemplateParser
	 */
	protected function _imTemplateParser()
	{
		$this->templateParser = new TemplateParser();
		$this->templateParser->init();
		return $this->templateParser;
	}

	/**
	 * Auto-Callable
	 *
	 * @since v 3.0
	 * @return SectionCache
	 */
	protected function _imSectionCache() { return new SectionCache(); }

}