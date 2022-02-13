<?php

namespace Scriptor;

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
	 * @var null|object - Hook event
	 */
	protected $event = null;


	public function execute(){}

	/**
	 * Check if this is really needed, if not remove it completely.
	 * NOTE: that this method is also commented out in ModuleInterface.
	 */
	//public function checkAction(){}

	/**
	 * Init module class
	 *
	 * Prepares some variables for local use
	 *
	 */
	public function init()
	{
		$this->config = Scriptor::getProperty('config');
		$this->imanager = Scriptor::getProperty('imanager');
		$this->i18n = Scriptor::getProperty('i18n');
		$this->msgs = Scriptor::getProperty('msgs');
		$this->siteUrl = $this->imanager->config->getUrl();
		$this->input = $this->imanager->input;
		$this->sanitizer = $this->imanager->sanitizer;
		$this->segments = $this->input->urlSegments;
		$this->event = $this->createEvent();

		if(!isset($_SESSION['msgs'])) $_SESSION['msgs'] = [];
		$this->msgs = & $_SESSION['msgs'];
	}
	
	public function createEvent()
	{
		$event = new \stdClass;
		$event->return = null;
		$event->replace = false;
		return $event;
	}

	/**
	 * Loads and returns editor module
	 * 
	 * If a module exists an instance of this module 
	 * will be returned, if not then null. 
	 * 
	 * @param string $moduleName - Module name to load
	 * @param array $options 
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
		if(!$module || !$module['active']) return false;

		$defaults = [
			'namespace' => __NAMESPACE__.'\\',
			'autoinit' => isset($module['autoinit']) ? $module['autoinit'] : true
		];
		$config = array_merge($defaults, $options);

		// Module paths (Core & Site)
		$coreModulePath = dirname(__DIR__)."/$module[path].php";
		$siteModulePath = IM_ROOTPATH."site/$module[path].php";
		// include module
		if(file_exists($siteModulePath)) include_once $siteModulePath;
		elseif(file_exists($coreModulePath)) include_once $coreModulePath;
		else return null;

		$class = $config['namespace'].$module['class'];
		if($config['autoinit']) {
			$currentModule = new $class();
			if($currentModule) $currentModule->init();
			return $currentModule;
		}
		return new $class();
	}
	
	/**
	 * Provides the gateway for calling hooks in Scriptor
	 * 
	 * When a non-existant method is called, this checks to 
	 * see if any hooks have been defined and sends the call 
	 * to them. 
	 * 
	 * @param string - The method to be called
	 * @param array - Arguments
	 */
	public function __call($method, array $args = [])
	{
		// Call hookable method
		if(method_exists($this, "___$method")) {

			$this->event->args = & $args;

			// Execute before hook
			if(Scriptor::execHook($this, $method, $this->event->args, 'before')) {
				if($this->event->return !== null || $this->event->replace) {
					$return = $this->event->return;
					$this->event = $this->createEvent();
					return $return;
				}
			}
			// Hooked function call
			$return = call_user_func_array(array($this, "___$method"), $this->event->args);
		
			// Execute after hook
			if(Scriptor::execHook($this, $method, $return, 'after')) {
				if($this->event->return !== null) {
					$return = $this->event->return;
					$this->event = $this->createEvent();
					return $return;
				}
			}
			
			return $return;
		}
		else {
			trigger_error('The called method '.$method.' was not found', E_USER_ERROR);
		}
	}

	/**
	 * Adds resources to the resources buffer.
	 * 
	 * @param string $context - Any resource 'script' or 'link' etc.
	 * @param array $data - Attribut name and value e.g. 'src' => '/your/resource-url/script.js'
	 * @param string $area - Resource area e.g. 'header', 'boddy'. Default is 'header'.
	 */
	public function addResource(string $context, array $data, string $area = 'header')
	{
		$attrs = '';
		$san = $this->imanager->sanitizer;

		foreach($data as $arrt => $value) {
			if(is_int($arrt)) $attrs .= $san->text($value).' ';
			else $attrs .= $san->text($arrt).'="'.$san->text($value).'" ';
		}

		$localArea = $san->text($area);
		$resources = Scriptor::getProperty($localArea.'Resources');
		$resources[$context][] = ($context != 'link') ? "<$context $attrs></$context>\r\n" : "<$context $attrs>\r\n";
		Scriptor::setProperty($localArea.'Resources', $resources);
	}

	/**
	 * Adds heder resources to the HeaderResources Buffer.
	 * 
	 * @deprecated - Use Scriptor\Module::addResource() instead.
	 * 
	 * @param string - Can currently only be 'js' or 'css'
	 * @param $url - resource URL
	 */
	public function addHeaderResource($context, $url)
	{
		$headerResources = Scriptor::getProperty('headerResources');
		if($context == 'js') $headerResources[$context][] =
			'<script src="'.$this->imanager->sanitizer->url($url).'"></script>'."\r\n";
		elseif($context == 'css') $headerResources[$context][] =
			'<link rel="stylesheet" href="'.$this->imanager->sanitizer->url($url).'">'."\r\n";
		Scriptor::setProperty('headerResources', $headerResources);
	}

	/**
	 * Returns resources e.g. used in editor theme 
	 * 
	 * @param string $context
	 * @param string $area
	 * 
	 * @return null|string
	 */
	public function getResources(string $context, string $area = 'header')
	{
		// Context converter because of backward compatibility
		$oldContexts = [
			'script' => 'js',
			'link' => 'css'
		];
		$san = $this->imanager->sanitizer;
		$resources = Scriptor::getProperty($san->text($area).'Resources');

		$result = null;
		if(isset($resources[$context]) && is_array($resources[$context])) {
			foreach($resources[$context] as $resource) $result .= $resource;
		}

		// just for backward compatibility
		elseif(isset($resources[$oldContexts[$context]]) && 
			is_array($resources[$oldContexts[$context]])) {
				foreach($resources[$oldContexts[$context]] as $resource) $result .= $resource;
		}

		return $result;
	}

	/**
	 * Returns header resources e.g. used in theme header 
	 * 
	 * @deprecated - Use Scriptor\Module::getResource() instead.
	 * 
	 * @return null|string
	 */
	public function getHeaderResources($context)
	{
		$headerResources = Scriptor::getProperty('headerResources');
		$result = null;
		if(isset($headerResources[$context]) && is_array($headerResources[$context])) {
			foreach($headerResources[$context] as $resource) $result .= $resource;
		}

		return $result;
	}

	/**
	 * Generates default messages markup and 
	 * flushes the message buffer.
	 * 
	 * @return string
	 */
	public function renderMessages()
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
	
	public function getProperty($name)
	{
		return isset($this->$name) ? $this->$name : null;
	}

	public function setProperty($name, $value)
	{
		if(property_exists($this, $name)) $this->$name = $value;
	}
}
