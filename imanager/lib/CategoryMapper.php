<?php namespace Imanager;

class CategoryMapper extends Mapper
{

	/**
	 * @var string - Filter by attribute
	 */
	private $filterby;

	/**
	 * @var int - Categories counter
	 */
	public $total = 0;

	/**
	 * @var null|string - Category file path
	 */
	public $path = null;

	/**
	 * @var bool - An initialize flag for intern use
	 */
	private static $initialized = false;

	/**
	 * Initializes all the categories and made
	 * them available in CategoryMapper::$categories
	 * buffer.
	 *
	 * This method uses control structure
	 * to avoid unnecessary reinitialization.
	 *
	 * Since ItemManager v 3.1.1 it supports a force
	 * parameter to force initialization.
	 *
	 * @return bool
	 */
	public function init($force = false)
	{
		if(self::$initialized && !$force) return true;

		parent::___init();
		$this->path = IM_BUFFERPATH.'categories/categories.php';
		if(!file_exists(dirname($this->path))) {
			Util::install($this->path);
		}
		if(file_exists($this->path)) {
			(!Util::isOpCacheEnabled()) or Util::clearOpCache($this->path);
			$this->categories = include $this->path;
			if(is_array($this->categories)) {
				$this->total = count($this->categories);
			} else {
				$this->categories = array();
				$this->total = 0;
			}
			self::$initialized = true;
			return true;
		}
		unset($this->categories);
		$this->categories = null;
		$this->total = 0;
		return false;
	}


	public function __get($name)
	{
		if($name == 'categories') {
			$this->init();
			return $this->categories;
		}
	}

	/**
	 * Returns the number of categories
	 *
	 * @param array $categories
	 * @return int
	 */
	public function countCategories(array $categories=array()) {
		return count(!empty($categories) ? $categories : $this->categories);
	}

	/**
	 * Returns the object of type Category
	 * NOTE: However if no $categories argument is passed to the function, the categories
	 * must already be in the buffer: ImCategory::$categories. Call the ImCategory::init()
	 * method before to assign the categories to the buffer.
	 *
	 * You can search for category by ID: ImCategory::getCategory(2) or similar to ImCategory::getCategory('id=2')
	 * or by category name ImCategory::getCategory('name=My category name')
	 *
	 * @param string/integer $selector
	 * @param array|input $categories
	 * @return boolean|object of the type Category
	 */
	public function getCategory($selector, $categories = array())
	{
		if(is_null($categories)) return null;

		if(!$categories)  $categories = $this->categories;
		// No items selected
		if(empty($categories)) return null;
		// A nummeric value, id was entered?
		if(is_numeric($selector)) return !empty($categories[$selector]) ? $categories[$selector] : null;
		// Separate selector
		$data = explode('=', $selector, 2);
		$key = strtolower(trim($data[0]));
		$val = trim($data[1]);
		$num = substr_count($val, '%');
		$pat = false;
		if($num == 1) {
			$pos = mb_strpos($val, '%');
			if($pos == 0) {
				$pat = '/'.strtolower(trim(str_replace('%', '', $val))).'$/';
			}
			elseif($pos == mb_strlen($val)-1) {
				$pat = '/^'.strtolower(trim(str_replace('%', '', $val))).'/';
			}
		} elseif($num == 2) {
			$pat = '/'.strtolower(trim(str_replace('%', '', $val))).'/';
		}
		if(false !== strpos($key, ' ')) return null;
		// Searching for entered value
		foreach($categories as $itemkey => $item) {
			if(!$pat && strtolower($item->{$key}) == strtolower($val)) return $item;
			elseif($pat && preg_match($pat, strtolower($item->{$key}))) return $item;
		}
		return null;
	}

	/**
	 * Returns the array of objects type Category, by a comparison of values
	 * NOTE: If $categories argument is not passed, the categories must already
	 * be in the buffer: CategoryMapper::$categories, call the CategoryMapper::init()
	 * to make sure that the categories are loaded into the buffer before proceeding.
	 *
	 * You can select the categories using any attributes
	 * Example getting categories by "name":
	 * CategoryMapper::getCategories('name=My Category Name')
	 *
	 * @param string $selector
	 * @param integer $key
	 * @param array|null $categories -
	 *
	 * @return boolean|array    - Array of categories
	 */
	public function getCategories($selector = '', $length = 0, $categories = array())
	{
		if(is_null($categories)) return null;
		$offset = 0;
		//settype($offset, 'integer');
		settype($length, 'integer');
		// reset offset
		$offset = ($offset > 0) ? $offset-1 : $offset;
		if($offset > 0 && $length > 0 && $offset >= $length) return null;
		if(!$categories) $categories = $this->categories;
		// nothing to select
		if(empty($categories)) return null;
		if(!empty($selector)) $arr = $this->applySearchPattern($categories, $selector);
		else $arr = $categories;
		// limited output
		if(!empty($arr) && ($offset > 0 || $length > 0)) {
			//if( $length == 0) $len = null;
			$arr = array_slice($arr, $offset, $length, true);
			return $this->reviseItemIds($arr);
		} else if(!empty($arr)) {
			return $this->reviseItemIds($arr);
		}

		return null;
	}

	/**
	 * Select categories by using several search patterns
	 *
	 * @param array $items - An array of categories to be processed
	 * @param $selector - Selector
	 *
	 * @return array|bool
	 */
	protected function applySearchPattern(array $items, $selector)
	{
		$res = array();
		$pattern = array(0 => '>=', 1 => '<=', 2 => '!=', 3 => '>', 4 => '<', 5 => '=');

		foreach($pattern as $pkey => $pval)
		{
			if(false === strpos($selector, $pval)) continue;

			$data = explode($pval, $selector, 2);
			$key = strtolower(trim($data[0]));
			$val = trim($data[1]);
			if(false !== strpos($key, ' ')) return false;

			$num = substr_count($val, '%');
			$pat = false;
			if($num == 1) {
				$pos = mb_strpos($val, '%');
				if($pos == 0) {
					$pat = '/'.strtolower(trim(str_replace('%', '', $val))).'$/';
				} elseif($pos == (mb_strlen($val)-1)) {
					$pat = '/^'.strtolower(trim(str_replace('%', '', $val))).'/';
				}
			} elseif($num == 2) {
				$pat = '/'.strtolower(trim(str_replace('%', '', $val))).'/';
			}

			foreach($items as $itemkey => $item)
			{
				if(!isset($item->$key)) { continue; }
				/*if(($key == 'id' || $key == 'position' || $key == 'created' || $key == 'updated') &&
					!is_numeric($val)) {
					return null;
				}*/
				if($pkey == 0) {
					if($item->$key < $val) continue;
				} elseif($pkey == 1) {
					if($item->$key > $val) continue;
				} elseif($pkey == 2) {
					if($item->$key == $val) continue;
				} elseif($pkey == 3) {
					if($item->$key <= $val) continue;
				} elseif($pkey == 4) {
					if($item->$key >= $val) continue;
				} elseif($pkey == 5) {
					if($item->$key != $val && !$pat) { continue; }
					elseif($pat && !preg_match($pat, strtolower($item->$key))){ continue; }
				}
				$res[$item->id] = $item;
			}

			if(!empty($res)) return $res;
			return false;
		}
		return false;
	}

	/**
	 * A public method for sorting the categories
	 *
	 * You can sort categories by using any attribute
	 * Default sortng attribute is "position":
	 * CategoryMapper::sort('position', 'DESC', $offset, $length)
	 *
	 * @param string $filterby - Filter by attribute
	 * @param string $order    - Order option
	 * @param array $items     - An category array
	 *
	 * @return boolean|array of Field objects
	 */
	public function sort($filterby = null, $order = 'asc',  $offset = 0, $length = 0, array $items = array())
	{
		settype($offset, 'integer');
		settype($length, 'integer');

		$offset = ($offset > 0) ? $offset-1 : $offset;

		$localItems = !empty($items) ? $items : $this->categories;

		if(empty($localItems)) return false;

		$this->filterby = ($filterby) ? $filterby : $this->imanager->config->filterByCategories;

		usort($localItems, array($this, 'sortObjects'));
		// sort DESCENDING
		if(strtolower($order) != 'asc') $localItems = $this->reverseItems($localItems);
		$localItems = $this->reviseItemIds($localItems);

		// Limiting item number
		if(!empty($localItems) && ($offset > 0 || $length > 0))
		{
			//if($length == 0) $len = null;
			$localItems = array_slice($localItems, $offset, $length, true);
		}

		if(!empty($items)) return $localItems;

		$this->categories = $localItems;
		return $this->categories;
	}


	/**
	 * Reverse the array of items
	 *
	 * @param array $itemContainer An array of objects
	 * @return boolean|array
	 */
	public function reverseItems($itemContainer)
	{
		if(!is_array($itemContainer)) return false;
		return array_reverse($itemContainer);
	}


	/**
	 * Revise keys of the array of categories and changes these into real item id's
	 *
	 * @param array $itemcontainer An array of objects
	 * @return boolean|array
	 */
	public function reviseItemIds($itemContainer)
	{
		if(!is_array($itemContainer)) return false;
		$result = array();
		foreach($itemContainer as $val) { $result[$val->id] = $val; }
		return $result;
	}

	/**
	 * Sorts the category objects
	 *
	 * @param $a $b objects to be sorted
	 * @return boolean
	 */
	private function sortObjects($a, $b)
	{
		$a = $a->{$this->filterby};
		$b = $b->{$this->filterby};
		if(is_numeric($a)) {
			if($a == $b) {return 0;}
			else{
				if($b > $a) {return -1;}
				else {return 1;}
			}
		} else {return strcasecmp($a, $b);}
	}

	/**
	 * Method deletes the respective Field or Category object.
	 *
	 * Before that, it checks whether this element exists.
	 *
	 * @param Category|Field $obj
	 *
	 * @return bool
	 */
	public function remove(& $obj, $complete = true)
	{
		if($obj instanceof Category) { return $this->removeCategory($obj); }
		elseif($obj instanceof Field) { return $this->imanager->fieldMappar->remove($obj, $complete); }

		throw new \ErrorException('Object type is unknown');
		return false;
	}

	/**
	 * Method deletes the respective Category object.
	 *
	 * Before that, it checks whether this element exists.
	 *
	 * @param Category $field
	 *
	 * @return bool
	 */
	protected function removeCategory(Category & $category)
	{
		$this->init();
		if(!isset($this->categories[$category->id])) {
			trigger_error('Category object does not exist', E_USER_WARNING);
			return false;
		}
		unset($this->categories[$category->id]);
		// Create a backup if necessary
		if($this->imanager->config->backupCategories) {
			Util::createBackup(dirname($this->path) . '/', basename($this->path, '.php'), '.php');
		}

		/* Delete item file */

		// Create a backup
		$itemsFile = IM_BUFFERPATH.'items/'.$category->id.'.items.php';
		if($this->imanager->config->backupItems) {
			Util::createBackup(dirname($itemsFile).'/', basename($itemsFile, '.php'),'.php');
		}
		if(file_exists($itemsFile)) { @unlink($itemsFile); }

		/* Delete field file */

		// Create a backup
		$fieldsFile = IM_BUFFERPATH.'fields/'.$category->id.'.fields.php';
		if($this->imanager->config->backupFields) {
			Util::createBackup(dirname($fieldsFile).'/', basename($fieldsFile, '.php'),'.php');
		}
		if(file_exists($fieldsFile)) { @unlink($fieldsFile); }

		/* And finally delete the Category */

		$export = var_export($this->categories, true);
		if(false !== file_put_contents($this->path, '<?php return ' . $export . '; ?>')) {
			@chmod($this->path, $this->imanager->config->chmodFile);
			$category = null;
			unset($category);
			return true;
		}
		trigger_error('Field object could not be deleted', E_USER_WARNING);
		return false;
	}

	/**
	 * Method deletes the respective Field object.
	 *
	 * Before that, it checks whether this element exists.
	 *
	 * @param Field $field
	 *
	 * @return bool
	 */
	/*protected function removeField(Field & $field, $complete = true)
	{
		$this->init($field->categoryid);
		if(!isset($this->fields[$field->name])) {
			trigger_error('Field object does not exist', E_USER_WARNING);
			return false;
		}
		unset($this->fields[$field->name]);
		// Create a backup if necessary
		if($this->imanager->config->backupFields) {
			Util::createBackup(dirname($this->path) . '/', basename($this->path, '.php'), '.php');
		}
		$categoryid = $field->categoryid;
		$export = var_export($this->fields, true);
		if(false !== file_put_contents($this->path, '<?php return ' . $export . '; ?>')) {
			@chmod($this->path, $this->imanager->config->chmodFile);
			$field = null;
			unset($field);
			// Now, prepare and save all the items of this category
			$this->imanager->itemMapper->rebuild($categoryid);
			return true;
		}
		trigger_error('Field object could not be deleted', E_USER_WARNING);
		return false;
	}*/
}
