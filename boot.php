<?php

use Scriptor\Core\Scriptor;

require __DIR__.'/imanager.php';
require __DIR__.'/data/settings/scriptor-config.php';
if (file_exists(__DIR__.'/data/settings/custom.scriptor-config.php')) { 
	$config = array_replace_recursive(
		$config, include __DIR__.'/data/settings/custom.scriptor-config.php'
	);
}

$corePath = __DIR__."/$config[admin_path]core/";
require $corePath.'scriptor.php';
require $corePath.'moduleInterface.php';
require $corePath.'module.php';
require $corePath.'page.php';
require $corePath.'pages.php';
require $corePath.'user.php';
require $corePath.'users.php';
require $corePath.'site.php';
require $corePath.'csrf.php';

Scriptor::build($config);