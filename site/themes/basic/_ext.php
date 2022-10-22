<?php

defined('IS_IM') or die('You cannot access this page directly');

/**
 * The _ext.php file is loaded before the template.php file.
 * This file includes all dynamic template components and 
 * calls some functions. 
 * 
 */

use Themes\Basic\BasicRouter;
use Scriptor\Core\Scriptor;

require_once __DIR__ . '/vendor/autoload.php';

/* BasicTheme extends default Site class */
Scriptor::setSite('BasicTheme', true, 'Themes\Basic');
$site = Scriptor::getSite();

/* Routing instance and pass $site */
$router = new BasicRouter($site);
$router->execute();

/** 
 * SuperCache 
 * Check if there's a cached version of that page.
 */
if ($output = $site->imanager->sectionCache->get(
    md5($_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']), 
    $site->getTCP('markup_cache_time'))
    ) {
    echo $output;
    exit;
}

/* 
UPGRADE SCRIPTOR V. 1.4.16 > 1.4.17

// Note: Your existing pages can get menu_title 
automatically, but the pages must be overwritten manually.

// If you have custom page fields, populate the array with 
// the field names. If not, leave it as it is:

$YOUR_FIELD_NAMES = [
	'slug',
	'parent',
	'pagetype',
	'menu_title', // New field, since Scriptor v. 1.4.17
	'content',
	'template',
	'images'
];

$pages = $imanager->getCategory('name=Pages');

// Create new field 'menu_title' of type 'text'

$newField = new \Imanager\Field($pages->id);

$newField->set('type', 'text')
	->set('name', 'menu_title')
	->set('label', 'Enter menu title')
	->save();

// Adjust the field positions

foreach($YOUR_FIELD_NAMES as $key => $name) {
	$field = $pages->getField("name=$name");
	$field->set('position', ++$key);
	$field->save();
}

echo count($YOUR_FIELD_NAMES).' fields have been updated.';

exit; */

//include __DIR__.'/_configs.php';
//include __DIR__.'/_functions.php';
//checkActions();