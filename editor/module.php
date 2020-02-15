<?php
/**
 * Class Module
 *
 * Extendable module class
 *
 */
class Module
{
	const VERSION = '1.3.5';
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
	 * @var string $pageUrl - Current page URL
	 */
	public $pageUrl;

	/**
	 * @var string $pageContent - Current page content
	 */
	public $pageContent;

	/**
	 * @var object $input - Input object instance
	 */
	public $input;

	/**
	 * @var object $segments - Segments object instance
	 */
	protected $segments;

	/**
	 * @var array $pages - An array of Page objects
	 */
	protected $pages;

	/**
	 * @var object $page - The current page object instance
	 */
	protected $page;

	/**
	 * @var array $users - An array of Users objects
	 */
	protected $users;

	/**
	 * @var object $user - A current user object instance
	 */
	protected $user;

	public $csrf;

	/**
	 * @var array $msgs - An array of local error messages
	 */
	protected $msgs;

	/**
	 * @var array $i18n - An array of language sets
	 */
	public $i18n;

	/**
	 * @var string $messages - rendered messages (markup)
	 */
	public $messages;

	/**
	 * @var string $breadcrumbs - Breadcrumbs markup
	 */
	public $breadcrumbs;

	/**
	 * @var bool $auth - Module authorization needed?
	 */
	protected $auth = true;

	/**
	 * @var array - Header Resources
	 */
	protected $headerResources = [];

	/**
	 * Module constructor
	 *
	 * @param $config
	 */
	public function __construct($config)
	{
		$this->config = $config;
		$this->config['version'] = self::VERSION;
		$this->csrf = new CSRF($this->config);
		require "lang/{$this->config['editor_lang']}.php";
		$this->i18n = $i18n;
	}

	/**
	 * Init module class
	 *
	 * Prepares some variables for local use and executes actions.
	 *
	 */
	public function init()
	{
		if($this->config['dif_lang_packs']) {
			foreach($this->config['dif_lang_packs'] as $pack) {
				if(file_exists("../data/lang/$pack{$this->config['editor_lang']}.php")) {
					$customI18n = include "../data/lang/$pack{$this->config['editor_lang']}.php";
					$this->i18n = array_merge($this->i18n, $customI18n);
				}
			}
		}
		$this->imanager = imanager();
		$this->pageUrl = $this->imanager->config->getUrl();
		$this->input = $this->imanager->input;
		$this->segments = $this->input->urlSegments;
		$this->pages = $this->imanager->getCategory('name=Pages');
		$this->users = $this->imanager->getCategory('name=Users');
		if(!isset($_SESSION['msgs'])) {
			$_SESSION['msgs'] = [];
		}
		$this->msgs = & $_SESSION['msgs'];

		$this->execute();
		$this->renderMessages();
	}

	/**
	 * Editor mapper
	 *
	 * @param $editor
	 */
	protected function map($editor)
	{
		$this->editor = & $editor;
		foreach($this->editor as $key => $value) {
			$this->$key = & $this->editor->$key;
		}
	}

	/**
	 * Loads and returns editor module
	 * 
	 * If a module exists an instance of this module 
	 * will be returned, if not then null. 
	 *  
	 */
	protected function loadModule($moduleName)
	{
		$module = isset($this->config['modules'][$moduleName]) ? $this->config['modules'][$moduleName] : null;
		// Is module disabled module file exists?
		if(!$module || !$module['active'] || !file_exists(__DIR__ ."/$module[path].php")) { return null; }
		// include module
		include_once $module['path'] . '.php';
		return new $module['class']($this->config);
	}

	protected function execute(){}

	protected function checkAction(){}

	protected function addHeaderResource($context, $url)
	{
		if($context == 'js') $this->headerResources[$context][] =
			'<script src="'.$this->imanager->sanitizer->url($url).'"></script>'."\r\n";
		elseif($context == 'css') $this->headerResources[$context][] =
			'<link rel="stylesheet" href="'.$this->imanager->sanitizer->url($url).'">'."\r\n";
	}

	/**
	 * Delivers header resources (used in theme header).
	 * 
	 * @return null|string
	 */
	public function getHeaderResources($context)
	{
		$result = null;
		if(isset($this->headerResources[$context]) && is_array($this->headerResources[$context])) {
			foreach($this->headerResources[$context] as $resource) {
				$result .= $resource;
			}
		}

		return $result;
	}

	/**
	 * 
	 */
	protected function renderMessages()
	{
		if(!empty($this->msgs)) {
			$this->messages .= '<div class="message">';
			foreach($this->msgs as $msg) {
				if($msg['type'] == 'error') {
					$this->messages .= '<p class="error">'.$msg['value'].'</p>';
				} elseif($msg['type'] == 'success') {
					$this->messages .= '<p class="success">'.$msg['value'].'</p>';
				}
			}
			$this->messages .= '</div>';
			unset($_SESSION['msgs']);
			$_SESSION['msgs'] = null;
		}
	}
}