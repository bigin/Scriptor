<?php namespace Imanager;

/**
 * Class Item
 *
 * @package Imanager
 */
class Item extends FieldMapper
{
	/**
	 * @var int|null - Category id
	 */
	public $categoryid = null;

	/**
	 * @var int|null - Item id
	 */
	public $id = null;

	/**
	 * @var null|string - Item name
	 */
	public $name = null;

	/**
	 * @var null|string - Item label
	 */
	public $label = null;

	/**
	 * @var null|int - Item position
	 */
	public $position = null;

	/**
	 * @var null|boolean - Active/inactive flag
	 */
	public $active = null;

	/**
	 * @var null|string - Timestamp
	 */
	public $created = null;

	/**
	 * @var null|boolean - Timestamp
	 */
	public $updated = null;

	/**
	 * @var null
	 */
	public $errorCode = null;

	/**
	 * Item constructor.
	 *
	 * @param $category_id
	 */
	public function __construct($category_id)
	{
		$this->categoryid = (int) $category_id;

		settype($this->categoryid, 'integer');
		settype($this->id, 'integer');
		settype($this->position, 'integer');
		settype($this->active, 'boolean');
		settype($this->created, 'integer');
		settype($this->updated, 'integer');

		unset($this->errorCode);
		unset($this->fields);
		unset($this->total);
		unset($this->path);
		unset($this->imanager);

		//parent::init($this->categoryid);
	}

	/**
	 * Restricted parent init.
	 * Used to prevent the writing of external properties in item
	 * objects buffer, is a kind of lazy init method
	 *
	 * @param $name
	 */
	public function init(int $categoryid, bool $force = false) { if(!isset($this->imanager)) { parent::init($categoryid, $force);} }

	/**
	 * Restricted parent init.
	 * Used to prevent the deformation of the properties in item objects
	 *
	 * @param $name
	 */
	public function __get($name)
	{
		if($name == 'fields') {
			$this->init($this->categoryid, true);
			return $this->{$name};
		}
	}

	/**
	 * This static method is called for items exported by var_export()
	 *
	 * @param $an_array
	 *
	 * @return Item
	 */
	public static function __set_state($an_array)
	{
		$_instance = new Item($an_array['categoryid']);
		foreach($an_array as $key => $val) {
			if(is_array($val)) $_instance->{$key} = $val;
			else $_instance->{$key} = $val;
		}
		return $_instance;
	}

	/**
	 * Retrives item attributes array
	 */
	private function getAttributes(): array {
		return array('categoryid', 'id', 'name', 'label', 'position', 'active', 'created', 'updated');
	}

	/**
	 * Returns next available id
	 *
	 * @return int
	 */
	private function getNextId(): int
	{
		$ids = array();
		$maxid = 1;
		if(file_exists(IM_BUFFERPATH.'items/'.(int) $this->categoryid.'.items.php')) {
			$items = include(IM_BUFFERPATH.'items/'.(int) $this->categoryid.'.items.php');
			if(is_array($items)) {
				$maxid = !empty($items) ? (max(array_keys($items))+1) : 1;
			}
		}
		return $maxid;
	}

	/**
	 * A safe way to set the value of an attribute or 
	 * item's field value. Used for internal purposes.
	 *
	 * @param string $name - Fieldname or attribute
	 * @param int|string|boolean|array $value
	 * @param bool $sanitize
	 *
	 * @return null|object
	 */
	public function set($name, $value, $sanitize = true) :?object
	{
		$this->init($this->categoryid, true);
		$attributeKey = strtolower(trim($name));
		$isAttribute = ! in_array($attributeKey, $this->getAttributes()) ? false : true;
		if(! $isAttribute && ! isset($this->fields[$name])) { Util::logException(new \ErrorException('Illegal attribute or field name')); }
		if($isAttribute) {
			if(in_array($attributeKey, array('categoryid', 'id', 'position', 'created', 'updated'))) {
				$this->$attributeKey = (int) $value;
			} elseif($attributeKey == 'name' || $attributeKey == 'label') {
				$this->$attributeKey = $this->imanager->sanitizer->text($value, [
					'maxLength' => $this->imanager->config->maxItemNameLength
				]);
			} elseif($attributeKey == 'active') {
				$this->$attributeKey = (boolean) $value;
			}
			return $this;
		}
		$field = $this->fields[$name];
		$inputClassName = __NAMESPACE__.'\Input'.ucfirst($field->type);
		$Input = new $inputClassName($field);
		$Input->itemid = $this->id;
		if(! $sanitize) {
			if(true !== $Input->prepareInput($value)) {
				$this->errorCode = $Input->errorCode;
				return null;
			}
			$this->$name = $Input->prepareOutput();
		} else {
			if(true !== $Input->prepareInput($value, true)) {
				$this->errorCode = $Input->errorCode;
				return null;
			}
			$this->$name = $Input->prepareOutput(true);
		}
		return $this;
	}

	/**
	 * Check required Item attributes
	 *
	 * @return bool
	 */
	private function checkRequired()
	{
		if(!(int) $this->categoryid) {
			Util::logException(new \ErrorException('A categoryid attribute value is expected'));
		}
		return true;
	}

	/**
	 * Removes redundant item object attributes
	 */
	public function declutter()
	{
		$fields = $this->fields;
		$attributes = $this->getAttributes();
		// Remove any other item attributes
		foreach($this as $key => $value) {
			if(!in_array($key, $attributes) && !array_key_exists($key, $fields)) {
				unset($this->$key);
			}
		}
	}

	/**
	 * Save item
	 *
	 * @return bool
	 */
	public function save()
	{
		$this->init($this->categoryid);
		$sanitizer = imanager('sanitizer');
		$config = imanager('config');
		$now = time();

		$this->id = (!$this->id) ? $this->getNextId() : (int) $this->id;

		if(!$this->created) {
			$this->created = $now;
		}
		$this->updated = $now;
		if(!$this->position) {
			$this->position = (int) $this->id;
		}
		if(true !== $this->checkRequired()) { return false; }

		// Set empty values to default defined field value
		foreach($this->fields as $key => $field) {
			if(!isset($this->{$field->name}) || !isset($this->{$field->name})) { $this->{$field->name} = $field->default; }
		}

		$im = imanager()->itemMapper;
		$im->init($this->categoryid);

		// Clean-up item object by removing redundant item object attributes
		$this->declutter();
		if(is_array($im->items)) foreach($im->items as $item) { $item->declutter(); }
		$im->items[$this->id] = $this;

		// Create a backup if necessary
		if($config->backupItems) {
			Util::createBackup(dirname($im->path).'/', basename($im->path, '.php'), '.php');
		}

		$export = var_export($im->items, true);
		file_put_contents($im->path, '<?php return ' . $export . '; ?>');
		@chmod($im->path, $config->chmodFile);

		return true;
	}
}