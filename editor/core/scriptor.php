<?php 

namespace Scriptor;

use Imanager\Util;

class Scriptor
{
	/**
	 * Application version
	 */
	const VERSION = '1.5.0';

	/**
	 * @var array $config - Configuration parameter
	 */
	private static $config = [];

	/**
	 * @var object $imanager - ItemManager object instance
	 */
	private static $imanager;

	/**
	 * @var array $i18n - Language variables
	 */
	private static $i18n = [];

	/**
	 * @var float $startTime - Unix timestamp with microseconds
	 */
	private static $startTime;

	/**
	 * @var object $csrf - CSRF class instance
	 */
	private static $csrf = null;

	/**
	 * @var object $site - Site class instance
	 */
	private static $site = null;

	/**
	 * @var object $editor - Editor object instance
	 */
	private static $editor = null;


	private static $hooked = [];

	/**
	 * @var array $headerResources - An array of header resources
	 */
	private static $headerResources = [];

	/**
	 * Build Scriptor class
	 */
	public static function build($config)
	{
		self::$startTime = microtime(true);
		self::load(__DIR__.'/helper.php');
		uasort($config['modules'], array('Scriptor\Helper', 'order'));
		self::$config = $config;
		self::$imanager = \imanager();
		include dirname(__DIR__).'/lang/'.self::$config['lang'].'.php';
		self::$i18n = $i18n;
	}

	/**
	 * Loads a file 
	 * 
	 * @var string $path
	 */
	public static function load($path)
	{
		return require_once $path;
	}

	/**
	 * 
	 * @return mixed|null
	 */
	public static function & getProperty($property)
	{
		$return = null;
		if(property_exists('Scriptor\Scriptor', $property)) { 
			 $return = self::${$property}; 
			 return $return;
		}
		return $return;
	}

	public static function setProperty($property, $value)
	{
		if(property_exists('Scriptor\Scriptor', $property)) { self::${$property} = $value; }
	}

	/**
	 * Is used to setup custom Editor module.
	 * 
	 * This method can be used to replace the default 
	 * Editor module with custom ones.
	 * 
	 * @var string Editor module name
	 * @var bool - Flag; Used to force initialization
	 */
	public static function setEditor($editor, $init = false)
	{
		self::$editor = new $editor(); 
		if($init) self::$editor->init();
	}

	/**
	 * Retrieve an instance of the Editor class and 
	 * loads language files of the modules into memory.
	 * 
	 * @return object|null
	 */
	public static function getEditor($init = true)
	{
		if(self::$editor === null) { 
			foreach(self::$config['modules'] as $module) {
				$modLang = IM_ROOTPATH.'site/'.dirname($module['path']).'/lang/'.
					self::$config['lang'].'.php';
				if(file_exists($modLang) && $module['active']) {
					self::$i18n = array_merge(self::$i18n, include $modLang);
				}
			}
			self::$editor = new Editor(); 
			if($init) self::$editor->init();
		}
		return self::$editor;
	}

	/**
	 * Executes Hook
	 *
	 * @since 1.4.6 
	 *
	 * @param $object 
	 * @param string $method - Method or property to run hooks for.
	 * @param string $args - Method arguments.
	 * @param string $type - May be any one of the following: 
	 *  - '': for hooked methods (default)
	 *  - before: only run before hooks and do nothing else
	 *  - after:  only run after hooks and do nothing else
	 */
	public static function execHook($object, $method = '', $args = [], $type = '') 
	{
		$result = null;
		$return = false;
		// Check method hooked?
		$method = !empty($method) ? ucfirst($method) : '';
		$hookName = Helper::rawClassName(get_class($object)).(!empty($method) ? '::'.$type.ucfirst($method) : '');
		// There no hook to execute, just return
		if(!isset(self::$config['hooks'][$hookName])) return;
		// Get installed hooks
		$hooks = self::$config['hooks'][$hookName];
		// Invalid hook specification
		if(!is_array($hooks) || empty($hooks)) {
			trigger_error('Invalid hook formatting specification', E_USER_WARNING);
			return false;
		}

		$event = $object->getProperty('event');
		$event->object = $object;
		$event->method = $method;
		if($type !== 'after') { 
			$event->args = $args;
			$event->return = null;
		} else { 
			$event->return = $args; 
		}
		$event->type = $type;
		$event->replace = false;

		foreach($hooks as $hook) {
			// If hooked class instance already exists, just call the method
			$class = '';
			if(isset(self::$hooked[$hookName])) {
				$class = Helper::rawClassName(get_class(self::$hooked[$hookName]));
			}

			if(isset(self::$hooked[$hookName]) && $class == $hook['module']) {
				$result = self::$hooked[$hookName]->{$hook['method']}($event);
				$return = true;
			} else {
				// Anonymous class / closure ...
				if(!isset($hook['module']) || !$hook['module']) {                    
					// closure
					if(Helper::isCallable($hook['method'])) {
						$result = $hook['method']($event);
						$return = true;
					} // class
					elseif((new \ReflectionClass($hook['method']))->isAnonymous()) {
						$object->extension = $hook['method'];
						$return = true;
					}
				}
				// Module method 
				else {
					self::$hooked[$hookName] = $object->loadModule($hook['module']);
					if(!self::$hooked[$hookName]) {
						trigger_error("Module $hook[module] not installed or is disabled", E_USER_WARNING);
						$return = false;
					}
					$result = self::$hooked[$hookName]->{$hook['method']}($event);
					$return = true;
				}
			}
		}
		// Currently we use no return apart from $event->return
		//return $result;
		return $return;
	}

	/**
	 * 
	 * @return void
	 */
	public static function setSite($site, $init = false)
	{
		self::$site = new $site(); 
		if($init) self::$site->init();
	}

	/**
	 * 
	 * @return object|null
	 */
	public static function getSite($init = true)
	{
		if(self::$site === null) { 
			self::$site = new Site(); 
			if($init) self::$site->init();
		}
		return self::$site;
	}


	/**
	 * 
	 * @return object|null
	 */
	public static function getCSRF()
	{
		if(self::$csrf === null) { self::$csrf = new CSRF(); }
		return self::$csrf;
	}

	public static function logRunTime($designation = 'Operation time:')
	{
		$after = microtime(true);
		Util::dataLog("$designation ".($after - self::$startTime).' sec');
	}

   /**
	* clone
	*
	* Also prevent copying the instance from outside.
	*/
   protected function __clone() {}

   /**
	* constructor
	*
	* Prevent external instantiation.
	*/
   protected function __construct() {}
}
