<?php 

namespace Scriptor;

use Imanager\Util;

class Scriptor
{
    /**
     * Application version
     */
    const VERSION = '1.4.5';

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
        uasort($config['modules'], array('Scriptor\Module', 'order'));
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
     * 
     * @return void
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
