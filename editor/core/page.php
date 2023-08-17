<?php

namespace Scriptor\Core;

use Imanager\Item;

class Page extends Item
{
	public string $menu_title = '';

	public string $slug = '';

	public string $template = '';

	public int $parent = 0;

	public string $pagetype = '';
	
	public string $content = '';

	/**
	 * Auto set id of Scriptor's Pages category if the $category_id isn't specified.
	 * 
	 * @param null|int $category_id - Optional
	 */
	public function __construct(?int $category_id = null)
	{
		if (!$category_id) {
			$this->categoryid = imanager()->getCategory('name=Pages')->id;
		} else {
			$this->categoryid = (int) $category_id;
		}

		parent::__construct($this->categoryid);
	}

	/**
	 * Retrieves child pages of the page.
	 * 
	 * @return array
	 */
	public function children(array $options = []) :array
	{
		return Scriptor::getSite()->pages()->getPageChildren($this, $options);
	}


	/**
	 * Tries to save item, with a previous check and 
	 * adjustment of particular parameters.
	 * 
	 * @return bool
	 */
	public function save() : bool
	{
		$sanitizer = imanager('sanitizer');

		$this->name = $sanitizer->text(str_replace('"', '', $this->name));
		($this->name) OR throw new \ErrorException('A name attribute value is expected');

		if ($this->menu_title) {
			$this->menu_title = $sanitizer->text(str_replace('"', '', $this->menu_title));
		} else {
			$this->menu_title = $this->name;
		}

		$url = trim($this->name, '-');
		if ($this->slug) {
			$this->slug = preg_replace("/(-)\\1+/", "$1",
				$sanitizer->pageName($this->slug));
		} else {
			$this->slug = preg_replace("/(-)\\1+/", "$1",
				$sanitizer->pageName($url));
		}

		// Its one of the reserved names?
		if (in_array($this->slug, Scriptor::getProperty('config')['reservedSlugs'])) {
			throw new \ErrorException('The name or slug is reserved');
		}

		// name and slug incompatible 
		if ($this->name && ! $this->slug) throw new \ErrorException('The name is not allowed');

		if ($this->template) {
			$this->template = $sanitizer->templateName($this->template);
		}

		if ($this->parent) {
			if ($this->id == $this->parent) return false;
			$parent = imanager()->getCategory($this->categoryid)->getItem($this->parent);
			if (!$parent) {
				throw new \ErrorException('The parent id is invalid');
			}
			$this->parent = (int) $parent->id;
		} else {
			$this->parent = 0;
		}

		$this->pagetype = $this->pagetype ?? '1';
		$this->content  = (string) $this->content ?? '';
		$this->template = (string) $this->template ?? '';
		$this->active = (bool) $this->active ?? false;

		return parent::save();
	}

	/**
	 * This static method is called for pages exported by var_export()
	 *
	 * @param $an_array
	 *
	 * @return Page
	 */
	public static function __set_state($an_array) :Page
	{
		$_instance = new Page($an_array['categoryid']);
		foreach ($an_array as $key => $val) {
			if (is_array($val)) $_instance->{$key} = $val;
			else $_instance->{$key} = $val;
		}
		return $_instance;
	}
}