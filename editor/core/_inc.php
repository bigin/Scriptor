<?php

use Scriptor\Scriptor;

$base = dirname(dirname(__DIR__));
require "$base/imanager.php";
require "$base/data/settings/scriptor-config.php";
if(file_exists("$base/data/settings/custom.scriptor-config.php")) { 
	include "$base/data/settings/custom.scriptor-config.php";
}

require __DIR__.'/scriptor.php';
require __DIR__.'/moduleInterface.php';
require __DIR__.'/module.php';
require __DIR__.'/editor.php';
require __DIR__.'/csrf.php';

Scriptor::build($config);

$editor = Scriptor::getEditor();
$editor->execute();

if(file_exists("$base/$config[editor_template].php")) {
	include "$base/$config[editor_template].php";
} elseif(file_exists("$base/$config[admin_path]$config[editor_template].php")) {
	include "$base/$config[admin_path]$config[editor_template].php";
} else {
	include "$base/$config[admin_path]theme/template.php";
}