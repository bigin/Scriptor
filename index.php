<?php

use Scriptor\Scriptor;
use Scriptor\Site;

require __DIR__.'/imanager.php';
require __DIR__.'/data/settings/scriptor-config.php';
if(file_exists(__DIR__.'/data/settings/custom.scriptor-config.php')) { 
	include __DIR__.'/data/settings/custom.scriptor-config.php';
}

$corePath = __DIR__."/$config[admin_path]core/";
require $corePath.'scriptor.php';
require $corePath.'moduleInterface.php';
require $corePath.'module.php';
require $corePath.'site.php';
require $corePath.'csrf.php';

Scriptor::build($config);

$site = null;
if(file_exists(__DIR__."/site/themes/$config[theme_path]_ext.php")) { 
	include __DIR__."/site/themes/$config[theme_path]_ext.php";
} else { 
	$site = Scriptor::getSite();
	$site->execute();
}
include __DIR__."/site/themes/$config[theme_path]template.php";
