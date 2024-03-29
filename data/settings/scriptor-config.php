<?php defined('IS_IM') or die('You cannot access this page directly');
/**
 * Site configuration file.
 * NOTE: The variables in this file are overwritten during 
 * the update. Instead, use custom.scriptor-config.php for 
 * your custom configuration options.
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
	 * Enter your site name
	 * 
	 * @var string
	 */
	'site_name' => 'Scriptor',

	/**
	 * Filename without the file extension.
	 * The default extension is .php
	 * 
	 * @var string
	 */
	'404page' => '404',

	/**
	 * Cache time in seconds.
	 * Example values: one hour - 3600, one month - 262974383, etc.
	 * 
	 * @var integer
	 */
	'markup_cache_time' => 262974383,

	/**
	 * Relative path to the theme folder.
	 * 
	 * @var string
	 */
	'theme_path' => 'basic/',

	/**
	 * Relative path to the admin folder.
	 *
	 * Please note that if you change the folder name, you also need to update the paths
	 * in the .htaccess file in the root directory accordingly.
	 * 
	 * @var string
	 */
	'admin_path' => 'editor/',

	/**
	 * Editor template. 
	 * Specify the relative path and file name without the file extension (default extension is .php).
	 * If no path is given, it will be searched under:
	 *    /~admin_path~/~editor_template~.php
	 * 
	 * @var string
	 */
	'editor_template' => 'theme/template',

	/**
	 * Enables CSRF (cross-site request forgery) protection on all Scriptor forms,
	 * recommended for improved security.
	 *
	 * @var bool
	 */
	'protectCSRF' => true,
	
	/**
	 * Are sessions allowed? Usually boolean 'true', unless 
	 * you provide a callable function that returns a boolean value.
	 * 
	 * @var bool|callable
	 */
	'sessionAllow' => true,

	/**
	 * Maximum number of CSRF tokens allowed per user.
	 * Corresponds to the number of tabs opened at the same time.
	 * Useful if you want to work with the application in multiple browser tabs.
	 *
	 * @var integer
	 */
	'maxNumTokens' => 10,

	/**
	 * Number of failed login attempts before lockout.
	 * 
	 * @var integer
	 */
	'maxFailedAccessAttempts' => 5,

	/**
	 * Duration of access lockout in minutes.
	 * 
	 * @var integer
	 */
	'accessLockoutDuration' => 5,

	/**
	 * Minimum password length.
	 * 
	 * @var integer
	 */
	'minPasswordLength' => 6,

	/**
	 * Maximum number of configuration files to be saved as backup.
	 * 
	 * @var integer
	 */
	'maxConfigBackupFiles' => 5,

	/**
	 * Enable HTML tags in page content output.
	 * 
	 * @var bool
	 */
	'allowHtmlOutput' => false,

	/**
	 * Array with reserved slugs.
	 * 
	 * @var array 
	 */
	'reservedSlugs' => [
		'index',
		'editor'
	],

	/**
	 * Installed Scriptor admin modules.
	 *
	 * Add your custom modules to '/site/modules/<ModuleName>/<ModuleName.php>'.
	 *
	 * Parameters description:
	 *
	 * // Note: the key 'pages' is the URL segment in admin ...
	 * 'pages' => [                                       // Module name / URL segment that resolves to the module (array)
	 *     'menu' => 'your_menu',                         // i18n variable name or string (string)
	 *     'position' => 4,                               // Module load order position (integer) (default 0)
	 *     'active' => true,                              // Enables or disables module (bool)
	 *     'auth' => true,                                // Enables or disables module authorization
	 *     'autoinit' => true,                            // Enables or disables auto initialization (bool) 
	 *     'path' => 'modules/your-dir/file',             // Path and file name without extension like '.php' (string)
	 *     'class' => 'Namespace\Class',                  // The full namespace and class to be called (string)
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
			'position' => 0,
			'active' => true,
			'auth' => true,
			'autoinit' => true,
			'path' =>  'modules/pages/pages',
			'class' => 'Scriptor\Core\Modules\Pages',
			'display_type' => [
				'sidebar'
			],
			'icon' => 'gg-list',
			'description' => "A Scriptor's build-in module to display and edit pages"
		],
		'profile' => [
			'menu' => 'profile_menu',
			'position' => 1,
			'active' => true,
			'auth' => true,
			'autoinit' => true,
			'path' => 'modules/profile/profile',
			'class' => 'Scriptor\Core\Modules\Profile',
			'display_type' => [
				'profile'
			],
			'icon' => 'gg-profile',
			'description' => 'A default module for editing profiles, shown in the header menu'
		],
		'auth' => [
			'menu' => 'logout_menu',
			'position' => 1,
			'active' => true,
			'auth' => false, // Authorization will performed by module itself
			'autoinit' => true,
			'path' => 'modules/auth/auth',
			'class' => 'Scriptor\Core\Modules\Auth',
			'display_type' => [
				'profile'
			],
			'icon' => 'gg-log-in',
			'description' => 'A default module for login and logout actions'
		],
		'settings' => [
			'menu' => 'settings_menu',
			'position' => 2,
			'active' => true,
			'auth' => true,
			'autoinit' => true,
			'path' => 'modules/settings/settings',
			'class' => 'Scriptor\Core\Modules\Settings',
			'display_type' => [
				'sidebar'
			],
			'icon' => 'gg-components',
			'description' => 'A default module for displaying the settings menu'
		],
		'install' => [
			'menu' => 'install_menu',
			'position' => 3,
			'active' => true,
			'auth' => true,
			'autoinit' => true,
			'path' => 'modules/install/install',
			'class' => 'Scriptor\Core\Modules\Install',
			'display_type' => [
				'sidebar'
			],
			'icon' => 'gg-plug',
			'description' => 'A default module for managing module installations'
		],
		'parsedown' => [
			'menu' => '',
			'position' => 4,
			'active' => true,
			'auth' => false,
			'autoinit' => true,
			'path' => 'modules/parsedown/Parsedown',
			'class' => 'Scriptor\Core\Modules\Parsedown',
			'display_type' => [
			],
			'description' => 'A default module for parsing markdown'
		]
	],
	
	/**
	 * Installed Scriptor hooks.
	 * 
	 * Also note the correct syntax, more in: 
	 *  /data/settings/custom.scriptor-config.php.
	 * 
	 * @var array
	 */
	'hooks' => [],

];