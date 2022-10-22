<?php
if(! isset($_SESSION)) { 
	session_name('IMSESSID');
	session_start(); 
}
// bootstrapping IManager core
$root = dirname(__DIR__, 4);
//include_once($root.'/imanager.php');
// bootstrap Scriptor
include_once($root.'/boot.php');
// logged in users only
defined('IS_IM') or die();
if(! isset($_SESSION['loggedin']) || true != $_SESSION['loggedin']) die();

require('UploadHandler.php');
$uploadHandler = new \Imanager\UploadHandler();
$uploadHandler->init();