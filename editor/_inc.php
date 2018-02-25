<?php
require('../imanager.php');
require('../data/settings/scriptor-config.php');
require('editor.php');

$editor = new Editor($config);
$editor->init();