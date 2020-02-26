<?php

namespace Scriptor;

use Imanager\TemplateParser;

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
	 * Site constructor.
	 *
	 * @param $config
	 */
	public function __construct() {
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
		parent::init();
		$this->themeUrl = $this->siteUrl.'/site/theme/';
		$this->input = $this->imanager->input;
		$this->segments = $this->input->urlSegments;
		$this->pages = $this->imanager->getCategory('name=Pages');
		$this->users = $this->imanager->getCategory('name=Users');
		$this->firstSegment = $this->segments->get(0);
		$this->lastSegment = $this->segments->getLast();
		$this->parsedown = $this->loadModule('parsedown');
		$this->version = Scriptor::VERSION;
	}

	public function execute()
	{
		// Home
		if(!$this->lastSegment) {
			$this->page = $this->pages->getItem(1);
			if(!$this->page || !$this->page->active) { return $this->throw404(); }
			$this->checkAction();
			$this->title = $this->page->name;
			foreach($this->page as $key => $param) {
				$this->$key = $param;
			}
		// Other pages
		} else {
			$total = $this->segments->total - 1;
			$this->page = $this->getPage($this->segments->segment, $total);
			if(!$this->page || !$this->page->active) {
				return $this->throw404();
			}
			$curentUrl = $this->segments->getUrl();
			$pageUrl = self::getPageUrl($this->page, $this->pages);
			if(strpos($curentUrl, $pageUrl) === false) {
				return $this->throw404();
			}
			$this->title = $this->page->name;
			foreach($this->page as $key => $param) {
				$this->$key = $param;
			}
		}
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
		$return .= $item->slug.'/';
		return  $return;
	}

	public function render($element)
	{
		$name = ($this->lastSegment) ? "$this->lastSegment-$element" : $element;

		if($element == 'navigation') {
			if(!$navi = $this->imanager->sectionCache->get($name, $this->config['markup_cache_time'])) {
				$navi = $this->buildNavi();
				if($navi) {
					$this->imanager->sectionCache->save($navi);
				}
			}
			return $navi;
		}
		elseif($element == 'content') {
			if(!$content = $this->imanager->sectionCache->get($name, $this->config['markup_cache_time'])) {
				$content = $this->templateParser->render($this->content, [
					'BASE_URL' => $this->siteUrl,
					'UPLOADS_URL' => $this->siteUrl.'/data/uploads/'
				]);
				if(true !== $this->config['allowHtmlOutput']) {
					$this->parsedown->setSafeMode(true);
				}
				$content = $this->parsedown->text(htmlspecialchars_decode($content)); 
				$this->imanager->sectionCache->save($content);
			}
			return $content;
		}
	}

	protected function getPage($segments, $index = 0, $pages = null)
	{
		if(!isset($segments[$index])) return null;

		$pages = $this->pages->getItems('slug='.$this->imanager->sanitizer->pageName($segments[($index)]));

		if(!$pages) { return null; }
		elseif(count($pages) == 1) {
			return array_values($pages)[0];
		}

		// There are multiple pages with same slug
		$parent = $this->getPage($segments, --$index, $pages);
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
		$topl = $this->pages->getItems('active=1', 0, $topl);
		$topl = $this->pages->sort('position', 'asc', 0, 0, $topl);
		if(!$topl) return $navi;
		foreach($topl as $item) {
			$all_pages = $this->pages->getItems("active=1");
			$all_pages = $this->pages->sort('position', 'asc', 0, 0, $all_pages);
			$navi .= $this->getChildren($item, $all_pages, rtrim($this->siteUrl, '/') . '/');
		}
		return $navi;
	}

	protected function getChildren($item, & $items, $url, $children = '')
	{
		$childs = $this->pages->getItems("parent=$item->id", 0, $items);
		if($childs) {
			$prefix = '<li' . $this->getClass($item) . '><a href="' .
				$url.(($item->id != 1 && !$item->parent) ? "$item->slug/" : '') . '">' . $item->name . '</a>';
			$buff = '';
			foreach($childs as $curitem) {
				$buff .= $this->getChildren($curitem, $items,
					$url.((!$item->parent) ? "$item->slug/" : '') . $curitem->slug . '/', $children);
			}
			$children = $prefix . '<ul>' . $buff . '</ul></li>';
		} else {
			$children = '<li'.$this->getClass($item).'><a href="'.
				$url.(($item->id != 1 && !$item->parent) ? "$item->slug/" : '').'">'.$item->name.'</a></li>';
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

	protected function throw404()
	{
		header("HTTP/1.0 404 Not Found");
		include 'site/theme/'.$this->config['404page'].'.php';
		die;
	}
}