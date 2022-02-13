<?php
namespace Scriptor;

use Imanager\Util;

class BasicRouter
{
	/**
	 * Site instance
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
	 * This should only affect the blog page, everything else 
	 * goes to the default site::execute(). 
	 * 
	 * The pages inside your container are automatically 
	 * assigned template 'blog-post'.
	 */
	public function execute() :void
	{
		$this->actions();

		$articles = $this->site->getPage((int) $this->site->getTCP('articles_page_id'));
		
		if($articles->slug != $this->site->segments->getlast()) {
			$this->site->execute();
			if($this->site->page->parent == $articles->id) $this->site->page->template = 'blog-post';
		} else {
			if(!$articles || !$articles->active) $this->site->throw404();
			$pageUrl = $this->site->getPageUrl($articles, $articles->pages);
			if(strpos($this->site->segments->getUrl(), $pageUrl) === false) $this->site->throw404();
			$this->site->page = $articles;
		}
	}

	/**
	 * Check user actions
	 */
	private function actions() :void
	{
		$post = $this->site->input->post;
		if($post->action && in_array($post->action, $this->site->getTCP('allowed_actions'))) {
			$name = $post->action;
			$func = $name.'Action';
			$this->site->$func();
		}
	}
}