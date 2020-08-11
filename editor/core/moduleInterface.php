<?php

namespace Scriptor;

/**
 * ModuleInterface currently not used but reserved for future functionality
 */
interface ModuleInterface 
{ 
	public function loadModule($moduleName);
	
	public function execute();
    /**
     * Commented out since 1.4.6
     * Check if this is really needed, if not remove it completely.
     */
	//public function checkAction();
}