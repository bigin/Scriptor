<?php namespace Imanager;

/**
 * Class ItemManager
 * @package Imanager
 */
class ItemManager extends Manager
{
	/**
	 * Just for internal use.
	 * ItemManager instances count
	 *
	 * @var int $counter
	 */
	public static $counter = 0;

	/**
	 * ItemManager constructor.
	 */
	public function __construct() {
		self::$counter++;
		parent::__construct();
	}

	/**
	 * A wrapper for TemplateParser's renderPagination
	 *
	 * @param $items
	 * @param array $params
	 * @param array $argtpls
	 *
	 * @return string - Pagination markup
	 */
	public function paginate(& $items, array $params = array(), $argtpls = array()) {
		return $this->templateParser->renderPagination($items, $params, $argtpls);
	}

	/**
	 * Categorie selector
	 *
	 * @param $selector
	 * @param int $offset
	 * @param int $length
	 * @param array $categories
	 *
	 * @return mixed|array - An array of Category objects
	 */
	public function getCategories($selector = '', $length = 0, array $categories = array()) {
		if(empty($this->categoryMapper->categories) && !$categories) {
			$this->categoryMapper->init();
		}
		return $this->categoryMapper->getCategories($selector, $length, $categories);
	}

	/**
	 * @param $selector
	 * @param array $categories
	 *
	 * @return mixed|Category - Catagory object
	 */
	public function getCategory($selector, array $categories = array()) {
		if(empty($this->categoryMapper->categories) && !$categories) {
			$this->categoryMapper->init();
		}
		return $this->categoryMapper->getCategory($selector, $categories);
	}

	/**
	 * A public method for sorting the categories and items
	 *
	 * You can sort categories/items by using any attribute.
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
	public function sort($filterby = 'position', $order = 'asc',  $offset = 0, $length = 0, array $items = array())
	{
		if($items && (array_values($items)[0] instanceof Item)) {
			return $this->itemMapper->sort($filterby, $order, $offset, $length, $items);
		} else if($items && (array_values($items)[0] instanceof Category)) {
			return $this->categoryMapper->sort($filterby, $order, $offset, $length, $items);
		}

		trigger_error('Object type is unknown', E_USER_WARNING);
		return false;
	}

	/**
	 * Removes one of the child objects
	 *
	 * @param Item|Field|Category $obj - The object that you want to delete
	 * @param bool $complete
	 *
	 * @return bool
	 */
	public function remove(& $obj, $complete = true)
	{
		if($obj instanceof Item) {
			return $this->itemMapper->remove($obj, $complete);
		}
		elseif($obj instanceof Field) {
			return $this->fieldMapper->remove($obj, $complete);
		}
		elseif($obj instanceof Category) {
			return $this->categoryMapper->remove($obj);
		}

		trigger_error('Object type is unknown', E_USER_WARNING);
		return false;
	}
}
