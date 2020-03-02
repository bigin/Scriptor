<?php

use Scriptor\Scriptor;
use Scriptor\Site;

require __DIR__.'/imanager.php';
require __DIR__.'/data/settings/scriptor-config.php';
if(file_exists(__DIR__.'/data/settings/custom.scriptor-config.php')) { 
    include __DIR__.'/data/settings/custom.scriptor-config.php';
}

require __DIR__."/$config[admin_path]core/scriptor.php";
require __DIR__."/$config[admin_path]core/moduleInterface.php";
require __DIR__."/$config[admin_path]core/module.php";
require __DIR__."/$config[admin_path]core/site.php";
require __DIR__."/$config[admin_path]core/csrf.php";

Scriptor::build($config);

$site = null;
if(file_exists(__DIR__."/site/themes/$config[theme_path]_ext.php")) { 
    include __DIR__."/site/themes/$config[theme_path]_ext.php";
} else { 
    $site = Scriptor::getSite();
    $site->execute();
}
include __DIR__."/site/themes/$config[theme_path]template.php";
