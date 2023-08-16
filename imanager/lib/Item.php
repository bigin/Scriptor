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
	public ?int $categoryid = null;

	/**
	 * @var int|null - Item id
	 */
	public ?int $id = null;

	/**
	 * @var null|string - Item name
	 */
	public ?string $name = null;

	/**
	 * @var null|string - Item label
	 */
	public ?string $label = null;

	/**
	 * @var null|int - Item position
	 */
	public ?int $position = null;

	/**
	 * @var null|boolean - Active/inactive flag
	 */
	public ?bool $active = null;

	/**
	 * @var null|int - Timestamp
	 */
	public ?int $created = null;

	/**
	 * @var null|int - Timestamp
	 */
	public ?int $updated = null;

	/**
	 * @var null
	 */
	public mixed $errorCode = null;

	/**
	 * Item constructor.
	 *
	 * @param $category_id
	 */
	public function __construct(int $category_id)
	{
		$this->categoryid = $category_id;
		/* settype($this->categoryid, 'integer');
		settype($this->id, 'integer');
		settype($this->position, 'integer');
		settype($this->active, 'boolean');
		settype($this->created, 'integer');
		settype($this->updated, 'integer'); */
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
	public function init(int $categoryid, bool $force = false) : void
	{ 
		if (!isset($this->imanager)) { 
			parent::init($categoryid, $force);
		} 
	}

	/**
	 * Restricted parent init.
	 * Used to prevent the deformation of the properties in item objects
	 *
	 * @param string $name
	 */
	public function __get(string $name)
	{
		if ($name == 'fields') {
			$this->init($this->categoryid, true);
			return $this->$name;
		}
	}

	/**
	 * This static method is called for items exported by var_export()
	 *
	 * @param $an_array
	 *
	 * @return Item
	 */
	public static function __set_state(array $an_array) : Item
	{
		$_instance = new Item($an_array['categoryid']);
		foreach ($an_array as $key => $val) {
			if (is_array($val)) $_instance->$key = $val;
			else $_instance->$key = $val;
		}
		return $_instance;
	}

	/**
	 * Retrives items default attributes array
	 */
	private function getAttributes(): array 
	{
		return array('categoryid', 'id', 'name', 'label', 'position', 'active', 'created', 'updated');
	}

	/**
	 * Returns next available id
	 *
	 * @return int
	 */
	private function getNextId(ItemMapper $im): int
	{
		return !empty($im->items) ? (max(array_keys($im->items))+1) : 1;
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
	public function set(string $name, mixed $value, bool $sanitize = true) : ?object
	{
		$this->init($this->categoryid, true);
		$attributeKey = strtolower(trim($name));
		$isAttribute = ! in_array($attributeKey, $this->getAttributes()) ? false : true;
		if (! $isAttribute && !isset($this->fields[$name])) { 
			Util::logException(new \ErrorException('Illegal attribute or field name')); 
		}
		if ($isAttribute) {
			if (in_array($attributeKey, array('categoryid', 'id', 'position', 'created', 'updated'))) {
				$this->$attributeKey = (int) $value;
			} elseif ($attributeKey === 'name' || $attributeKey === 'label') {
				$this->$attributeKey = $this->imanager->sanitizer->text($value, [
					'maxLength' => $this->imanager->config->maxItemNameLength
				]);
			} elseif ($attributeKey === 'active') {
				$this->$attributeKey = (bool) $value;
			}
			return $this;
		}
		$field = $this->fields[$name];
		$inputClassName = __NAMESPACE__.'\Input'.ucfirst($field->type);
		$Input = new $inputClassName($field);
		$Input->itemid = $this->id;
		if (!$sanitize) {
			if (true !== $Input->prepareInput($value)) {
				$this->errorCode = $Input->errorCode;
				return null;
			}
			$this->$name = $Input->prepareOutput();
		} else {
			if (true !== $Input->prepareInput($value, true)) {
				$this->errorCode = $Input->errorCode;
				return null;
			}
			$this->$name = $Input->prepareOutput(true);
		}
		return $this;
	}

	/**
	 * Save item
	 * 
	 * This method saves the item to the file system by creating a backup of the file if necessary, 
	 * locking the file, updating the item's attributes, cleaning up the item object, and writing 
	 * the changes to the file. If an error occurs during the process, the method logs the error 
	 * and returns false.
	 *
	 * @return bool
	 */
	public function save() : bool
	{
		$this->init($this->categoryid);
		$im = imanager()->itemMapper;
		$config = imanager('config');
		$now = time();

		// Lock the file
		$lockFile = $this->lockFile($im->path);
		$im->init($this->categoryid, true);

		$this->id = (!$this->id) ? $this->getNextId($im) : (int) $this->id;

		if (!$this->created) {
			$this->created = $now;
		}

		$this->updated = $now;

		if (!$this->position) {
			$this->position = $this->id;
		}

		if (!$this->checkRequired()) {
			Util::logException(new \ErrorException('A categoryid attribute value is expected'));
		}

		// Set empty values to default defined field value
		if (is_array($this->fields)) {
			foreach ($this->fields as $key => $field) {
				if (!isset($this->{$field->name}) && !empty($field->default)) { 
					$this->{$field->name} = $field->default; 
				}
			}
		}

		// Clean-up item object by removing redundant item object attributes
		$this->declutter();
		if (is_array($im->items)) foreach($im->items as $item) { $item->declutter(); }
		$im->items[$this->id] = $this;	

		// Create a backup if necessary
		if($config->backupItems) {
			Util::createBackup(dirname($im->path).'/', basename($im->path, '.php'), '.php');
		}

		// Write the changes to the file
		$export = var_export($im->items, true);
		try {
			file_put_contents($im->path, '<?php return ' . $export . '; ?>');
			@chmod($im->path, $config->chmodFile);
		} catch (\ErrorException $e) {
			 Util::logException($e);
			 return false;
		} finally {
			// Unlock the file
			$this->unlockFile($lockFile);
		}

		return true;
	}

	/**
	 * Check required Item attributes
	 *
	 * @return bool
	 */
	private function checkRequired() : bool
	{
		if (!(int) $this->categoryid) {
			return false;
		}
		return true;
	}

	/**
	 * Removes redundant item object attributes
	 * 
	 * This method removes any unnecessary attributes from the item object. 
	 * It checks the attributes of the item object against a list of allowed 
	 * attributes and removes any that are not in the list.
	 */
	public function declutter() : void
	{
		$attributes = $this->getAttributes();
		$fields = $this->fields;
		// Remove any other item attributes
		foreach ($this as $key => $value) {
			if (!in_array($key, $attributes) && (empty($fields) || !array_key_exists($key, $fields))) {
				unset($this->$key);
			}
		}
	}

	/**
	 * Locks the file for writing
	 *
	 * @param string $path The path to the file
	 * @return resource The lock file handle
	 */
	private function lockFile(string $path)
	{
		$lockFile = fopen(pathinfo($path, PATHINFO_FILENAME) . '.lock', 'w');
		if (!$lockFile) {
			Util::logException(new \ErrorException('Failed to open lock file'));
        	return false;
		}
		if (!flock($lockFile, LOCK_EX)) {
			Util::logException(new \ErrorException('Failed to acquire lock'));
        	return false;
		}
		return $lockFile;
	}

	/**
	 * Unlocks the file after writing
	 *
	 * @param resource $lockFile The lock file handle
	 */
	private function unlockFile($lockFile)
	{
		flock($lockFile, LOCK_UN);
		fclose($lockFile);
	}
}