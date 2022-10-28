<?php 
namespace Scriptor\Core;

class Pages extends Module
{
	public $config;

	public $category;

	public function __construct()
	{
		parent::init();
		$this->config = Scriptor::getProperty('config');
		$this->category = $this->imanager->getCategory('name=Pages');
		$this->sanitizer = $this->imanager->sanitizer;
	}

	/**
	 * Searches the records for a specific page using a selector statement.
	 * 
	 * @param int|string $selector
	 * @param array $pages
	 */
	public function getPage(int|string $selector, array $pages = [])
	{
		return $this->category->getItem($selector, $pages);
	}
	
	/**
	 * Searches the records with a selector statement for any amount of the pages.
	 * 
	 * @param string $selector - e.g. 'field_name=value' or 'attribute_name=value'
	 * @param array $conds - Conditions
	 */
	public function getPages(string $selector = '', array $conds = []) :?array
	{
		$setup = array_merge( [
			'all' => false,
			'sortBy' => 'position',
			'order' => 'asc',
			'offset' => 0,
			'length' => 0,
			'items' => []
		], $conds);
		
		$pages = $this->category->getItems($selector, 0, 0, $setup['items']);
		if (!$pages) return null;

		if (!$setup['all']) {
			$active = $this->category->getItems('active=1', 0, 0, $pages);
			if(!$active) return null;
			$pages = $active;
		}
		$total = count($pages);
		if($setup['sortBy'] != 'position' || strtolower($setup['order']) != 'asc') {
			$pages = $this->category->sort($setup['sortBy'], $setup['order'], 0, 0, $pages);
		}
		if(!empty($pages) && ($setup['offset'] > 0 || $setup['length'] > 0)) {
			$pages = $this->category->getItems('', $setup['offset'], $setup['length'], $pages);
		}

		return ['pages' => $pages, 'total' => $total];
	}

	/**
	 * Do not select, only sort.
	 * 
	 * @param array $conds - The sorting conditions. 
	 */
	public function sortPages(array $conds = [])
	{
		$setup = array_merge([
			'sortBy' => 'position', // string sort by attribute or field name
			'order' => 'asc',
			'offset' => 0,
			'length' => 0,
			'items' => []
		], $conds);

		return $this->category->sort(
			$setup['sortBy'], 
			$setup['order'], 
			$setup['offset'], 
			$setup['length'], 
			$setup['items']
		);
	}

	public function getPageLevels($options = [], $pages = []) :array
	{
		$configs = array_merge([
			'parent' => 0,
			'maxLevel' => 1,
			'sortBy' => 'position',
			'order' => 'asc',
			'active' => true,
			'exclude' => []
		], $options);
		
		$topl = $this->category->getItems("parent=$configs[parent]");
		if(! $topl) return $pages;
		if($configs['active']) {
			$topl = $this->category->getItems('active=1', 0, 0, $topl);
		}
		if(is_array($configs['exclude']) && ! empty($configs['exclude'])) {
			foreach($configs['exclude'] as $exclude) {
				$topl = $this->category->getItems("parent!=$exclude", 0, 0, $topl);
				$topl = $this->category->getItems("id!=$exclude", 0, 0, $topl);
			}
		}
		$topl = $this->category->sort($configs['sortBy'], $configs['order'], 0, 0, $topl);
		$pages[$configs['parent']] = $topl;
		if(count($pages) < $configs['maxLevel']) {
			foreach($topl as $item) {
				$buff = $this->getPageLevels(['parent' => $item->id] + $configs, $pages);
				$pages = $pages + $buff;
			}
		}
		return $pages;
	}

	public function getParentPages($options = [])
	{
		$return = [];
		$defaults = [
			'page' => $this->page->id, // Page id
			'maxLevel' => 0,           // Zero means unlimited
			'active' => true           // Active pages only
		];
		$configs = array_merge($defaults, $options);

		$par = $this->category->getItem($configs['page']);
		if($configs['active'] && ! $par->active) return null;
		$return[] = $par;
		if(($configs['maxLevel'] > 0 && count($return) >= $configs['maxLevel']) 
		   || ! $par->parent) return $return;
		
		   $options['page'] = $par->parent;
		
		$res = $this->getParentPages($options);
		if(isset($res) && is_array($res)) {
			foreach($res as $arr) $return[] = $arr;
		} else {
			$return[] = $res;
		}

		return $return;
	}

	/**
	 * Method for determining the IDs of the children under a page
	 * 
	 * @param Page $page
	 * @param array $options
	 * @param array $idBuffer
	 */
	public function getPageChildrenIds(
		Page $page, 
		array $options = [], 
		array $idBuffer = []) :array 
	{

		$configs = array_merge([
			'all' => false,
			'sortBy' => 'position',
			'order' => 'asc',
			'offset' => 0,
			'length' => 0,
			'items' => []
		], $options);

		$childs = $this->getPages("parent=$page->id", $configs);
		if ($childs) {
			foreach ($childs['pages'] as $p) {
				$idBuffer[] = $p->id;
				$idBuffer = $this->getPageChildrenIds($p, $configs, $idBuffer);
			}
		}
		return $idBuffer;
	}

	public function getPageBySegment($segments, $index = 0, $pages = null)
	{
		if(!isset($segments[$index])) return null;

		$pages = $this->category->getItems('slug='.$this->sanitizer->pageName($segments[($index)]));

		if(!$pages) { return null; }
		elseif(count($pages) == 1) {
			return array_values($pages)[0];
		}

		// There are multiple pages with same slug
		$parent = $this->getPageBySegment($segments, --$index, $pages);
		if(!$parent) { return null; }

		foreach($pages as $page) {
			if($page->parent == $parent->id) {  return $page; }
		}
		return null;
	}

	/**
	 * Delete a page.
	 * 
	 * You can pass a number of parameters to specify how the 
	 * deletion should be done. 
	 * 
	 * If you set the "recursive" option to "true", all child pages 
	 * will be deleted as well. 
	 * 
	 * Ohh, and the cache will be flushed too, if you don't want that, 
	 * set the parameter "clearCache" to false.
	 * 
	 * @param int|object $target
	 * @param array $opts
	 * @throws \ErrorException
	 */
	public function deletePage(int|object $target, array $opts = []) : bool
	{
		$options = array_merge([
			'clearCache' => true,
			'recursive' => false, // delete recursively
			'force' => false
		], $opts);

		if (is_integer($target)) {
			$page = $this->getPage($target);
			$pageId = $target;
		} else {
			$page = $this->getPage((int) $target->id);// do not use a passed object
			$pageId = $target->id;
		}

		if (!is_object($page)) {
			throw new \ErrorException("Page ID '$pageId' not found.");
		}

		if (!$this->isDeletionSafe($page, $options)) {
			throw new \ErrorException('The operation was not approved.');
		}
		
		$this->completeDeletion($page, $options['recursive']);

		if ($options['clearCache']) {
			$this->imanager->sectionCache->expire();
		}

		return true;
	}

	/**
	 * If there is at least one child page or the page you want 
	 * to delete has an ID 1, deletion should not be possible.
	 * 
	 * Never allow the deletion of a page with id 1.
	 * 
	 * @param object $page - Page/Item Object
	 * @param array $opts - Deletion options
	 */
	private function isDeletionSafe(object $page, array $opts = []) :bool
	{
		$options = array_merge([
			'recursive' => false,
			'force' => false
		], $opts);

		if ($page->id == 1) {
			$this->addNote([
				'type' => 'error',
				'value' => $this->i18n['error_deleting_first_page']
			]);
			return false;
		}

		// If the force option is set, child pages will not be checked.
		if ($options['force']) return true;
		// does it have the child pages?
		$child = $this->getPage("parent=$page->id");
		if ($child) {
			if ($options['recursive']) return true;
			$this->addNote([
				'type' => 'error',
				'value' => $this->i18n['error_remove_parent_page']
			]);
			return false;
		}
		return true;
	}

	/**
	 * Finishes the deletion process.
	 * 
	 * If the "recursive" parameter is set to "true", the child pages 
	 * will also be deleted recursively. 
	 * 
	 * @param object $page
	 * @param bool $recursive
	 * @throws \ErrorException
	 */
	private function completeDeletion(object $page, bool $recursive = false) :bool
	{
		if (!$recursive) {
			if (!$this->category->remove($page)) {
				$this->addNote([
					'type' => 'error',
					'value' => $this->i18n['error_operation_failed']
				]);
				throw new \ErrorException("The operation was not successful.");
			}
			return true;
		}
		
		$pageIds = $this->getPageChildrenIds($page, [], [$page->id]);
		if (empty($pageIds)) return true;

		foreach ($pageIds as $id) {
			$trash = $this->getPage((int) $id);
			if ($trash && ! $this->category->remove($trash)) {
				$this->addNote([
					'type' => 'error',
					'value' => $this->i18n['error_operation_failed']
				]);
				throw new \ErrorException("The operation was not successful.");
			}
		}
		return true;
	}
}