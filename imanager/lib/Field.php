<?php namespace Imanager;

class Field extends Object
{
	/**
	 * @var int|null - The category ID of this field
	 */
	public $categoryid = null;

	/**
	 * @var int|null - This field id
	 */
	public $id = null;

	/**
	 * @var string|null - Field name
	 */
	public $name = null;

	/**
	 * @var string|null - Field label
	 */
	public $label = null;

	/**
	 * @var string|null - Field type
	 */
	public $type = null;

	/**
	 * @var int|null - Field position
	 */
	public $position = null;

	/**
	 * @var string|null - This field default value
	 */
	public $default = null;

	/**
	 * @var array - This field options
	 */
	public $options = array();

	/**
	 * @var null - Field info
	 */
	public $info = null;

	/**
	 * @var null - Required attribute of this field
	 */
	public $required = null;

	/**
	 * @var null - Minimum length for this field value
	 */
	public $minimum = null;

	/**
	 * @var null - Maximum length for this field value
	 */
	public $maximum = null;

	/**
	 * @var null - CSS class
	 */
	public $cssclass = null;

	/**
	 * @var null - Custom field configs
	 */
	public $configs = null;

	/**
	 * @var null - Created date
	 */
	public $created = null;

	/**
	 * @var null - Modified date
	 */
	public $updated = null;

	/**
	 * Field constructor.
	 *
	 * @param $category_id
	 */
	public function __construct($category_id)
	{
		$this->categoryid = (int) $category_id;

		$this->configs = new FieldConfigs();

		settype($this->id, 'integer');
		settype($this->position, 'integer');
		settype($this->maximum, 'integer');
		settype($this->required, 'boolean');
		settype($this->minimum, 'integer');
		settype($this->created, 'integer');
		settype($this->updated, 'integer');

		unset($this->imanager);
	}

	/**
	 * Restricted parent init.
	 * Used to prevent the writing of external properties in field objects buffer
	 *
	 * @param $name
	 */
	private function init() {
		if(!isset($this->imanager)) { parent::___init($this->categoryid);}
	}

	/**
	 * @param $an_array
	 *
	 * @return Field
	 */
	public static function __set_state($an_array)
	{
		$_instance = new Field($an_array['categoryid']);
		foreach($an_array as $key => $val) {
			if(is_array($val)) $_instance->{$key} = $val;
			else $_instance->{$key} = $val;
		}
		//$_instance->configs = new \stdClass();
		//$_instance->configs = array();
		return $_instance;
	}

	/**
	 * Retrives field attributes array
	 */
	private function getAttributes() {
		return array('categoryid', 'id', 'name', 'label', 'type', 'position', 'default', 'options',
			'info', 'required', 'minimum', 'maximum', 'cssclass', 'configs', 'created', 'updated');
	}

	/**
	 * Returns maximal field id
	 *
	 * @return integer
	 */
	private function getMaxFieldId()
	{
		$fm = $this->imanager->fieldMapper;
		$fm->init($this->categoryid);
		$ids = array();
		if(is_array($fm->fields)) {
			foreach($fm->fields as $field) {
				$ids[] = $field->id;
			}
		}
		return (!empty($ids) ? max($ids) : 0);
	}

	/**
	 * Set any attribute value depending on the data type
	 *
	 * @param $key
	 * @param $val
	 * @param bool $sanitize
	 *
	 * @return bool
	 */
	public function set($key, $val, $sanitize = true)
	{
		$this->init();
		$sanitizer = $this->imanager->sanitizer;

		$key = strtolower($key);

		if(!in_array($key, $this->getAttributes())) { return false; }

		$literals = array('name', 'label', 'type', 'default', 'info', 'areaclass', 'labelclass', 'fieldclass');

		if(in_array($key, $literals)) {
			if($key == 'name') {
				$this->{$key} = $sanitizer->fieldName($val, false, $this->imanager->config->maxFieldNameLength);
			} elseif($key == 'type') {
				$this->{$key} = $sanitizer->fieldName($val);
			} elseif($key == 'default') {
				if(is_bool($val)) { $this->{$key} = $val; }
				elseif(is_array($val)) { $this->{$key} = $val; }
				elseif(is_numeric($val)) { $this->{$key} = $val; }
				else { $this->{$key} = ($sanitize) ? $sanitizer->text($val) : $val; }
			} else {
				$this->{$key} = ($sanitize) ? $sanitizer->text($val) : $val;
			}
		} elseif($key == 'options') {
			$this->options = $val;
		} elseif($key == 'configs' && $val instanceof FieldConfigs) {
			$this->{$key} = $val;
		} else {
			$this->{$key} = ($sanitize) ? (int) $val : $val;
		}
	}

	/**
	 * Retrieve a field attribute
	 *
	 * @param $key
	 *
	 * @return null
	 */
	public function get($key){ return isset($this->{$key}) ? $this->{$key} : null; }


	/**
	 * Returns maximum field id
	 */
	private function getNextId()
	{
		// no category is selected, return false
		if(!$this->categoryid) return null;

		$ids = array();
		$maxid = 1;
		if(file_exists(IM_BUFFERPATH.'fields/'.(int) $this->categoryid.'.fields.php')) {
			$fields = include(IM_BUFFERPATH.'fields/'.(int) $this->categoryid.'.fields.php');
			if(is_array($fields)) { $maxid = ($this->getMaxFieldId()+1);}
		}
		return $maxid;
	}

	/**
	 * Check required field attributes
	 *
	 * @return bool
	 */
	private function checkRequired()
	{
		$sanitizer = $this->imanager->sanitizer;

		$catid = (int) $this->categoryid;

		$this->imanager->categoryMapper->init();
		$cat = $this->imanager->categoryMapper->getCategory($catid);
		if(!$cat) {
			Util::logException(new \ErrorException('The entered category id is unknown'));
		}
		$this->categoryid = $cat->id;

		$this->type = $sanitizer->fieldName($this->type);
		if(!$this->type) {
			Util::logException(new \ErrorException('Illegal field type'));
		}

		$this->name = $sanitizer->fieldName($this->name, false, $this->imanager->config->maxFieldNameLength);
		if(!$this->name) {
			Util::logException(new \ErrorException('Illegal field name'));
		}

		return true;
	}

	/**
	 * Check field name duplicates
	 *
	 * @return bool
	 */
	private function checkNameDuplicates()
	{
		$fm = $this->imanager->fieldMapper;
		$fm->init($this->categoryid);
		$existed = $fm->getField('name='.$this->name);
		if($existed && ((int) $existed->id !== (int) $this->id)) {
			Util::logException(new \ErrorException('Field name already exists'));
		}

		return true;
	}

	/**
	 * Look for reserved names
	 *
	 * @return bool
	 */
	private function checkReservedNames()
	{
		if(in_array($this->name, $this->getAttributes())) {
			Util::logException(new \ErrorException("Field name '$this->name' is reserved for internal usage"));
		}
		return true;
	}

	/**
	 * Save field
	 *
	 * @return bool
	 */
	public function save()
	{
		$this->init();
		$sanitizer = $this->imanager->sanitizer;
		$config = $this->imanager->config;
		$now = time();

		if(!$this->checkRequired()) return false;

		$this->id = (!$this->id) ? $this->getNextId() : (int) $this->id;

		if(!$this->created) $this->created = $now;
		$this->updated = $now;
		if(!$this->position) $this->position = (int) $this->id;

		// check field name unique
		if(!$this->checkNameDuplicates()) return false;
		// check reserved name
		if(!$this->checkReservedNames()) return false;

		$fm = $this->imanager->fieldMapper;
		$fm->init($this->categoryid);
		// Remove unwanted field attributes
		foreach($this as $key => $value) {
			if(!in_array($key, $this->getAttributes())) {
				unset($this->$key);
			}
		}
		// Check field id exists and delete it if renamed
		$exist = $fm->getField($this->id);
		if($exist && $exist->name != $this->name) { unset($fm->fields[$exist->name]); }
		$fm->fields[$this->name] = $this;

		$fm->sort();

		// Create a backup if necessary
		if($config->backupFields) {
			Util::createBackup(dirname($fm->path).'/', basename($fm->path, '.php'), '.php');
		}

		$export = var_export($fm->fields, true);
		file_put_contents($fm->path, '<?php return ' . $export . '; ?>');
		@chmod($fm->path, $config->chmodFile);
		return true;
	}
}