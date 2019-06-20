<?php defined('IS_IM') or die('You cannot access this page directly');

$config = [
	/**
	 * Your admin language: de_DE, en_US
	 * See languages available under: editor/lang/
	 */
	'editor_lang' => 'en_US',

	/**
	 * Additional language packs, example:
	 *
	 * 'dif_lang_packs' => [
	 *     'your-languge-pack/',
	 *     'another-languge-pack/'
	 * ]
	 *
	 * ... this means that the language packages in the
	 *
	 * "data/lang/your-languge-pack/en_US.php"
	 *
	 * and "data/lang/another-languge-pack/en_US.php"
	 *
	 * directories are taken into use.
	 *
	 */
	'dif_lang_packs' => [],

	/**
	 * Enter your sitename
	 */
	'site_name' => 'Scriptor',

	/**
	 * Without the file extension.
	 * Extension by default is .php
	 */
	'404page' => '404',

	/**
	 * one hour: 3600
	 * month: 262974383
	 * etc
	 */
	'section_cache_time' => 262974383,

	/**
	 * Relative path to the admin folder
	 *
	 * Please note, if you change the folder name, you
	 * also need to adjust parts of your .htaccess file.
	 *
	 */
	'admin_path' => 'editor/',

	/**
	 * Installed Scriptor admin modules
	 *
	 * Add your custom modules to '/root/editor/modules/ModuleName/ModuleName.php'
	 *
	 * The structure is as follows:
	 *
	 * 'pages' => [                // URL Segment that resolves to the module
	 *     'menu' => 'pages_menu', // i18n variable name or string
	 *     'active' => true,       // Enables or disables module
	 *     'path' => 'pages/pages',// Path and file name without extension like '.php'
	 *     'class' => 'Pages',     // The class to be called
	 *     'display_type' => [     // Module display options
	 *         'sidebar'           // Show in 'sidebar' and/or 'profile' menu
	 *     ],
	 *     'description' => ""     // Module description
	 * ]
	 */
	'modules' => [
		'pages' => [
			'menu' => 'pages_menu',
			'active' => true,
			'path' => 'pages/pages',
			'class' => 'Pages',
			'display_type' => [
				'sidebar'
			],
			'description' => "Scriptor's build-in module to display and edit pages"
		],
		'settings' => [
			'menu' => 'settings_menu',
			'active' => true,
			'class' => null, // Build in module
			'display_type' => [
				'sidebar'
			],
			'description' => 'A build-in module for showing settings menu'
		],
		'profile' => [
			'menu' => 'profile_menu',
			'active' => true,
			'path' => 'profile/profile',
			'class' => 'Profile',
			'display_type' => [
				'profile'
			],
			'icon' => 'fas fa-user-circle',
			'description' => 'A Profile edit module for showing in the header menu'
		],
		'logout' => [
			'menu' => 'logout_menu',
			'active' => true,
			'path' => '',
			'class' => null, // Build in module
			'display_type' => [
				'profile'
			],
			'icon' => 'fas fa-sign-out-alt',
			'description' => 'A build-in module for showing in the header menu'
		]
	]
];