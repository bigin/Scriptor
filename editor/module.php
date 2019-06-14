<?php
/**
 * Class Module
 *
 * Extendable module class
 *
 */
class Module
{
	/**
	 * @var object $imanager - Instance of IManager
	 */
	protected $imanager;

	/**
	 * @var array $config - Scriptor config
	 */
	public $config;

	/**
	 * @var sting $pageTitle - Meta page title
	 */
	public $pageTitle;

	/**
	 * @var string $pageUrl - Current page URL
	 */
	public $pageUrl;

	/**
	 * @var string $pageContent - Current page content
	 */
	public $pageContent;

	/**
	 * @var object $input - Input object instance
	 */
	protected $input;

	/**
	 * @var object $segments - Segments object instance
	 */
	protected $segments;

	/**
	 * @var array $pages - An array of Page objects
	 */
	protected $pages;

	/**
	 * @var array $users - An array of Users objects
	 */
	protected $users;

	/**
	 * @var array $msgs - An array of local error messages
	 */
	protected $msgs;

	/**
	 * Module constructor
	 *
	 * @param $config
	 */
	public function __construct($config) {
		$this->config = $config;
	}

	public function init() {
		$this->imanager = imanager();
		$this->pageUrl = $this->imanager->config->getUrl();
		$this->input = $this->imanager->input;
		$this->segments = $this->input->urlSegments;
		$this->pages = $this->imanager->getCategory('name=Pages');
		$this->users = $this->imanager->getCategory('name=Users');
		if(!isset($_SESSION['msgs'])) {
			$_SESSION['msgs'] = [];
		}
		$this->msgs = & $_SESSION['msgs'];
	}

	public function inject($editor) { $this->editor = $editor; }
}