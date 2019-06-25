<?php
require '../imanager.php';
require '../data/settings/scriptor-config.php';
require 'module.php';
require 'editor.php';
require 'csrf.php';

$editor = new Editor($config);
$editor->init();