<?php
$basedir = __DIR__;
if(strpos($basedir, '..') !== false) $basedir = realpath($basedir);
include $basedir.'/imanager/inc/_inc.php';
$imanager = imanager();