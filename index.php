<?php
/**
 * Scriptor skeleton
 */
include __DIR__.'/imanager.php';
include __DIR__.'/data/settings/scriptor-config.php';
include __DIR__."/$config[admin_path]module.php";
include __DIR__."/$config[admin_path]site.php";
include __DIR__."/$config[admin_path]csrf.php";
if(file_exists(__DIR__.'/site/theme/functions.php')) {
	include __DIR__.'/site/theme/functions.php';
}
$page = new Site($config);
$page->init();
include __DIR__.'/site/theme/template.php';
?>