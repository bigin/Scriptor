<?php

/*
 * The _ext.php file is loaded before the template.php file.
 * This file includes all dynamic template components and 
 * calls some functions. 
 * 
 */
use Themes\Basic\BasicRouter;
use Scriptor\Core\Scriptor;

require_once __DIR__ . '/vendor/autoload.php';

// Set the BasicTheme as the active theme for the site
// BasicTheme extends the default Site class
Scriptor::setSite('Themes\Basic\BasicTheme', true);
$site = Scriptor::getSite();

// Create a router instance for handling site routing
$router = new BasicRouter($site);

/* 
 * SuperCache
 * 
 * Check if a cached version of the page exists. If so, retrieve 
 * and output it, then interrupt further script execution.
 * 
 * NOTE: User actions such as contact form submissions or subscriber 
 * form submissions are still processed if they have been executed. 
 * Tags and other user-specific data are also processed.
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