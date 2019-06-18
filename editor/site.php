<?php
class Site extends Module
{
	/**
	 * @var object $imanager - Instance of IManager
	 */
	protected $imanager;

	/**
	 * @var array $config - Scriptor config
	 */
	public $config;

	/**
	 * @var sting $pageTitle - Meta page title
	 */
	public $pageTitle;

	/**
	 * @var string $themeUrl - Theme URL
	 */
	public $themeUrl;

	/**
	 * @var object $input - Input object instance
	 */
	protected $input;

	/**
	 * @var object $segments - Segments object instance
	 */
	protected $segments;

	/**
	 * @var string $firstSegment - Buffered first url segment
	 */
	protected $firstSegment;

	/**
	 * @var string $lastSegment - Buffered last url segment
	 */
	protected $lastSegment;

	/**
	 * @var object $pages - An object of category Pages
	 */
	public $pages;

	/**
	 * @var object $users - An object of category Users
	 */
	public $users;

	/**
	 * @var array $msgs - An array of local error messages
	 */
	protected $msgs;

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
	 * Editor constructor.
	 *
	 * @param $config
	 */
	public function __construct($config) {
		$this->config = $config;
		$this->config['version'] = parent::VERSION;
	}

	/**
	 * Init editor class
	 * Prepares some variables for local use and executes actions
	 *
	 */
	public function init()
	{
		$this->imanager = imanager();
		$this->siteUrl = $this->imanager->config->getUrl();
		$this->themeUrl = $this->siteUrl.'site/theme/';
		$this->input = $this->imanager->input;
		$this->segments = $this->input->urlSegments;
		$this->pages = $this->imanager->getCategory('name=Pages');
		$this->users = $this->imanager->getCategory('name=Users');
		$this->firstSegment = $this->segments->get(0);
		$this->lastSegment = $this->segments->getLast();
		if(!isset($_SESSION['msgs'])) {
			$_SESSION['msgs'] = array();
		}
		$this->msgs = & $_SESSION['msgs'];

		$this->execute();
		//$this->renderMessages();
	}

	protected function execute()
	{
		if(!$this->lastSegment) {
			// Home
			$this->page = $this->pages->getItem(1);
			if(!$this->page || !$this->page->active) { return $this->throw404(); }
			$this->checkAction();
			$this->title = $this->page->name;
			foreach($this->page as $key => $param) {
				$this->$key = $param;
			}
		} else {
			// Other pages
			$this->page = $this->pages->getItem('slug='.$this->imanager->sanitizer->pageName($this->lastSegment));
			if(!$this->page || !$this->page->active) { return $this->throw404(); }
			$this->title = $this->page->name;
			foreach($this->page as $key => $param) {
				$this->$key = $param;
			}
		}
	}

	public function render($element)
	{
		if($element == 'navigation') {
			$name = ($this->lastSegment) ? "$this->lastSegment-$element" : $element;
			if(!$navi = $this->imanager->sectionCache->get($name, $this->config['section_cache_time'])) {
				$navi = $this->buildNavi();
				if($navi) {
					$this->imanager->sectionCache->save($navi);
				}
			}
			return $navi;
		}
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
			$navi .= $this->getChildren($item, $all_pages, $this->siteUrl);
		}
		return $navi;
	}

	protected function getChildren($item, & $items, $url, $children = '')
	{
		$childs = $this->pages->getItems("parent=$item->id", 0, $items);

		if($childs) {
			$prefix = '<li' . $this->getClass($item) . '><a href="' .
				$url . (($item->id != 1 && !$item->parent) ? $item->slug . '/' : '') . '">' . $item->name . '</a>';
			$buff = '';
			foreach($childs as $curitem) {
				$buff .= $this->getChildren($curitem, $items,
					$url . (($item->id != 1 && !$item->parent) ? $item->slug . '/' : '') . $curitem->slug . '/', $children);
			}
			$children = $prefix . '<ul>' . $buff . '</ul></li>';
		} else {
			$children = '<li'.$this->getClass($item).'><a href="'.
				$url.(($item->id != 1 && !$item->parent) ? $item->slug.'/' : '').'">'.$item->name.'</a></li>';
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