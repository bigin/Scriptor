<?php

use Scriptor\Core\Scriptor;

if (! isset($_SESSION)) { 
	session_name('IMSESSID');
	session_start(); 
}

$base = dirname(dirname(__DIR__));
require "$base/imanager.php";
require "$base/data/settings/scriptor-config.php";
if (file_exists("$base/data/settings/custom.scriptor-config.php")) {
	$config = array_replace_recursive(
		$config, include "$base/data/settings/custom.scriptor-config.php"
	);
}

require __DIR__.'/scriptor.php';
require __DIR__.'/moduleInterface.php';
require __DIR__.'/module.php';
require __DIR__.'/page.php';
require __DIR__.'/pages.php';
require __DIR__.'/user.php';
require __DIR__.'/users.php';
require __DIR__.'/editor.php';
require __DIR__.'/site.php';
require __DIR__.'/csrf.php';

spl_autoload_register(function ($pClassName) {
    $basePath = 'Scriptor\Modules\\';
    $pClassName = str_replace('\\', '/', str_replace($basePath, '', $pClassName));
    $inclClass = IM_ROOTPATH . "site/modules/$pClassName.php";
    if (file_exists($inclClass)) {
        include_once $inclClass;
    }
});

Scriptor::build($config);

$editor = Scriptor::getEditor();
$editor->execute();

if (file_exists("$base/$config[editor_template].php")) {
	include_once "$base/$config[editor_template].php";
} elseif (file_exists("$base/$config[admin_path]$config[editor_template].php")) {
	include_once "$base/$config[admin_path]$config[editor_template].php";
} else {
	include_once "$base/$config[admin_path]theme/template.php";
}