<?php
// bootstrapping IManager core
$root = dirname(dirname(dirname(dirname(__DIR__))));
include_once($root.'/imanager.php');
// logged in users only
defined('IS_IM') or die();
if(!isset($_SESSION['loggedin']) && true != $_SESSION['loggedin']) die();

require('UploadHandler.php');
$uploadHandler = new \Imanager\UploadHandler();
$uploadHandler->init();