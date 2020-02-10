<?php

class Settings extends Module
{
    /**
	 * Default execute module method
	 */
	public function execute()
	{
		$this->pageContent = $this->renderSettingsEditor();
		$this->breadcrumbs = '<li><a href="../">'.$this->i18n['dashboard_menu'].'</a></li><li><span>'.
		$this->i18n['settings_menu'].'</span></li>';
    }

    protected function renderSettingsEditor()
	{
		return '<p>'.$this->i18n['settings_page_text'].'</p>';
	}
}