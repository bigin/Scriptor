<?php

use Imanager\Util;

/**
 * Scriptor's Editor class
 * 
 * Is assigned to use the editor modules in Scriptor CMS.
 * Takes care of the loading and unloading of the editor 
 * modules.
 */
class Editor extends Module
{
	protected function execute()
	{
		$this->siteUrl = $this->imanager->config->getUrl();
		// Set default start segment
		if(!$this->segments->get(0)) { $this->segments->set(0, 'dashboard'); }
		// Execute Module
		if(array_key_exists($this->segments->get(0), $this->config['modules'])) {
			$module = $this->config['modules'][$this->segments->get(0)];
			// Is module disabled?
			if(!$module['active']) { return; }
			$auth = isset($module['auth']) ? $module['auth'] : true;
			if(true === $auth && (!isset($_SESSION['loggedin']) || true != $_SESSION['loggedin'])) {
				// Todo: That should to be more dynamic
				Util::redirect($this->siteUrl.'auth/login/');
			}
			// Check module file exists. NOTE: deprecated method
			if(file_exists($module['path'] . '.php')) {
				// include module
				include_once $module['path'] . '.php';
				$module = new $module['class']($this->config);
				$module->auth = $auth;
				$module->map($this);
				$module->execute();
			}
			// Used since v. 1.3.4
			elseif(file_exists(__DIR__ ."/$module[path].php")) {
				include_once __DIR__ ."/$module[path].php";
				$module = new $module['class']($this->config);
				$module->auth = $auth;
				$module->map($this);
				$module->execute();
			}
		}
	}
}