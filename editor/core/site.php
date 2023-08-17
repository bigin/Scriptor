<?php

namespace Scriptor\Core;

use Imanager\Input;
use Imanager\Item;
use Imanager\SectionCache;
use Imanager\TemplateParser;
use Imanager\UrlSegments;
use Imanager\Util;
use Scriptor\Core\Modules\Parsedown;

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
	 * @var object $urlSegments - urlSegments object instance
	 */
	public $urlSegments;

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
	//public $pages;

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
	 * @var object - ItemManager's TemplateParser instance
	 */
	public $templateParser;

	/**
	 * @var Scriptor\Core\Modules\Parsedown
	 */
	public $parsedown;

	/**
	 * Site constructor.
	 *
	 * @param $config
	 */
	public function __construct() 
	{
		$this->config = Scriptor::getProperty('config');
	}

	/**
	 * Init Site class
	 * Loads some stuff, prepares variables for local use
	 *
	 */
	public function init()
	{
		if (!defined('IS_EDITOR') && isset($this->config['sessionAllow'])) {
			$this->checkCookieAllowed();
		}
		parent::init();
		$this->templateParser = new TemplateParser();
		$this->themeUrl = $this->siteUrl.'/site/themes/'.$this->config['theme_path'];
		$this->imanager->fieldMapper->init($this->pages->category->id, true);
		$this->input = $this->input();
		$this->urlSegments = $this->urlSegments();
		$this->firstSegment = $this->urlSegments->get(0);   // Kick it out
		$this->lastSegment = $this->urlSegments->getLast(); // same here
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
			$this->page = $this->pages->getPage(1);
			if (!$this->page || !$this->page->active) { 
				$this->throw404(); 
			}
		// other pages
		} else {
			$total = $this->urlSegments->total - 1;
			$this->page = $this->pages->getPageBySegment($this->urlSegments->segment, $total);
			if (!$this->page || !$this->page->active) {
				$this->throw404();
			}
			$curentUrl = $this->urlSegments->getUrl();
			$pageUrl = self::getPageUrl($this->page, $this->pages);
			if (strpos($curentUrl, $pageUrl) === false) {
				$this->throw404();
			}
		}
	}

	public function input() : Input
	{
		return ($this->input) ?? $this->imanager->input;
	}

	public function urlSegments() : UrlSegments
	{
		return ($this->urlSegments) ?? $this->input->urlSegments;
	}

	public function sectionCache() : SectionCache
	{
		return $this->imanager->sectionCache;
	}

	public function pages() : Pages
	{
		return ($this->pages) ?? new Pages();
	}

	public function users() : Users
	{
		return ($this->users) ?? new Users();
	}

	public function parsedown() : Parsedown
	{
		if (!isset($this->parsedown)) {
			$this->parsedown = $this->loadModule('parsedown', ['namespace' => __NAMESPACE__.'\Modules\\']);
		}
		return $this->parsedown;
	}

	/**
	 * NOTE: "segments" is used only for compatibility reasons.
	 */
	public function __get($arg)
	{
		switch ($arg) {
			case 'pages':
				return $this->pages();
				break;
			case 'segments':
			case 'urlSegments': 
				return $this->urlSegments();
				break;
			case 'users':
				return $this->users();
				break;
			case 'parsedown':
				return $this->parsedown();
				break;
		}
	}

	/**
	 * This Method can be used to generate URLs for pages 
	 * that have a hierarchical structure, like a nested 
	 * category or section system.
	 * 
	 * This method takes in two parameters, $item and $pages, 
	 * and returns a string that represents the URL of a page.
	 * 
	 * @param object $item
	 * @param null|object $pages
	 * @return string
	 * 
	 */
	public static function getPageUrl($item, $pages) :string
	{
		$return = '';
		if ($item->parent) {
			$parent = $pages->category->items[$item->parent];
			if ($parent) {
				$return .= self::getPageUrl($parent, $pages);
			}
		}
		$return .= ($item->id != 1) ? $item->slug.'/' : '';
		return  $return;
	}

	/**
	 * Site::___render() is a public hookable method that takes a string as an 
	 * argument and renders the appropriate page element. It has a return type 
	 * of ?string, meaning it may return a string or null. It renders a 
	 * navigation, content, or other elements depending on the argument given.
	 * 
	 * @param string $element
	 * @return null|string
	 */
	public function ___render(string $element) :?string
	{
		switch ($element) {
			case 'navigation':
				return $this->buildNavi();
				break;
			case 'content':
				return $this->buildContent();
				break;
			case 'messages':
				return $this->messages;
				break;
		}
		return null;
	}

	/**
	 * It takes the content of the page, renders it with given parameters, 
	 * and then parses it according to the allowHtmlOutput configuration.
	 */
	protected function buildContent() :string
	{
		//$name = ($this->lastSegment) ? "$this->lastSegment-content" : 'content';
		$imgPath = '';
		$field = $this->pages->category->getField('name=images');
		$pageId = isset($this->page->id) ? $this->page->id : null;

		if ($pageId && $field && $this->pages) {
			$imgPath = $this->pages->category->id.".$pageId.$field->id/";
		} else {
			$imgPath = ".tmp_{$this->input->post->timestamp_images}_{$this->category->id}.$field->id/";
		}

		$content = $this->templateParser->render($this->page->content, [
			'BASE_URL' => $this->siteUrl,
			'UPLOADS_URL' => $this->siteUrl.'/data/uploads/',
			'IMAGES_URL' => $this->siteUrl."/data/uploads/$imgPath"
		]);

		if ($this->config['allowHtmlOutput'] !== true) {
			$this->parsedown()->setSafeMode(true);
			$content = $this->parsedown()->text(htmlspecialchars_decode($content));
		} else {
			$content = $this->parsedown()->text(htmlspecialchars_decode($content));
		}
		
		return $content;
	}

	/**
	 * It takes all the pages, sorts them according to position, and then builds 
	 * a navigation tree with the getNaviChildren() method.
	 */
	protected function buildNavi() :string
	{
		$navi = '';
		$topl = $this->pages->category->getItems('parent=0');
		// TODO: check if topl exists if 0 pages created
		$topl = $this->pages->category->getItems('active=1', 0, 0, $topl);
		$topl = $this->pages->category->sort('position', 'asc', 0, 0, $topl);
		if (!$topl) return $navi;
		foreach ($topl as $item) {
			$all_pages = $this->pages->category->getItems("active=1");
			$all_pages = $this->pages->category->sort('position', 'asc', 0, 0, $all_pages);
			$navi .= $this->getNaviChildren($item, $all_pages, rtrim($this->siteUrl, '/') . '/');
		}
		return $navi;
	}

	/**
	 * TODO: This function should be moved off-site
	 */
	protected function getNaviChildren($item, & $items, $url, $children = '')
	{
		$childs = $this->pages->category->getItems("parent=$item->id", 0, 0, $items);
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

	/**
	 * Recursive Method that checks if a page is the parent of another page. 
	 * It takes a page and the current page being checked for parentage and 
	 * recursively checks the parent of the current page until it finds the 
	 * parent, or if the current page has no parent.
	 * 
	 * @param object $item
	 * @param object $current
	 */
	protected function parrentOf(& $item, $current) :bool
	{
		if (!$current->parent) {
			return false;
		}
		else if ($item->id == $current->parent) {
			return true;
		}
		//else {
		$parent = $this->pages->category->getItem((int)$current->parent);
		if ($parent) {
			return $this->parrentOf($item, $parent);
		}
		return false;
		//}
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
	 * Throws 404 page
	 */
	public function throw404()
	{
		header("HTTP/1.0 404 Not Found");
		include 'site/themes/'.$this->config['theme_path'].$this->config['404page'].'.php';
		die;
	}
	
	/**
	 * It checks if the value of the config variable sessionAllow is set to false, 
	 * if so session and cookies should not be used.
	 * 
	 */
	protected function checkCookieAllowed(): void
	{
		if ($this->config['sessionAllow'] instanceof \Closure) {
			$allowed = $this->config['sessionAllow']();
		} else { 
			$allowed = $this->config['sessionAllow']; 
		}

		if ($allowed) return;

		if (ini_get('session.use_cookies')) {
			! isset($_SESSION) OR session_destroy();

			$params = session_get_cookie_params();
			setcookie(session_name('IMSESSID'), '', time() - 42000, $params['path'],
				$params['domain'], $params['secure'], $params['httponly']
			);
		}
	}
}