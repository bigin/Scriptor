<?php

namespace Scriptor;

interface ModuleInterface 
{ 
	public function loadModule($moduleName);
	
	public function execute();

	public function checkAction();
}