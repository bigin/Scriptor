<?php defined('IS_IM') or die('You cannot access this page directly');

$config = array(
	/**
	 * Your admin language: de_DE, en_US
	 * See languages available under: editor/lang/
	 */
	'editor_lang' => 'en_US',

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
	 * Installed Scriptor modules
	 * Please add your custom modules to '/root/modules/ModuleName/ModuleName.php'
	 */
	'modules' => array(
		// Slug
		'pages' => array(
			'menu' => 'pages_menu', // i18n variable name or string
			'active' => true,
			'class' => null, // Build-in module
			'description' => "Scriptor's build-in module to display and edit pages"
		),
		// Slug
		'settings' => array(
			'menu' => 'settings_menu', // i18n variable name or string
			'active' => true,
			'class' => null, // Build-in module
			'description' => 'A build-in module for showing settings menu'
		)
	),

	/**
	 * Do not change this value
	 */
	'version' => '1.1'

);