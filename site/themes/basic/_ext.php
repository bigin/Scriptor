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
Scriptor::setSite('Themes\Basic\BasicTheme', true);
$site = Scriptor::getSite();

// Crete router instance
$router = new BasicRouter($site);

/* 
 * ~ SuperCache
 *  
 * Looks to determine if there is a cached version of the page, 
 * and if so, retrieves it, outputs it, and interrupts further 
 * script execution. 
 * 
 * NOTE: The user actions are still performed if they have been 
 * executed, e.g. contact form has been sent, subscriber form 
 * has been sent, tags, etc. 
 * 
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