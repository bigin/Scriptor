<?php
namespace Scriptor;

use Imanager\Util;

class BasicTheme extends Site
{
	/**
	 * Template pieces 
	 */
	private $tpls;

	/**
	 * Config
	 */
	public $config;

	/**
	 * Articles page
	 * 
	 */
	private $articles;

	/**
	 * Init Basic theme module and make the theme configuration (site/themes/basic/_configs.php) 
	 * available inside the class.
	 */
	public function init()
	{
		ob_start();
		parent::init();
		$this->config['theme'] = Scriptor::load(__DIR__.'/../_configs.php');
		$this->tpls = Scriptor::load(__DIR__.'/_tpls.php');
		$this->articles = $this->getPage($this->getTCP('articles_page_id'));
	}

	/**
	 * Renders an element of the website that is responsible for displaying the 
	 * list of articles. All other calls are forwarded to the Site::___render() 
	 * method.
	 * 
	 * NOTE: The is a hookable method of the Site class, should remain hookable.
	 * 
	 * @param $element
	 */
	public function ___render(string $element) :?string
	{
		switch($element) {
			case 'archivesContent':
				return $this->renderContent();
			case 'archiveNav':
				return $this->renderArchiveNav();
			case 'pagination':
				return $this->articles->pagination;
			case 'hero':
				return $this->renderHero();
			case 'footerNav':
				return $this->renderFooterNav();
			case 'mainNavItems':
				return $this->renderMainNavItems();
			case 'messages':
				return $this->articles->msgs;
			case 'socIcons':
				return $this->renderSocIcons();
			case 'articleDate':
				return $this->renderArticleDate();
			case 'emptyCsrfFields':
				return $this->renderCsrfFields(false);
		}

		return parent::___render($element);
	}

	/**
	 * @return string
	 */
	private function renderContent() :string
	{
		// Is an archive?
		if($this->input->get->archive) {
			return $this->renderArchive(
				$this->sanitizer->url($this->input->get->archive)
			);
		}
		// or article list 
		return $this->renderArticles();
	}

	/**
	 * Prepares the archives view
	 * 
	 * @param string $str
	 * 
	 * @return string
	 */
	private function renderArchive(string $str) :string
	{
		$data = explode('-', $str, 2);
		if($data) {
			if(count($data) > 1) {
				$year = (int) $data[0];
				$month = $data[1];
			} else {
				$year = date('Y');
				$month = $data[0];
			}

			// All datasets created within one month.
			if(($end = strtotime("$month $year +1 month")) === false) {
				$this->throw404();
			}
			$start = strtotime("$month $year");
			$articles = $this->getArticlesWithin($start, $end);
			if($articles) {
				return $this->renderArticlesContent($articles);
			} else {
				$this->throw404();
			}
		}
	}
	
	/**
	 * Prepares the articles view
	 * 
	 * Note that here we cache the $pagination markup under articles->pagination. 
	 * This can be used to retrieve pagination if necessary.
	 * 
	 * @return string
	 */
	private function renderArticles() :string
	{
		$perpage = $this->getTCP('articles_per_page');

		$articles = $this->getPages('parent='.(int) $this->articles->id, [
			'sortBy' => 'created',
			'order' => 'desc',
			'length' => $perpage
		]);

		$this->articles->pagination = '';
		if($articles && $articles['total'] > $perpage) {
			$this->articles->pagination = $this->imanager->paginate($articles, [
				'limit' => $perpage, 
				'count' => (isset($articles['total']) ? $articles['total'] : 0)
				], [
				'wrapper' => $this->tpls['pagination_wrapper'],
				'central_inactive' => $this->tpls['pagination_inactive'],
				'central' => $this->tpls['pagination_active'],
				'prev' => $this->tpls['pagination_prev'],
				'prev_inactive' => $this->tpls['pagination_prev_inactive'],
				'next' => $this->tpls['pagination_next'],
				'next_inactive' => $this->tpls['pagination_next_inactive'],
				'ellipsis' => $this->tpls['pagination_ellipsis']
			]);
		}
		
		return $this->renderArticlesContent($articles);
	}

	/**
	 * Renders archive navi in the sidebar
	 * 
	 * @return string
	 */
	private function renderArchiveNav() :string
	{
		$rows = '';
		$archive = $this->archiveNavPages();

		if($archive) {
			$url = $this->getBasePath().$this->getPageUrl($this->articles, $this->pages);
			$curYear = date('Y');
			$curMonth = date('F');
			foreach($archive as $year => $arr) {
				foreach($arr as $month => $item) {
					if($curYear != $year) {
						$rows .= $this->templateParser->render($this->tpls['archive_nav_past_row'], [
							'URL' => $url.'?archive='.$year.'-'.strtolower($month),
							'MONTH' => $month,
							'YEAR' => $year
						]);
					} elseif($curMonth != $month) {
						$rows .= $this->templateParser->render($this->tpls['archive_nav_current_row'], [
							'URL' => $url.'?archive='.$year.'-'.strtolower($month),
							'MONTH' => $month
						]);
					}
				}
			}
		}
		return $rows;
	}

	/**
	 * The method retrieves archive pages.
	 * 
	 * @return array
	 */
	private function archiveNavPages() :array
	{
		$rows = [];
		$articles = $this->getPages('parent=2', ['sortBy' => 'created', 'order' => 'DESC']);
		if($articles) {
			foreach($articles['pages'] as $article) {
				$dtz = $this->getTCP('datetime_zone');
				if($dtz) {
					$date = new \DateTime('now', new \DateTimeZone($dtz));
				} else {
					$date = new \DateTime();
				}
				$date->setTimestamp($article->created);

				$month = $date->format('F');
				$year = $date->format('Y');
				
				$rows[$year][$month] = [
					'count' => isset($rows[$year][$month]['count']) ? ++$rows[$year][$month]['count'] : 1,
				];
			}
		}
		return $rows;
	}	

	/**
	 * Renders the content of the 'articles' page (articles list)
	 * 
	 * @param array $articles
	 * 
	 * @return string
	 */
	private function renderArticlesContent(array $articles = []) :string
	{
		if(!isset($articles['pages']) || empty($articles['pages'])) {
			return $this->templateParser->render($this->tpls['empty_article_row'], [
				'TEXT' => $this->getTCP('msgs')['no_articles_found']
			]);
		}
	
		$list = '';
		foreach($articles['pages'] as $article) {
			isset($i) OR $i = 0; $i++;

			$date = $this->getFormatedPageDate($article->created);
		
			$articleUrl = $this->getBasePath().$this->getPageUrl($article, $this->pages);

			$figure = '';
			if(isset($article->images[0])) {
				$imageUrl = $this->getBasePath().$article->images[0]->resize(800, 350, 0, 'adaptiveResize');
				$info = '';
				if($article->images[0]->title) {
					$info = $this->templateParser->render($this->tpls['art_list_image_caption'], [
						'TEXT' => $this->parsedown->text($article->images[0]->title)
					]);
				}
				
				$figure = $this->templateParser->render($this->tpls['art_list_figure'], [
					'URL' => $articleUrl,
					'DATA_SRC' => $imageUrl,
					'ALT' => '',
					'INFO_ROW' => $info
				]);
			}

			$this->parsedown->setSafeMode(true);

			if(mb_strlen($article->content) > $this->getTCP('summary_character_len')) {
				$content = $this->parsedown->text(mb_substr(htmlspecialchars_decode($article->content), 0, 
					$this->getTCP('summary_character_len')).' ...');
			} else {
				$content = $this->parsedown->text(htmlspecialchars_decode($article->content));
			}
		
			$list .= $this->templateParser->render($this->tpls['article_row'], [
				'HEADER_CLASS' => (($i == 1) ? ' class="uk-margin-top uk-padding-remove"' : ''),
				'URL' => $articleUrl,
				'HEADER_LINK_TITLE' => $article->name,
				'HEADER_TEXT' => $article->name,
				'CREATED_DATE' => $date,
				'FIGURE' => $figure,
				'CONTENT' => $content 
			]);
		}
		return $list;
	}

	/**
	 * Renders the date in the article view.
	 * 
	 * @return string 
	 */
	private function renderArticleDate() :string
	{
		$modified = '';
		if($this->page->created != $this->page->updated) {
			$modified .= $this->templateParser->render($this->tpls['modified_date'], [
				'DATE' => $this->getFormatedPageDate($this->page->updated)
			]);
		}

		$created = $this->templateParser->render($this->tpls['created_date'], [
			'DATE' => $this->getFormatedPageDate($this->page->created)
		]);

		return $this->templateParser->render($this->tpls['article_date'], [
			'CREATED_DATE' => $created,
			'MODIFIED_DATE' => $modified,
		]);
	}

	/**
	 * Renders hero area in the template. 
	 * We use "Parsedown" - a Markdown parser when rendering image 
	 * description, because this may contain Markdown links.
	 * 
	 * @return string
	 */
	private function renderHero() :string
	{
		$hero = '';
		if(isset($this->page->images[0])) {
			$imageUrl = $this->getBasePath().$this->page->images[0]->resize(1200, 0);
			$hero = $this->templateParser->render($this->tpls['hero'], [
				'SRC' => $imageUrl,
				'INFO' => $this->parsedown->text($this->page->images[0]->title)
			]);
		}
		return $hero;
	}

	/**
	 * This method extends Module::addMsg() by adding the header parameter.
	 * 
	 * @param string $type - Message type
	 * @param string $text - Message text
	 * @param string $header - Message header when needed
	 */
	public function addMsg(string $type, string $text, string $header = '') :void 
	{
		$headline = '';
		if(!empty($header)) {
			$headline .= $this->templateParser->render($this->tpls['msg_header'], [
				'TEXT' => $header,
			]);
			$this->msgs[] = [
				'type' => $this->sanitizer->text($type),
				'header' => $headline,
				'value' => $text
			];
		} else {
			parent::addMsg($type, $text);
		}
	}

	/**
	 * Renders messages
	 * 
	 * @return string
	 */
	public function renderMsgs() :string
	{
		$messages = '';
		$msgs = $this->getProperty('msgs');
		if(!empty($msgs)) {
			foreach($msgs as $msg) {
				$messages .= $this->templateParser->render($this->tpls['msg'], [ 
					'TYPE' => $msg['type'],
					'HEADER' => isset($msg['header']) ? $msg['header'] : '',
					'TEXT' => $msg['value']
				]);
			}
			unset($_SESSION['msgs']);
			$_SESSION['msgs'] = null;
		}
		$this->articles->msgs = $messages;
		return $this->articles->msgs;
	}

	/**
	 * The navigation elements in the footer
	 * 
	 * @return string
	 */
	private function renderFooterNav() :string
	{
		$container = $this->getPage($this->getTCP('footer_container_id'));
		if($container) {
			return $this->templateParser->render($this->tpls['footer_nav'], [
				'MENU_TITLE' => $container->menu_title,
				'INFO' => $container->content,
				'ITEM_ROWS' => $this->renderNavItems([
					'parent' => $container->id,
					'icon' => '&raquo; '
				])
			]);
		}
	}

	/**
	 * Renders the navigation elements
	 * 
	 * @param array $options
	 * 
	 * @return string
	 */
	private function renderNavItems(array $options = []) :string
	{
		$setup = array_merge([
			'parent' => 0,
			'maxLevel' => 0,
			'sortBy' => 'position',
			'order' => 'asc',
			'active' => true,
			'icon' => ''
		], $options);

		$data = $this->getPageLevels($setup);

		if(empty($data[$setup['parent']])) return '';

		$navi = '';
		foreach($data[$setup['parent']] as $page) {
			$class = ($this->page->slug == $page->slug || $this->page->parent == $page->id) ? 'uk-active' : '';
			$navi .= $this->templateParser->render($this->tpls['nav_item'], [
				'CLASS' => $class,
				'URL' => $this->getBasePath().$this->getPageUrl($page, $this->pages),
				'ICON' => !empty($setup['icon']) ? $setup['icon'] : '',
				'TITLE' => $page->menu_title 
			]);
		}

		return $navi;
	}

	/**
	 * Main navi
	 * 
	 * @return string
	 */
	private function renderMainNavItems() :string
	{
		return $this->renderNavItems([
			'exclude' => $this->getTCP('main_nav_exclude_ids')
		]);
	}

	/**
	 * Renders SOC icons 
	 * 
	 * @return string
	 */
	private function renderSocIcons() :string
	{
		$icons = '';
		foreach($this->getTCP('soc') as $name => $ref) {
			$icons .= $this->templateParser->render($this->tpls['icon_nav_row'], [
				'URL' => $ref['href'],
				'ICON_NAME' => $name
			]);
		}
		return $icons;
	}

	/**
	 * Cross-site request forgery protection.
	 * 
	 * It generates markup from two hidden fields containing CSRF "token" and "name".
	 * 
	 * @param bool $setToken
	 * 
	 * @return string
	 */
	private function renderCsrfFields(bool $setToken = true) :string
	{
		$csrf = Scriptor::getCSRF();
		return $this->templateParser->render($this->tpls['csrf_token_fields'], [
			'NAME' => ($setToken) ? $csrf->getTokenName() : '',
			'VALUE' => ($setToken) ? $csrf->getTokenValue() : ''
		]);
	}

	/**
	 * Executed after sending the contact form on the website.
	 * 
	 * Three underscores ___ is a hookable method 
	 */
	public function ___contactAction() :void
	{
		$err = false;
		$csrf = Scriptor::getCSRF();

		if($this->config['protectCSRF']) {
			if(!$csrf->isTokenValid($this->input->post->tokenName, $this->input->post->tokenValue)) {
				$this->addMsg('danger', $this->getTCP('msgs')['csrf_token_mismatch']);
				$this->renderMsgs();
				Helper::sendJsonResponse([
					'msgs' => $this->articles->msgs,
				]);
			}
		}

		$mailData = [
			'subject' => $this->getTCP('email')['subject_contact'],
			'to' => $this->getTCP('email')['email_to'],
			'to_name' => $this->getTCP('email')['email_to_name'],
			'from' => $this->sanitizer->email($this->input->post->replyto),
			'from_name' => $this->sanitizer->text($this->input->post->name),
			'body' => $this->sanitizer->textarea($this->input->post->text)
		];

		if(empty($mailData['from'])) {
			$this->addMsg('danger', $this->getTCP('msgs')['empty_from_field']);
			$err = true;
		} elseif(empty($mailData['from_name']) || empty($mailData['body'])) {
			$this->addMsg('danger', $this->getTCP('msgs')['empty_mandatory_fields']);
			$err = true;
		}

		if($err) {
			$this->renderMsgs();
			Helper::sendJsonResponse([
				'msgs' => $this->articles->msgs
			]);
		}

		$result = $this->sendMail($mailData);

		if($result !== true) {
			$this->addMsg('danger', $this->getTCP('msgs')['error_sending_email']);
			$this->renderMsgs();
			Helper::sendJsonResponse([
				'msgs' => $this->articles->msgs
			]);
		}

		$this->addMsg('success', $this->getTCP('msgs')['email_received']);
		$this->renderMsgs();
		Helper::sendJsonResponse([
			'success' => true,
			'msgs' => $this->articles->msgs
		]);
	}

	/**
	 * Sending email
	 * 
	 * This function does not validate the user input ($data) - make sure you validate 
	 * the user input beforehand (use sanitizer).
	 * 
	 * INFO: a hookable method
	 * 
	 * @param $data
	 * @return bool - Returns true if the mail was successfully accepted for delivery, 
	 *                false otherwise.
	 */
	public function ___sendMail(array $data) :bool
	{
		$headers = "From: $data[from_name] <$data[from]>" . "\r\n" .
			"Reply-To: $data[from]" . "\r\n" .
			'X-Mailer: PHP/' . phpversion();

		return mail($data['to'], $data['subject'], $data['body'], $headers);
	}

	/**
	 * Returns new CSRF token in JSON format.
	 */
	public function loadTokenAction() :void
	{
		$csrf = Scriptor::getCSRF();
		Helper::sendJsonResponse([
			'success' => true,
			'csrf' => [
				'tokenName' => $csrf->getTokenName(),
				'tokenValue' => $csrf->getTokenValue()
			]
		]);
	}

	/**
	 * Subscribe user
	 * A MailChimp account must be created before this.
	 * The method is executed when the user has submitted the Subscribe form.
	 */
	public function ___subscribeAction() :void
	{
		$csrf = Scriptor::getCSRF();

		if($this->config['protectCSRF']) {
			if(!$csrf->isTokenValid($this->input->post->tokenName, $this->input->post->tokenValue)) {
				$this->addMsg('danger', $this->getTCP('msgs')['csrf_token_mismatch']);
				Helper::sendJsonResponse([
					'msgs' => $this->renderMsgs(),
				]);
			}
		}

		$subscData = [
			//'name' => $this->sanitizer->text($this->input->post->name),
			// GDRP?
			//'confirm' => (int) $this->input->post->confirm,
			'email' => mb_strtolower($this->sanitizer->email($this->input->post->email))
		];

		if(empty($subscData['email'])) {
			$this->addMsg('danger', $this->getTCP('msgs')['empty_email_field']);
			Helper::sendJsonResponse([
				'success' => true,
				'msgs' => $this->renderMsgs()
			]);
		}

		$mc = new MailChimp($this->getTCP('mail_chimp'));

		$subscriber = $mc->get($subscData['email']);

		$result = '';
		
		if(isset($subscriber['email_address']) && isset($subscriber['email_address']) == $subscData['email']) {
			// already subscriber?
			if($subscriber['status'] == 'subscribed') {
				$this->addMsg('success', $this->getTCP('msgs')['subsc_email_exists']);
				Helper::sendJsonResponse([
					'success' => true,
					'msgs' => $this->renderMsgs()
				]);
			}

			// Contact unsubscribed, change to subscribed
			$result = $mc->change([
				'email_address' => $subscData['email'],
				'status_if_new' => 'pending',
				'status' => 'pending'
			]);
			if($mc->code == 200) {
				// Show notice: email sent
				$sec = $this->templateParser->render($this->getTCP('msgs')['subsc_email_confirmation'], [
					'EMAIL' => $subscData['email']
				]);
				$this->addMsg('success', $sec, $this->getTCP('msgs')['subsc_email_header']);
				Helper::sendJsonResponse([
					'success' => true,
					'msgs' => $this->renderMsgs()
				]);
			}

		// The contact doesn’t exist in the mailing list
		} elseif($mc->code == 404) {
            $result = $mc->add([
				'email_address' => $subscData['email'],
				'status' => 'pending'
			]);
            if($mc->code == 200) {
				// Show notice: email sent
				$sec = $this->templateParser->render($this->getTCP('msgs')['subsc_email_confirmation'], [
					'EMAIL' => $subscData['email']
				]);
				$this->addMsg('success', $sec, $this->getTCP('msgs')['subsc_email_header']);
				Helper::sendJsonResponse([
					'success' => true,
					'msgs' => $this->renderMsgs()
				]);
            }
        }

		$this->addMsg('danger', $this->getTCP('msgs')['subsc_faild']);
        Util::dataLog('Failed to add your email address to our Newsletter mailing list. Response: ' . print_r($result, true));
        Helper::sendJsonResponse([
			'success' => false,
			'msgs' => $this->renderMsgs()
		]);
	}

	/**
	 * Returns articles that were created in a specified time period.
	 * We use this method to sort the articles by months (see "archive").
	 * 
	 * @param int $start - timestamp
	 * @param int $end - timestamp
	 * 
	 * @return null|array
	 */
	public function getArticlesWithin(int $start, int $end) :?array
	{
		$contId = $this->getTCP('articles_page_id');
		$levels = $this->getPageLevels(['parent' => $contId]);

		if(!isset($levels[$contId]) || empty($levels[$contId])) return null;

		$articles = $this->getPages("created >= $start", [
			'items' => $levels[$contId],
			'length' => 0
		]);

		if(!$articles || empty($articles['pages']) ) return null;
		
		$period = $this->getPages("created < $end", [
			'items' => $articles['pages'],
			'sortBy' => 'created',
			'order' => 'desc',
			'length' => 0
		]);
		
		return $period;
	}

	/**
	 * Retrieves a theme configurations property
	 * 
	 * @param string $prop - Property name
	 * 
	 * @return mixed
	 */
	public function getTCP(string $prop) :mixed
	{
		return isset($this->config['theme'][$prop]) ? $this->config['theme'][$prop] : null;
	}

	/**
	 * Use REQUEST_URI over $this->siteUrl 
	 * 
	 * TODO: 
	 * The method has a disadvantage, the first segments can be manipulated
	 * like this ... /fake/dir/start-page/ etc.
	 * 
	 * @return string
	 */
	public function getBasePath() :string
	{
		if(isset($_SERVER['REQUEST_URI']) && ! empty($_SERVER['REQUEST_URI'])) {
			$urlArr = explode('?', $_SERVER['REQUEST_URI'], 2);
			if(is_array($urlArr)) {
				$url = $this->sanitizer->url($urlArr[0]);
				return str_replace($this->input->get->id, '', $url);
			}
		}
		return $this->siteUrl.'/';
	}

	/**
	 * Returns formatted date.
	 * 
	 * @param int $date - timestamp (numeric value)
	 * 
	 * @return string
	 */
	public function getFormatedPageDate(int $datestamp) :string
	{
		$dtz = $this->getTCP('datetime_zone');
		if($dtz) $date = new \DateTime('now', new \DateTimeZone($dtz));
		else $date = new \DateTime();
		
		$date->setTimestamp($datestamp);
		return $date->format($this->getTCP('datetime_format'));
	}

	/**
	 * Page output caching
	 * 
	 * Allows to cache rendered output of a complete page to the cache.
	 * Note that only the pages listed in the "cacheable_templates" 
	 * configuration variable will be cached.
	 * 
	 * @return string
	 */
	public function cache() :string
	{
		$output = ob_get_clean();
		if($this->page && in_array($this->page->template, $this->getTCP('cacheable_templates'))) {
			$this->imanager->sectionCache->save($output);
		}
		return $output;
	}
}
