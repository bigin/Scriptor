<?php namespace Imanager;

class ItemMapper extends Mapper
{
	/**
	 * @var string filter by node
	 */
	protected $filterby;

	/**
	 * @var boolean indicates to searchig field values
	 */
	private $fieldflag = false;

	/**
	 * @var int - Total number of items
	 */
	public $total = 0;

	/**
	 * @var null|string - Path to the items file
	 */
	public $path = null;

	/**
	 * @var - An array of the Item objects
	 */
	public $items = array();

	/**
	 * @var bool - An initialize flag for intern use
	 */
	private static $initialized = false;

	/**
	 * @var null|int - Current initialized category id
	 */
	private static $category_id = null;

	/**
	 * Regular init method for the item objects of a given category id.
	 *
	 * This method uses control structure to avoid unnecessary
	 * reinitialization.
	 *
	 * Since ItemManager v 3.1.1 it supports a force parameter to force
	 * initialization.
	 *
	 * @param $category_id
	 * @param bool $force
	 *
	 * @return bool
	 */
	public function init(int $category_id, bool $force = false)
	{
		if(self::$initialized && !$force && self::$category_id === $category_id) return true;
		self::$category_id = $category_id;

		parent::___init();
		$this->path = IM_BUFFERPATH.'items/'.(int) $category_id.'.items.php';

		if(!file_exists(dirname($this->path))) {
			Util::install($this->path);
		}
		if(file_exists($this->path)) {
			(!Util::isOpCacheEnabled()) or Util::clearOpCache($this->path);
			$this->items = include $this->path;
			if(is_array($this->items)) {
				$this->total = count($this->items);
			} else {
				$this->items = array();
				$this->total = 0;
			}
			self::$initialized = true;
			return true;
		}
		unset($this->items);
		$this->items = null;
		$this->total = 0;
		return false;
	}

	/**
	 * Method deletes the passed Item object
	 *
	 * @param Item $item
	 *
	 * @return bool
	 */
	public function remove(Item & $item, $complete = true)
	{
		$this->init($item->categoryid);
		if(!isset($this->items[$item->id])) return false;

		// delete assets?
		if($complete) {
			$itemid = $item->id;
			$categoryid = $item->categoryid;
			if(file_exists(IM_UPLOADPATH)) {
				foreach(glob(IM_UPLOADPATH."$categoryid.$itemid.*") as $file) {
					if(file_exists($file)) { Util::delTree($file); }
				}
			}
		}

		unset($this->items[$item->id]);
		// Create a backup if necessary
		if($this->imanager->config->backupItems){
			Util::createBackup(dirname($this->path).'/', basename($this->path, '.php'), '.php');
		}
		$export = var_export($this->items, true);
		if(false !== file_put_contents($this->path, '<?php return ' . $export . '; ?>')) {
			@chmod($this->path, $this->imanager->config->chmodFile);
			$item = null;
			unset($item);
			return true;
		}
		trigger_error('Item object could not be deleted', E_USER_WARNING);
		return false;
	}

	/**
	 * Initializes all items and made them available in ImItem::$items array
	 * NOTE: Could be extrem slow and memory intensive with high data volumes
	 *
	 * @return bool|mixed
	 */
	public function initAll()
	{

	}

	/**
	 * Returns a total number of given items
	 *
	 * @param array $items
	 *
	 * @return int
	 */
	public function countItems(array $items=array()) {
		return !empty($items) ? count($items) : count($this->items);
	}

	/**
	 * Get the Item matching the given selector string without exclusions. Returns an Item, or a NULL if not found.
	 *
	 * @param $selector - Selector
	 * @param array|null $items
	 *
	 * @return bool|mixed
	 */
	public function getItem($selector, $items = array())
	{
		if(is_null($items)) return null;
		// No items selected
		if(!empty($items)) $this->items = $items;
		if(!$this->items) return;
		// A nummeric value, id was entered?
		if(is_numeric($selector)) return !empty($this->items[$selector]) ? $this->items[$selector] : null;
		// Separate selector
		$data = explode('=', $selector, 2);
		$key = strtolower(trim($data[0]));
		$val = trim($data[1]);
		$num = substr_count($val, '%');
		$pat = false;
		if($num == 1) {
			$pos = mb_strpos($val, '%');
			if($pos == 0) { $pat = '/'.strtolower(trim(str_replace('%', '', $val))).'$/';}
			elseif($pos == mb_strlen($val)-1) {$pat = '/^'.
				strtolower(trim(str_replace('%', '', $val))).'/';}
		} elseif($num == 2) {
			$pat = '/'.strtolower(trim(str_replace('%', '', $val))).'/';
		}
		if(false !== strpos($key, ' ')) return null;
		// Searching for entered value
		foreach($this->items as $itemkey => $item) {
			if(!$pat && strtolower($item->{$key}) == strtolower($val)) return $item;
			elseif($pat && preg_match($pat, strtolower($item->{$key}))) return $item;
		}
		return null;
	}

	/**
	 * Select method for multiple items
	 *
	 * @param $selector        - Selector
	 * @param int $length  - A clause that is used to specify the number of records to return
	 * @param array $items - Item array rekursion
	 *
	 * @return array|bool
	 */
	public function getItems($selector = '', $length = 0, $items = array())
	{
		$offset = 0;
		settype($length, 'integer');
		// reset offset
		$offset = ($offset > 0) ? $offset-1 : $offset;
		if(empty($items) && is_array($items)) $items = $this->items;
		// nothing to select
		if(!$items) return null;

		if(!empty($selector)) $arr = $this->applySearchPattern($items, $selector);
		else $arr = $items;
		// limited output
		if(!empty($arr) && ($offset > 0 || $length > 0)) {
			//if($length == 0) $len = null;
			$arr = array_slice($arr, $offset, $length, true);
			return $this->reviseItemIds($arr);
		} else if(!empty($arr)) {
			return $this->reviseItemIds($arr);
		}

		return null;
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
	 *
	 * @return boolean|array   - An array of Item objects
	 */
	public function sort($filterby = null, $order = 'asc',  $offset = 0, $length = 0, array $items = array())
	{
		settype($offset, 'integer');
		settype($length, 'integer');

		$offset = ($offset) ? $offset :
			(($this->imanager->input->pageNumber) ? (($this->imanager->input->pageNumber -1) * $length) : 0);

		$localItems = (!empty($items) ? $items : $this->items);

		if(empty($localItems)) return false;

		$this->filterby = ($filterby) ? $filterby : $this->imanager->config->filterByItems;

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

		$this->items = $localItems;
		return $this->items;
	}

	/**
	 * Select items by using search patterns
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
			if(false !== strpos($key, ' ')) return null;

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
				/*if(($key == 'categoryid' || $key == 'id' || $key == 'position' || $key == 'created' ||
					$key == 'updated') && !is_numeric($val)) {
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
			return null;
		}
		return null;
	}

	/**
	 * Sorts the item objects
	 *
	 * @param $a $b objects to be sorted
	 *
	 * @return boolean
	 */
	protected function sortObjects($a, $b)
	{
		$a = $a->{$this->filterby};
		$b = $b->{$this->filterby};
		if(is_numeric($a)) {
			if($a == $b) {return 0;}
			else if($b > $a) {return -1;}
			else {return 1;}
		}
		return strcasecmp($a, $b);
	}

	/**
	 * Reverse the array of items
	 *
	 * @param array $itemsContainer An array of objects
	 *
	 * @return boolean|array
	 */
	public function reverseItems($itemsContainer)
	{
		if(!is_array($itemsContainer)) return false;
		return array_reverse($itemsContainer);
	}

	/**
	 * Revise keys of the array of items and changes these into real item id's
	 *
	 * @param array $itemsContainer An array of objects
	 *
	 * @return boolean|array
	 */
	public function reviseItemIds($itemsContainer)
	{
		if(!is_array($itemsContainer)) return false;
		$result = array();
		foreach($itemsContainer as $val) { $result[$val->id] = $val; }
		return $result;
	}

	/**
	 * Recreates an item buffer completely
	 *
	 * @param $category_id
	 *
	 * @return bool
	 */
	public function rebuild($category_id, array $options = array())
	{
		$this->init($category_id);
		if($this->items) {
			$items = array();
			foreach($this->items as $item) {
				$item->declutter();
				$items[$item->id] = $item;
				// Delete assets related to a field?
				if($options['removeAssets'] && $options['fieldId']) {
					if(file_exists(IM_UPLOADPATH)) {
						foreach(glob(IM_UPLOADPATH."$item->categoryid.$item->id.$options[fieldId]") as $file) {
							if(file_exists($file)) { Util::delTree($file); }
						}
					}
				}
			}
			if($items) {
				if($this->imanager->config->backupItems) {
					Util::createBackup(dirname($this->path).'/', basename($this->path, '.php'), '.php');
				}
				$export = var_export($items, true);
				file_put_contents($this->path, '<?php return ' . $export . '; ?>');
				@chmod($this->path, $this->imanager->config->chmodFile);

				return true;
			}
			trigger_error('Items could not be rebuild', E_USER_WARNING);
			return false;
		}
		return true;
	}
}
