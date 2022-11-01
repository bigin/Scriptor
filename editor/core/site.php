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
		if (isset($this->config['sessionAllow'])) $this->checkCookieAllowed();
		parent::init();
		$this->templateParser = new TemplateParser();
		$this->themeUrl = $this->siteUrl.'/site/themes/'.$this->config['theme_path'];
		$this->input = $this->imanager->input;
		$this->urlSegments = $this->urlSegments();
		//$this->urlSegments = $this->input->urlSegments;
		//$this->pages = new Pages();
		//$this->users = $this->imanager->getCategory('name=Users');
		$this->firstSegment = $this->urlSegments->get(0);
		$this->lastSegment = $this->urlSegments->getLast();
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
			$this->page = $this->pages->getPage(1);
			if(!$this->page || !$this->page->active) { 
				$this->throw404(); 
			}
		// other pages
		} else {
			$total = $this->urlSegments->total - 1;
			$this->page = $this->pages->getPageBySegment($this->urlSegments->segment, $total);
			if(!$this->page || !$this->page->active) {
				$this->throw404();
			}
			$curentUrl = $this->urlSegments->getUrl();
			$pageUrl = self::getPageUrl($this->page, $this->pages);
			if(strpos($curentUrl, $pageUrl) === false) {
				$this->throw404();
			}
		}
	}

	public function urlSegments()
	{
		return ($this->urlSegments) ?? $this->input->urlSegments;
	}

	public function pages()
	{
		return ($this->pages) ?? new Pages();
	}

	public function users()
	{
		return ($this->users) ?? new Users();
	}

	/**
	 * NOTE: "segment" is used only for compatibility reasons.
	 */
	public function __get($arg)
	{
		if ($arg == 'pages') return $this->pages();
		elseif ($arg == 'segments' || $arg == 'urlSegments') return $this->urlSegments();
		elseif ($arg == 'users') return $this->users();
	}

	public static function getPageUrl($item, $pages)
	{
		$return = '';
		if($item->parent) {
			$parent = $pages->category->items[$item->parent];
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
			$field = $this->pages->category->getField('name=images');
			$pageId = isset($this->page->id) ? $this->page->id : null;
			if($pageId && $field && $this->pages) {
				$imgPath = $this->pages->category->id.".$pageId.$field->id/";
			} else {
				$imgPath = ".tmp_{$this->input->post->timestamp_images}_{$this->category->id}.$field->id/";
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

	protected function buildNavi()
	{
		$navi = '';
		$topl = $this->pages->category->getItems('parent=0');
		// Todo: check if topl exists if 0 pages created
		$topl = $this->pages->category->getItems('active=1', 0, 0, $topl);
		$topl = $this->pages->category->sort('position', 'asc', 0, 0, $topl);
		if (!$topl) return $navi;
		foreach($topl as $item) {
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


	protected function parrentOf(& $item, $current)
	{
		if(!$current->parent) {
			return false;
		}
		else if($item->id == $current->parent) {
			return true;
		}
		else {
			$parent = $this->pages->category->getItem((int)$current->parent);
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