<?php
/**
 * Class Module
 *
 * Extendable module class
 *
 */
class Module
{
	const VERSION = '1.3.0';
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
	 * @var object $page - The current page object instance
	 */
	protected $page;

	/**
	 * @var array $users - An array of Users objects
	 */
	protected $users;

	/**
	 * @var object $user - A current user object instance
	 */
	protected $user;

	/**
	 * @var array $msgs - An array of local error messages
	 */
	protected $msgs;

	/**
	 * @var array $i18n - An array of language sets
	 */
	public $i18n;

	/**
	 * @var string $messages - rendered messages (markup)
	 */
	public $messages;

	/**
	 * @var string $breadcrumbs - Breadcrumbs markup
	 */
	public $breadcrumbs;

	/**
	 * Module constructor
	 *
	 * @param $config
	 */
	public function __construct($config)
	{
		$this->config = $config;
		$this->config['version'] = self::VERSION;
		require "lang/{$this->config['editor_lang']}.php";
		$this->i18n = $i18n;
	}

	/**
	 * Init module class
	 *
	 * Prepares some variables for local use and executes actions.
	 *
	 */
	public function init()
	{
		if($this->config['dif_lang_packs']) {
			foreach($this->config['dif_lang_packs'] as $pack) {
				if(file_exists("../data/lang/$pack{$this->config['editor_lang']}.php")) {
					$customI18n = include "../data/lang/$pack{$this->config['editor_lang']}.php";
					$this->i18n = array_merge($this->i18n, $customI18n);
				}
			}
		}
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

		$this->execute();
		$this->renderMessages();
	}

	/**
	 * Editor mapper
	 *
	 * @param $editor
	 */
	protected function map($editor)
	{
		$this->editor = & $editor;
		foreach($this->editor as $key => $value) {
			$this->$key = & $this->editor->$key;
		}
	}

	protected function execute(){}

	protected function renderMessages()
	{
		if(!empty($this->msgs)) {
			$this->messages .= '<div class="message">';
			foreach($this->msgs as $msg) {
				if($msg['type'] == 'error') {
					$this->messages .= '<p class="error">'.$msg['value'].'</p>';
				} elseif($msg['type'] == 'success') {
					$this->messages .= '<p class="success">'.$msg['value'].'</p>';
				}
			}
			$this->messages .= '</div>';
			unset($_SESSION['msgs']);
			$_SESSION['msgs'] = null;
		}
	}
}