<?php
use Scriptor\Scriptor;

require_once  __DIR__.'/boot.php';

$site = null;
if(file_exists(__DIR__."/site/themes/$config[theme_path]_ext.php")) { 
	include __DIR__."/site/themes/$config[theme_path]_ext.php";
} else { 
	$site = Scriptor::getSite();
	$site->execute();
}
include __DIR__."/site/themes/$config[theme_path]template.php";
