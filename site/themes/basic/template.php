<?php defined('IS_IM') or die('You cannot access this page directly'); 

$tplName = $site->sanitizer->templateName($site->page->template);
$tplFile = __DIR__."/$tplName.php";

if(file_exists($tplFile)) {
	include $tplFile;
} else {
	include __DIR__.'/default.php';
}

echo $site->cache();
