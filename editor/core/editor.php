<?php

namespace Scriptor\Core;

use Imanager\TemplateParser;
use Imanager\Util;
use Scriptor\Core\Module;

defined('IS_EDITOR') OR define('IS_EDITOR', true);

/**
 * Scriptor's Editor module
 * 
 * Is assigned to use the editor modules in Scriptor CMS.
 * This module takes care of the loading and unloading of 
 * the editor modules.
 */
class Editor extends Module
{
	// Current editor module
	private $module;

	public function execute()
	{
		if (Scriptor::execHook($this, 'execute', [], 'before') && 
			$this->event->replace
		) return;

		// Set default start segment & redirect
		if (!$this->segments->get(0)) {
			$fstKey = array_key_first($this->config['modules']);
			$this->segments->set(0, $fstKey);
			Util::redirect($this->siteUrl.'/'.$this->segments->get(0).'/');
		}
		$this->csrf = Scriptor::getCSRF();
		// Execute Module
		if (array_key_exists($this->segments->get(0), $this->config['modules'])) {
			$module = $this->config['modules'][$this->segments->get(0)];
			// Is module disabled?
			if (!$module['active']) { return; }
			$auth = isset($module['auth']) ? $module['auth'] : true;
			if ($auth === true && (! isset($_SESSION['loggedin']) || $_SESSION['loggedin'] != true)) {
				// Todo: That should to be more dynamic
				Util::redirect($this->siteUrl.'/auth/login/');
			}

			$this->module = $this->loadModule(
				$this->imanager->sanitizer->name($this->segments->get(0)),
				[
					//'namespace' => __NAMESPACE__.'\Modules\\',
					'autoinit' => isset($module['autoinit']) ? $module['autoinit'] : true
				]
			);
			if ($this->module) $this->module->execute();
		}
		// Module not found
		else {
			if (!isset($_SESSION['loggedin']) || true != $_SESSION['loggedin']) {
				Util::redirect($this->siteUrl.'/auth/login/');
			}
			$this->moduleNotFound();
		}
		Scriptor::execHook($this, 'execute', [], 'after');
		// The last step is to render the messages.
		$this->renderMessages();
	}

	public function getProperty($property) :mixed
	{
		if(isset($this->module->$property)) { return $this->module->$property; }
		return isset($this->$property) ? $this->$property : null;
	}

	/**
	 * If the user is logged into the editor, 
	 * an error message is displayed.
	 */
	private function moduleNotFound()
	{
		$templateParser = new TemplateParser();
		$this->pageTitle = 'Module Not Found - Scriptor';
		$this->pageContent = $templateParser->render(
			$this->i18n['error_module_not_found'], [
			'module' => $this->imanager->sanitizer->pageName($this->segments->get(0))
		]);
	}
}