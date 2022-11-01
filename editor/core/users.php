<?php
namespace Scriptor\Core;

class Users extends Pages
{
	public $config;

	public $category;

	public function __construct()
	{
		parent::init();
		$this->category = $this->imanager->getCategory('name=Users');
	}

	/**
	 * Searches the records for a specific user using a selector statement.
	 * 
	 * @param int|string $selector
	 * @param array $pages
	 */
	public function getUser(int|string $selector, array $pages = [])
	{
		return $this->getPage($selector, $pages);
	}
	
	/**
	 * Searches the records for any number of users with a selector statement.
	 * 
	 * @param string $selector - e.g. 'field_name=value' or 'attribute_name=value'
	 * @param array $conds - Conditions
	 */
	public function getUsers(string $selector = '', array $conds = []) :?array
	{
		return $this->getPages($selector, $conds);
	}

	/**
	 * Delete user
	 */
	public function deleteUser(int|object $target, array $opts = []) :bool
	{
		return $this->deletePage($target, $opts);
	}
}