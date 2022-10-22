<?php

namespace Scriptor\Core;

/**
 * ModuleInterface currently not used but reserved for future functionality
 */
interface ModuleInterface 
{ 
	public function loadModule($moduleName);
	
	public function execute();
}