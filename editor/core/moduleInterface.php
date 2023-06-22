<?php

namespace Scriptor\Core;

/**
 * ModuleInterface currently not used but reserved for future functionality
 */
interface ModuleInterface
{ 
	public static function moduleInfo() : array;

	public static function moduleHooks() : array;

	public function loadModule(string $moduleName);
	
	public function execute();

	public function install() : bool;

	public function uninstall() : bool;
}