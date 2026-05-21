<?php

$tplName = $site->sanitizer->templateName($site->currentTemplate());
$tplFile = __DIR__."/$tplName.php";

ob_start();
if (file_exists($tplFile)) {
	include $tplFile;
} else {
	include __DIR__.'/default.php';
}

echo $site->cache();
