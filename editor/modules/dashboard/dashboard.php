<?php

namespace Scriptor;

class Dashboard extends Module
{
	/**
	 * Default execute module method
	 */
	public function execute()
	{
		$this->pageTitle = 'Dashboard - Scriptor';
		$this->pageContent = $this->renderDashboard();
		$this->breadcrumbs = '<li><span>'.$this->i18n['dashboard_menu'].'</span></li>';
	}

	protected function renderDashboard()
	{
		return $this->i18n['dashboard_content'];
	}
}