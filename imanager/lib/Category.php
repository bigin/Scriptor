<?php namespace Imanager;

class Category extends ImObject
{
	/**
	 * @var integer - Category id
	 */
	public $id = null;

	/**
	 * @var integer - Category position
	 */
	public $position = null;

	/**
	 * @var string - Name of the category
	 */
	public $name = null;

	/**
	 * @var string - Category permalink
	 */
	public $slug = null;

	/**
	 * @var integer - Category created date
	 */
	public $created = null;

	/**
	 * @var integer - Category updated date
	 */
	public $updated = null;

	/**
	 * Category constructor.
	 */
	public function __construct()
	{
		settype($this->id, 'integer');
		settype($this->position, 'integer');
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
	public function init() { if(!isset($this->imanager)) { parent::___init();} }

	public static function __set_state($an_array)
	{
		$_instance = new Category();
		foreach($an_array as $key => $val) {
			if(is_array($val)) $_instance->{$key} = $val;
			else $_instance->{$key} = $val;
		}
		return $_instance;
	}

	/**
	 * Retrives category attributes array
	 */
	protected function getAttributes() {
		return array('id', 'position', 'name', 'slug', 'created', 'updated');
	}

	/**
	 * Get all items of the current category
	 * Call example: $category->items
	 *
	 * @param $name
	 *
	 * @return null
	 */
	public function __get($name)
	{
		if($name == 'items' && $this->id) {
			$this->init();
			$mapper = $this->imanager->itemMapper;
			$mapper->init($this->id, false);
			return $mapper->items;
		} else if($name == 'fields' && $this->id) {
			$this->init();
			$this->imanager->fieldMapper->init($this->id, false);
			return $this->imanager->fieldMapper->fields;
		}
		return null;
	}

	/**
	 * Get the Item matching the given selector string without exclusions.
	 * Returns an Item, or a NULL if not found.
	 *
	 * Since ItemManager v 3.1.1 it supports a force
	 * parameter to force initialization.
	 *
	 * ~ This method is a part of category wrapper methods for handling
	 *   child objects ~
	 *
	 * @param $selector
	 * @param array $items
	 * @param bool $force
	 *
	 * @return mixed
	 */
	public function getItem($selector, array $items = array(), $force = false)
	{
		$this->init();
		$this->imanager->itemMapper->init($this->id, $force);
		return $this->imanager->itemMapper->getItem($selector, $items);
	}

	/**
	 * Get all Items matching the given selector string without exclusions.
	 * Returns one or several Items, or a NULL if not found.
	 *
	 * Since ItemManager v 3.1.1 it supports a force
	 * parameter to force initialization.
	 *
	 * ~ This method is a part of category wrapper methods for handling
	 *   child objects ~
	 *
	 * @param $selector
	 * @param int $length
	 * @param array $items
	 * @param bool $force
	 *
	 * @return mixed
	 */
	public function getItems($selector = '', $length = 0, array $items = array(), $force = false)
	{
		$this->init();
		$this->imanager->itemMapper->init($this->id, $force);
		return $this->imanager->itemMapper->getItems($selector, $length, $items);
	}

	/**
	 * A public method for sorting the items
	 *
	 * You can sort items by using any attribute.
	 * Default sortng attribute is "position":
	 * ItemMapper::sort('position', 'DESC', $offset, $length, $your_items_array)
	 *
	 * @param string $filterby - Filter by Item attribute
	 * @param string $order    - The order in which Items are listed
	 * @param int|null $offset - The first row to return
	 * @param length $length   - Specifies the maximum number of rows to return
	 * @param array $items     - Elements to search through or empty if the buffered Items shall be used instead
	 * @param bool $force
	 *
	 * @return boolean|array   - An array of Item objects
	 */
	public function sort($filterby = 'position', $order = 'asc',  $offset = 0, $length = 0, array $items = array(), $force = false)
	{
		$this->init();
		$this->imanager->itemMapper->init($this->id, $force);
		return $this->imanager->itemMapper->sort($filterby, $order, $offset, $length, $items);
	}

	/**
	 * Deletes an item or field belonging to this category.
	 *
	 * @param $obj
	 * @param bool $complete
	 *
	 * @return bool
	 * @throws \ErrorException
	 */
	public function remove(& $obj, $complete = true)
	{
		$this->init();
		if($obj instanceof Item) {
			return $this->imanager->itemMapper->remove($obj, $complete);
		} elseif($obj instanceof Field) {
			return $this->imanager->fieldMapper->remove($obj, $complete);
		}
		trigger_error('Object type is unknown', E_USER_WARNING);
		return false;
	}

	/**
	 * Get a Field matching the given selector string without exclusions.
	 * Returns one Field object, or a NULL if not found.
	 *
	 * ~ This method is a part of category wrapper methods for handling
	 *   child objects ~
	 *
	 * @param $selector
	 * @param array $fields
	 * @param bool $force
	 *
	 * @return mixed
	 */
	public function getField($selector, array $fields = array(), $force = false)
	{
		$this->init();
		$this->imanager->fieldMapper->init($this->id, $force);
		return $this->imanager->fieldMapper->getField($selector, $fields);
	}

	/**
	 * Set category's attribut value
	 *
	 * @param $key
	 * @param $val
	 *
	 * @return bool
	 */
	public function set($key, $val, $sanitize = true)
	{
		$this->init();
		$sanitizer = $this->imanager->sanitizer;
		$key = strtolower($key);

		// Allowed attributes
		if(!in_array($key, $this->getAttributes())) {
			Util::logException(new \ErrorException('Illegal category attribute'));
		}

		$sanitized = null;
		if($key == 'slug') {
			$sanitized = ($sanitize) ? $sanitizer->pageName($val) : $val;
		} elseif($key == 'id' || $key == 'created' || $key == 'updated' || $key == 'position') {
			$sanitized = ($sanitize) ? (int) $val : $val;
		} else {
			$sanitized = ($sanitize) ? $sanitizer->text($val) : $val;
		}

		if(!$sanitized) { Util::logException(new \ErrorException('Illegal category attribute format')); }
		$this->{$key} = $sanitized;

		return $this;
	}

	/**
	 * Check required Category attributes
	 *
	 * @return bool
	 */
	private function checkRequired()
	{
		if(!$this->name) {
			Util::logException(new \ErrorException('A category name attribute value is expected'));
		}
		return true;
	}

	/**
	 * Check category name/slug duplicates
	 *
	 * @return bool
	 */
	private function checkDuplicates()
	{
		$existed = $this->imanager->categoryMapper->getCategory('name='.$this->name);
		if($existed && ((int) $existed->id !== (int) $this->id)) {
			Util::logException(new \ErrorException('Category name already exists'));
		}
		$existed = $this->imanager->categoryMapper->getCategory('slug='.$this->slug);
		if($existed && ((int) $existed->id !== (int) $this->id)) {
			Util::logException(new \ErrorException('Category slug already exists'));
		}
		return true;
	}

	/**
	 * Removes redundant Category object attributes.
	 *
	 * This method is used to prepare category objects for saving.
	 */
	public function declutter()
	{
		foreach($this as $key => $value) {
			if(!in_array($key, $this->getAttributes())) {
				unset($this->$key);
			}
		}
	}

	/**
	 * Save Category
	 *
	 * Clean-up Category object by removing redundant object attributes
	 *
	 * @return bool
	 */
	public function save()
	{
		$this->init();
		$sanitizer = $this->imanager->sanitizer;
		$attributes = $this->getAttributes();
		$now = time();

		$cm = $this->imanager->categoryMapper;
		$cm->init();

		if(true !== $this->checkRequired()) { return false; }

		if(!$this->id && $cm->categories) {
			$this->id = (max(array_keys($cm->categories))+1);
		} else {
			$this->id = ($this->id) ? (int) $this->id : 1;
		}
		if(!$this->slug) {
			$this->slug = $sanitizer->pageName($this->name);
		}
		if(!$this->created) {
			$this->created = $now;
		}
		$this->updated = $now;
		if(!$this->position) {
			$this->position = (int) $this->id;
		}

		if(true !== $this->checkDuplicates()) { return false; }

		$this->declutter();

		$cm->categories[$this->id] = $this;
		// Create a backup if necessary
		if($cm->imanager->config->backupCategories) {
			Util::createBackup(dirname($cm->path).'/', basename($cm->path, '.php'), '.php');
		}
		$export = var_export($cm->categories, true);
		file_put_contents($cm->path, '<?php return ' . $export . '; ?>');
		@chmod($cm->path, $cm->imanager->config->chmodFile);

		return true;
	}
}