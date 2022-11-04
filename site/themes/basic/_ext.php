<?php

defined('IS_IM') or die('You cannot access this page directly');

/*
 * The _ext.php file is loaded before the template.php file.
 * This file includes all dynamic template components and 
 * calls some functions. 
 * 
 */

use Themes\Basic\BasicRouter;
use Scriptor\Core\Scriptor;

require_once __DIR__ . '/vendor/autoload.php';

// BasicTheme extends default Site class 
Scriptor::setSite('BasicTheme', true, 'Themes\Basic');
$site = Scriptor::getSite();

// Crete router instance
$router = new BasicRouter($site);

/* 
 * SuperCache 
 * Check if there's a cached version of that page.
 */
if ($output = $site->imanager->sectionCache->get(
    md5($_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']), 
    $site->getTCP('markup_cache_time'))
    ) {
	$router->actions();
    echo $output;
    exit;
}




// Execute
$router->execute();

/* 
UPDATE SCRIPTOR V. 1.4.16 > 1.4.17

// New pages will get a new "menu_title" field after the update, but the pages 
// that are already created will have to be overwritten manually.
//
// If you have other custom page fields, fill in the array with their field names:

$YOUR_FIELD_NAMES = [
	'slug',
	'parent',
	'pagetype',
	'menu_title',
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