<?php

namespace Themes\Basic;

use Scriptor\Core\Module;

/**
 * This class handles the routing for the basic theme.
 *
 */
class BasicRouter
{
	/**
	 * @var Scriptor\Core\Module $site instance
	 */
	private $site;

	/**
	 * Init Basic theme module
	 */
	public function __construct(Module $site)
	{
		$this->site = $site;
	}

	/**
	 * It only affects the blog page, everything else goes to the default site::execute(). 
	 * The pages inside your container are automatically assigned template 'blog-post'.
	 * 
	 * @return void
	 */
	public function execute() :void
	{
		$this->actions();

		$articles = $this->site->pages()->getPage((int) $this->site->getTCP('articles_page_id'));
		
		if ($articles && $articles->slug != $this->site->urlSegments->getlast()) {
			$this->site->execute();
			if ($this->site->page->parent == $articles->id) {
				$this->site->page->template = 'blog-post';
			}
		} else {
			if (!$articles || !$articles->active) {
				$this->site->throw404();
			}
			$pageUrl = $this->site->getPageUrl($articles, $articles->pages);
			if (strpos($this->site->urlSegments->getUrl(), $pageUrl) === false) {
				$this->site->throw404();
			}
			$this->site->page = $articles;
		}
	}

	/**
	 * Check user actions
	 * 
	 * @return void
	 */
	public function actions() :void
	{
		$post = $this->site->input->post;
		if ($post->action && in_array($post->action, $this->site->getTCP('allowed_actions'))) {
			$name = $post->action;
			$func = $name.'Action';
			$this->site->$func();
		}
	}
}
