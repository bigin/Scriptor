<?php

namespace Scriptor\Core;

use Imanager\Item;
use Imanager\TemplateParser;
use Imanager\Util;

class Site extends Module
{
	/**
	 * @var object $imanager - Instance of IManager
	 */
	public $imanager;

	/**
	 * @var array $config - Scriptor config
	 */
	public $config;

	/**
	 * @var string $pageTitle - Meta page title
	 */
	public $pageTitle;

	/**
	 * @var string $themeUrl - Theme URL
	 */
	public $themeUrl;

	/**
	 * @var object $input - Input object instance
	 */
	public $input;

	/**
	 * @var object $segments - Segments object instance
	 */
	public $segments;

	/**
	 * @var string $firstSegment - Buffered first url segment
	 */
	public $firstSegment;

	/**
	 * @var string $lastSegment - Buffered last url segment
	 */
	public $lastSegment;

	/**
	 * @var object $pages - An object of category Pages
	 */
	public $pages;

	/**
	 * @var object $users - An object of category Users
	 */
	public $users;

	/**
	 * @var string $messages - rendered messages (markup)
	 */
	public $messages;

	/**
	 * @var object $page - The current page object instance
	 */
	public $page;

	/**
	 * @var object $user - The current user object instance
	 */
	public $user;

	/**
	 * @var string $breadcrumbs - Breadcrumbs markup
	 */
	public $breadcrumbs;

	/**
	 * @var string $title - Page title
	 */
	public $title;

	/**
	 * @var string $pageContent - Current page content
	 */
	public $content;

	/**
	 * @var string $version - Scriptor version
	 */
	public $version;

	/**
	 * @var object - ParseDown instance
	 */
	public $parsedown;

	/**
	 * @var object - ItemManager's TemplateParser instance
	 */
	public $templateParser;

	/**
	 * @var array - Messages container
	 * 
	 */
	protected static $notes = [];

	/**
	 * Site constructor.
	 *
	 * @param $config
	 */
	public function __construct() 
	{
		$this->config = Scriptor::getProperty('config');
		$this->templateParser = new TemplateParser();
	}

	/**
	 * Init Site class
	 * Prepares some variables for local use and executes actions
	 *
	 */
	public function init()
	{
		if (isset($this->config['sessionAllow'])) $this->checkCookieAllowed();
		parent::init();
		$this->themeUrl = $this->siteUrl.'/site/themes/'.$this->config['theme_path'];
		$this->input = $this->imanager->input;
		$this->segments = $this->input->urlSegments;
		$this->pages = $this->imanager->getCategory('name=Pages');
		$this->users = $this->imanager->getCategory('name=Users');
		$this->firstSegment = $this->segments->get(0);
		$this->lastSegment = $this->segments->getLast();
		$this->parsedown = $this->loadModule('parsedown', ['namespace' => __NAMESPACE__.'\Modules\\']);
		$this->version = Scriptor::VERSION;
	}

	/**
	 * Attempts to define current page using URL segments, if 
	 * no matching one is found throw 404.
	 * 
	 */
	public function execute() :void
	{
		if (Scriptor::execHook(
			$this, 'execute', [], 'before'
			) && $this->event->replace) return;

		// Home
		if (!$this->lastSegment) {
			$this->page = $this->pages->getItem(1);
			if(!$this->page || !$this->page->active) { 
				$this->throw404(); 
			}
		// other pages
		} else {
			$total = $this->segments->total - 1;
			$this->page = $this->getPageBySegment($this->segments->segment, $total);
			if(!$this->page || !$this->page->active) {
				$this->throw404();
			}
			$curentUrl = $this->segments->getUrl();
			$pageUrl = self::getPageUrl($this->page, $this->pages);
			if(strpos($curentUrl, $pageUrl) === false) {
				$this->throw404();
			}
		}
	}

	/**
	 * Searches the records for a specific page using a selector statement.
	 * 
	 * @param int|string $selector
	 * @param array $pages
	 */
	public function getPage(int|string $selector, array $pages = [])
	{
		return $this->pages->getItem($selector, $pages);
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
		
		$pages = $this->pages->getItems($selector, 0, 0, $setup['items']);
		if (!$pages) return null;

		if (!$setup['all']) {
			$active = $this->pages->getItems('active=1', 0, 0, $pages);
			if(!$active) return null;
			$pages = $active;
		}
		$total = count($pages);
		if($setup['sortBy'] != 'position' || strtolower($setup['order']) != 'asc') {
			$pages = $this->pages->sort($setup['sortBy'], $setup['order'], 0, 0, $pages);
		}
		if(!empty($pages) && ($setup['offset'] > 0 || $setup['length'] > 0)) {
			$pages = $this->pages->getItems('', $setup['offset'], $setup['length'], $pages);
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

		return $this->pages->sort(
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
		
		$topl = $this->pages->getItems("parent=$configs[parent]");
		if(! $topl) return $pages;
		if($configs['active']) {
			$topl = $this->pages->getItems('active=1', 0, 0, $topl);
		}
		if(is_array($configs['exclude']) && ! empty($configs['exclude'])) {
			foreach($configs['exclude'] as $exclude) {
				$topl = $this->pages->getItems("parent!=$exclude", 0, 0, $topl);
				$topl = $this->pages->getItems("id!=$exclude", 0, 0, $topl);
			}
		}
		$topl = $this->pages->sort($configs['sortBy'], $configs['order'], 0, 0, $topl);
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

		$par = $this->pages->getItem($configs['page']);
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
	 */
	public function deletePage(int|object $target, array $opts = []) : bool
	{
		$options = array_merge([
			'clearCache' => true,
			'recursive' => false, // delete recursively
			'force' => true
		], $opts);

		if (is_integer($target)) {
			$page = $this->getPage($target);
		} else {
			$page = $this->getPage((int) $target->id);// do not use a passed object 
		}

		if (!is_object($page)) {
			self::addNote([
				'type' => 'error',
				'value' => $this->i18n['page_not_found']
			]);
			return false;
		}

		if (!$this->isDeletionSafe($page, $options)) return false;
		
		if ($this->completeDeletion($page, $options['recursive'])) {
			if ($options['clearCache']) {
				$this->imanager->sectionCache->expire();
			}
			return true;
		}

		return false;
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
			self::addNote([
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
			self::addNote([
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
	 */
	private function completeDeletion(object $page, bool $recursive = false) :bool
	{
		if (!$recursive) return $this->pages->remove($page);
		
		$res = true;
		$pageIds = $this->getPageChildrenIds($page, [], [$page->id]);
		if (empty($pageIds)) return $res;

		foreach ($pageIds as $id) {
			$trash = $this->getPage((int) $id);
			if ($trash && ! $this->pages->remove($trash)) {
				self::addNote([
					'type' => 'error',
					'value' => $this->i18n['error_deleting_page']
				]);
				$res = false;
			}
		}
		return $res;
	}

	public static function getPageUrl($item, $pages)
	{
		$return = '';
		if($item->parent) {
			$parent = $pages->items[$item->parent];
			if($parent) {
				$return .= self::getPageUrl($parent, $pages);
			}
		}
		$return .= ($item->id != 1) ? $item->slug.'/' : '';
		return  $return;
	}

	public function ___render(string $element) :?string
	{
		$name = ($this->lastSegment) ? "$this->lastSegment-$element" : $element;

		if($element == 'navigation') {
			$navi = $this->buildNavi();
			return $navi;
		} elseif($element == 'content') {
			$imgPath = '';
			$field = $this->pages->getField('name=images');
			$pageId = isset($this->page->id) ? $this->page->id : null;
			if($pageId && $field && $this->pages) {
				$imgPath = $this->pages->id.".$pageId.$field->id/";
			} else {
				$imgPath = ".tmp_{$this->input->post->timestamp_images}_{$this->pages->id}.$field->id/";
			}
			$content = $this->templateParser->render($this->page->content, [
				'BASE_URL' => $this->siteUrl,
				'UPLOADS_URL' => $this->siteUrl.'/data/uploads/',
				'IMAGES_URL' => $this->siteUrl."/data/uploads/$imgPath"
			]);
			if(true !== $this->config['allowHtmlOutput']) {
				$this->parsedown->setSafeMode(true);
				$content = $this->parsedown->text(htmlspecialchars_decode($content));
			} else {
				$content = $this->parsedown->text(htmlspecialchars_decode($content));
			}
			
			return $content;
		}

		return null;
	}

	protected function getPageBySegment($segments, $index = 0, $pages = null)
	{
		if(!isset($segments[$index])) return null;

		$pages = $this->pages->getItems('slug='.$this->imanager->sanitizer->pageName($segments[($index)]));

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

	protected function buildNavi()
	{
		$navi = '';
		$topl = $this->pages->getItems('parent=0');
		// Todo: check if topl exists if 0 pages created
		$topl = $this->pages->getItems('active=1', 0, 0, $topl);
		$topl = $this->pages->sort('position', 'asc', 0, 0, $topl);
		if(!$topl) return $navi;
		foreach($topl as $item) {
			$all_pages = $this->pages->getItems("active=1");
			$all_pages = $this->pages->sort('position', 'asc', 0, 0, $all_pages);
			$navi .= $this->getNaviChildren($item, $all_pages, rtrim($this->siteUrl, '/') . '/');
		}
		return $navi;
	}

	/**
	 * TODO: This function should be moved off-site
	 */
	protected function getNaviChildren($item, & $items, $url, $children = '')
	{
		$childs = $this->pages->getItems("parent=$item->id", 0, 0, $items);
		if ($childs) {
			$prefix = '<li' . $this->getClass($item) . '><a href="' .
				$url.(($item->id != 1 && !$item->parent) ? "$item->slug/" : '') . '">'.
					($item->menu_title ?? $item->name).'</a>';
			$buff = '';
			foreach ($childs as $curitem) {
				$buff .= $this->getNaviChildren($curitem, $items,
					$url.((!$item->parent) ? "$item->slug/" : '') . $curitem->slug . '/', $children);
			}
			$children = $prefix . '<ul>' . $buff . '</ul></li>';
		} else {
			$children = '<li'.$this->getClass($item).'><a href="'.
				$url.(($item->id != 1 && !$item->parent) ? "$item->slug/" : '').'">'.
					($item->menu_title ?? $item->name).'</a></li>';
		}
		return  $children;
	}


	protected function parrentOf(& $item, $current)
	{
		if(!$current->parent) {
			return false;
		}
		else if($item->id == $current->parent) {
			return true;
		}
		else {
			$parent = $this->pages->getItem((int)$current->parent);
			if($parent) {
				return $this->parrentOf($item, $parent);
			}
			return false;
		}
	}

	/**
	 * TODO: This function should be moved off-site
	 */
	protected function getClass(& $item)
	{
		$class = null;
		if($this->parrentOf($item, $this->page)) {
			$class = 'active';
		}
		if($item->id == $this->page->id) {
			$class = ($class) ? "$class current" : 'current';
		}
		return (($class) ? ' class="'.$class.'"' : '');
	}

	/**
	 * Add note to the container
	 * 
	 * @param array $note 
	 * @param int $key
	 */
	protected function addNote(array $note, int $key = null) :?int
	{
		if ($key) self::$notes[$key] = $note;
		else self::$notes[] = $note;

		return ($key) ? $key : count(self::$notes)-1;
	}

	public function getNote(int $key)
	{
		return self::$notes[$key] ?? null;
	}

	public function getNotes()
	{
		return self::$notes;
	}

	/**
	 * Throws 404 page
	 */
	public function throw404()
	{
		header("HTTP/1.0 404 Not Found");
		include 'site/themes/'.$this->config['theme_path'].$this->config['404page'].'.php';
		die;
	}
	
	/**
	 * It checks if the value of the config 
	 * variable sessionAllow is set to false, 
	 * if so session and cookies should not 
	 * be used.
	 * 
	 */
	protected function checkCookieAllowed(): void
	{
		if($this->config['sessionAllow'] instanceof \Closure) {
			$allowed = $this->config['sessionAllow']();
		} else { 
			$allowed = $this->config['sessionAllow']; 
		}

		if($allowed) {
			if(! isset($_SESSION)) { 
				session_name('IMSESSID');
				session_start(); 
			}
			return;
		}

		if(ini_get('session.use_cookies')) {
			$params = session_get_cookie_params();
			setcookie(session_name('IMSESSID'), '', time() - 42000, $params['path'],
				$params['domain'], $params['secure'], $params['httponly']
			);
		}
		
		! isset($_SESSION) OR session_destroy();
	}
}