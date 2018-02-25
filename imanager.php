<?php
$basedir = __DIR__;
if(DIRECTORY_SEPARATOR != '/') {
	$basedir = str_replace(DIRECTORY_SEPARATOR, '/', $basedir);
}
if(strpos($basedir, '..') !== false) $basedir = realpath($basedir);
include($basedir.'/imanager/inc/_inc.php');
$imanager = imanager();