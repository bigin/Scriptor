<?php defined('IS_IM') or die('You cannot access this page directly');
/**
 * Static site configuration file.
 * NOTE: The variables in this file are overwritten during 
 * the update. Instead, use custom.scriptor-config.php for 
 * your specific configuration options.
 * 
 * @var array
 */
$config = [
	/**
	 * Current language: de_DE, en_US
	 * See languages available under: editor/lang/
	 * 
	 * @var string
	 */
	'lang' => 'en_US',

	/**
	 * Enter your sitename
	 * 
	 * @var string
	 */
	'site_name' => 'Scriptor',

	/**
	 * Without the file extension.
	 * Extension by default is .php
	 * 
	 * @var string
	 */
	'404page' => '404',

	/**
	 * one hour: 3600
	 * month: 262974383
	 * etc
	 * 
	 * @var integer
	 */
	'markup_cache_time' => 262974383,

	/**
	 * Relative path to the admin folder.
	 *
	 * Please note, if you change the folder name, you have to change the paths
	 * in the .htaccess file, in the root directory accordingly.
	 * 
	 * @var string
	 */
	'admin_path' => 'editor/',

	/**
	 * Editor template. 
	 * Relative path + file name without the file extension (default extension is .php).
	 * If no path is given, it will be searched under:
	 *    /~admin_path~/~editor_template~.php
	 * 
	 * @var string
	 */
	'editor_template' => 'theme/template',

	/**
	 * Enables CSRF (cross site request forgery) protection on all Scriptor forms,
	 * recommended for improved security.
	 *
	 * @var bool
	 */
	'protectCSRF' => true,

	/**
	 * Maximum number of CSRF tokens allowed per user.
	 * Corresponds to the number of tabs opened at the same time.
	 * It is useful if you want to work with the application in multiple browser tabs.
	 *
	 * @var integer
	 */
	'maxNumTokens' => 5,

	/**
	 * Number of failed login attempts before lockout.
	 * 
	 * @var integer
	 */
	'maxFailedAccessAttempts' => 5,

	/**
	 * Access lockout duration in minutes
	 * 
	 * @var integer
	 */
	'accessLockoutDuration' => 5,

	/**
	 * Enable HTML tags in page content output
	 * 
	 * @var bool
	 */
	'allowHtmlOutput' => false,

	/**
	 * Installed Scriptor admin modules
	 *
	 * Add your custom modules to '/root/editor/modules/ModuleName/ModuleName.php'
	 *
	 * The structure is as follows:
	 *
	 * // Key should be the same as the slug
	 * 'pages' => [                                       // URL Segment that resolves to the module (array)
	 *     'menu' => 'your_menu',                         // i18n variable name or string (string)
	 *     'active' => true,                              // Enables or disables module (bool)
	 *     'auth' => true,                                // Enables or disables module authorization
	 *     'path' => IM_DATAPATH.'modules/your-dir/file', // Path and file name without extension like '.php' (string)
	 *     'class' => 'Pages',                            // The class to be called (string)
	 *     'display_type' => [                            // Module display options (array)
	 *         'sidebar'                                  // Show in 'sidebar' and/or 'profile' menu (string)
	 *     ],
	 *     'description' => ''                            // Module description (string)
	 * ]
	 * 
	 * @var array
	 */
	'modules' => [
		'pages' => [
			'menu' => 'pages_menu',
			'active' => true,
			'auth' => true,
			'path' =>  'modules/pages/pages',
			'class' => 'Pages',
			'display_type' => [
				'sidebar'
			],
			'description' => "Scriptor's build-in module to display and edit pages"
		],
		'profile' => [
			'menu' => 'profile_menu',
			'active' => true,
			'auth' => true,
			'path' => 'modules/profile/profile',
			'class' => 'Profile',
			'display_type' => [
				'profile'
			],
			'icon' => 'fas fa-user-circle',
			'description' => 'A Profile edit module for showing in the header menu'
		],
		'auth' => [
			'menu' => 'logout_menu',
			'active' => true,
			'auth' => false, // Authorization will performed by module  itself
			'path' => 'modules/auth/auth',
			'class' => 'Auth',
			'display_type' => [
				'profile'
			],
			'icon' => 'fas fa-sign-out-alt',
			'description' => 'Login, logout actions module'
		],
		'dashboard' => [
			'menu' => '',
			'active' => true,
			'auth' => true,
			'path' => 'modules/dashboard/dashboard',
			'class' => 'Dashboard',
			'display_type' => [
			],
			'icon' => '',
			'description' => "A default Scriptor's dashboard module"
		],
		'settings' => [
			'menu' => 'settings_menu',
			'active' => true,
			'auth' => true,
			'path' => 'modules/settings/settings',
			'class' => 'Settings',
			'display_type' => [
				'sidebar'
			],
			'description' => 'A default module for showing settings menu'
		],
		'parsedown' => [
			'menu' => '',
			'active' => true,
			'auth' => false,
			'path' => 'modules/parsedown/Parsedown',
			'class' => 'Parsedown',
			'display_type' => [
			],
			'description' => 'A default module for parsing markdown'
		],
	]
];