<?php
// Define constants
include_once __DIR__.'/_def.php';
// Util
include_once IM_SOURCEPATH.'_Util.php';
// Manager
include_once IM_SOURCEPATH.'Manager.php';
// ItemManager
include_once IM_SOURCEPATH.'ItemManager.php';

/**
 * ItemManager's core function, we use it to create a static ItemManager instance
 *
 * @param string $name
 *
 * @return Imanager\ItemManager instance
 */
function imanager($name='')
{
	global $im;
	if($im === null) $im = new Imanager\ItemManager();
	return !empty($name) ? $im->$name : $im;
}
