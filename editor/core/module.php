<?php

namespace Scriptor;

use Imanager\Util;

/**
 * Class Module
 *
 * Extendable module class
 *
 */
class Module implements ModuleInterface
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
	 * @var object $sanitizer - ItemManager's Sanitizer instanze
	 */
	public $sanitizer;

	/**
	 * @var object $segments - Segments object instance
	 */
	protected $segments;

	/**
	 * @var array $msgs - An array of local error messages
	 */
	protected $msgs;

	/**
	 * @var array $i18n - An array of language sets
	 */
	public $i18n = [];

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


	public function execute(){}

    public function checkAction(){}

	/**
	 * Init module class
	 *
	 * Prepares some variables for local use
	 *
	 */
	public function init()
	{
		// var_dump($this->scriptor);
		$this->config = Scriptor::getProperty('config');
		$this->imanager = Scriptor::getProperty('imanager');
		$this->i18n = Scriptor::getProperty('i18n');
		$this->msgs = Scriptor::getProperty('msgs');
		$this->siteUrl = $this->imanager->config->getUrl();
		$this->input = $this->imanager->input;
		$this->sanitizer = $this->imanager->sanitizer;
		$this->segments = $this->input->urlSegments;

		if(!isset($_SESSION['msgs'])) {
            $_SESSION['msgs'] = [];
        }
        $this->msgs = & $_SESSION['msgs'];
	}

	/**
	 * Loads and returns editor module
	 * 
	 * If a module exists an instance of this module 
	 * will be returned, if not then null. 
	 * 
	 * @var string $moduleName - Module name to load
	 * @var array $options 
	 *   - namespace - Namespace constant|string with a a trailing 
	 *                 slash (Default: current Scriptor's namespace)
	 * 
	 *   - autoinit  - bool Should this module initialize automatically?
	 *                 (Default: true)
	 *  
	 * @return object|null - Module instance or null
	 */
	public function loadModule($moduleName, $options = [])
	{
		$module = isset($this->config['modules'][$moduleName]) ? $this->config['modules'][$moduleName] : null;
		// Is module disabled module file exists?
		if(!$module || !$module['active']) { return false; } 

		$defaults = [
			'namespace' => __NAMESPACE__.'\\',
			'autoinit' => isset($module['autoinit']) ? $module['autoinit'] : true
		];
		$config = array_merge($defaults, $options);

		// Module paths (Core & Site)
		$coreModulePath = dirname(__DIR__)."/$module[path].php";
		$siteModulePath = IM_ROOTPATH."site/$module[path].php";
		// include module
		if(file_exists($siteModulePath)) {
			include_once $siteModulePath;
		}
		elseif(file_exists($coreModulePath)) {
			include_once $coreModulePath;
		}
		else { return null; }

		$class = $config['namespace'].$module['class'];
		if($config['autoinit']) {
			$currentModule = new $class();
			if($currentModule) $currentModule->init();
			return $currentModule;
		}
		return new $class();
	}

	/**
	 * 
	 */
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
