<?php 

namespace Scriptor;

class Scriptor
{
    const VERSION = '1.4.1';

    private static $config;

    private static $imanager;

    private static $msgs;

    private static $i18n;

    private static $csrf = null;

    private static $site = null;

    private static $editor = null;

    public static function build($config)
    {
        self::$config = $config;
        self::$imanager = \imanager();

        include dirname(__DIR__).'/lang/'.self::$config['lang'].'.php';
		self::$i18n = $i18n;
    }

    /**
     * 
     * @return mixed|null
     */
    public static function getProperty($property)
    {
        if(isset(self::${$property})) { return self::${$property}; }
        return null;
    }

    /**
     * 
     * @return object|null
     */
    public static function getEditor($init = true)
    {
        if(self::$editor === null) { 
            self::$editor = new Editor(); 
            if($init) self::$editor->init();
        }
        return self::$editor;
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
