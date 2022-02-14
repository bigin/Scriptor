<?php namespace Imanager;

/**
 * Class TemplateParser
 * @package Imanager
 *
 * We use this module as a kind of a template
 * engine for parsing simple templates.
 *
 */
class TemplateParser
{

	/**
	 * @var \Im\ItemManager - ItemManager instance
	 */
	protected $imanager;
	/**
	 * @var string - Path to templates
	 */
	public $path;

	/**
	 * @var string - Output buffer
	 */
	protected $output;

	/**
	 * @var string - Cached template name
	 */
	protected $runtimeCacheKey = null;

	/**
	 * TemplateParser constructor.
	 */
	public function __construct() { $this->imanager = imanager(); }

	/**
	 * @param string $tplpath
	 */
	public function init($tplpath = '')
	{
		if(empty($tplpath)) {
			$this->path = IM_TEMPLATEPATH;
		} else {
			$this->path = $tplpath;
		}
	}

	/**
	 * @tpl - official template file path or string
	 * @tvs - template variables
	 * @clean - cleaning template
	 */
	public function render($tpl, array $tvs=array(), $clean=false)
	{
		if($this->runtimeCacheKey != $tpl)
		{
			$this->runtimeCacheKey = $tpl;
			if(file_exists($this->path.$tpl.'.tpl')){
				$this->output = file_get_contents($this->path.$tpl.'.tpl');
			} else{$this->output = $tpl;}
		}

		$output = $this->output;

		if(!empty($tvs))
			foreach($tvs as $key => $val)
				$output = preg_replace('%\[\[( *)'.$key.'( *)\]\]%', $val, $output);

		if($clean) return preg_replace('%\[\[(.*)\]\]%', '', $output);

		return $output;
	}

	public function renderPagination(& $items, array $params = [], $argtpls = [])
	{
		$config = $this->imanager->config;

		$tpls['wrapper'] = !empty($argtpls['wrapper']) ? $argtpls['wrapper'] : 'pagination/wrapper';
		$tpls['prev'] = !empty($argtpls['prev']) ? $argtpls['prev'] : 'pagination/prev';
		$tpls['prev_inactive'] = !empty($argtpls['prev_inactive']) ? $argtpls['prev_inactive'] : 'pagination/prev_inactive';
		$tpls['central'] = !empty($argtpls['central']) ? $argtpls['central'] : 'pagination/central';
		$tpls['central_inactive'] = !empty($argtpls['central_inactive']) ? $argtpls['central_inactive'] : 'pagination/central_inactive';
		$tpls['next'] = !empty($argtpls['next']) ? $argtpls['next'] : 'pagination/next';
		$tpls['next_inactive'] = !empty($argtpls['next_inactive']) ? $argtpls['next_inactive'] : 'pagination/next_inactive';
		$tpls['ellipsis'] = !empty($argtpls['ellipsis']) ? $argtpls['ellipsis'] : 'pagination/ellipsis';
		$tpls['secondlast'] = !empty($argtpls['secondlast']) ? $argtpls['secondlast'] : 'pagination/secondlast';
		$tpls['second'] = !empty($argtpls['second']) ? $argtpls['second'] : 'pagination/second';
		$tpls['last'] = !empty($argtpls['last']) ? $argtpls['last'] : 'pagination/last';
		$tpls['first'] = !empty($argtpls['first']) ? $argtpls['first'] : 'pagination/first';

		// Determine start position (current page number)
		if(!empty($params['page'])) {
			//$page = (!empty($params['page']) ? $params['page'] : (isset($_GET['page']) ? (int) $_GET['page'] : 1));
			$page = $params['page'];
		} else {
			$page = ($this->imanager->input->pageNumber) ? $this->imanager->input->pageNumber : 1;
		}
		$pageurl = !empty($params['pageurl']) ? $params['pageurl'] : $config->pageNumbersUrlSegment;
		$params['count'] = !empty($params['count']) ? $params['count'] : count($items);

		$limit = !empty($params['limit']) ? $params['limit'] : (int) $config->maxItemsPerPage;
		$adjacents = !empty($params['adjacents']) ? $params['adjacents'] : 3;
		$lastpage = !empty($params['lastpage']) ? $params['lastpage'] : ceil($params['count'] / $limit);

		$slash =  !empty($params['trailingSlash']) ? '/' : '';

		$next = ($page+1);
		$prev = ($page-1);

		//$this->init();
		// only one page to show
		if($lastpage <= 1)
			return $this->render($tpls['wrapper'], array('value' => ''), true);

		$output = '';
		// $pageurl . '1'
		if($page > 1) { $output .= $this->render($tpls['prev'], array('href' => $pageurl . $prev . $slash), true); }
		else { $output .= $this->render($tpls['prev_inactive'], array(), true); }

		// not enough pages to bother breaking it up
		if($lastpage < 7 + ($adjacents * 2))
		{
			for($counter = 1; $counter <= $lastpage; $counter++)
			{
				if($counter == $page) {
					$output .= $this->render($tpls['central_inactive'], array('counter' => $counter), true);
				} else {
					// $pageurl . '1'
					$output .= $this->render($tpls['central'], array(
						'href' => ($counter > 1) ? $pageurl . $counter . $slash : $pageurl . '1' . $slash, 'counter' => $counter), true
					);
				}
			}
			// enough pages to hide some
		} elseif($lastpage > 5 + ($adjacents * 2))
		{
			// vclose to beginning; only hide later pages
			if($page < 1 + ($adjacents * 2))
			{
				for($counter = 1; $counter < 4 + ($adjacents * 2); $counter++)
				{
					if($counter == $page)
					{
						$output .= $this->render($tpls['central_inactive'], array('counter' => $counter), true);
					} else
					{
						$output .= $this->render($tpls['central'], array('href' => $pageurl . $counter . $slash,
							'counter' => $counter), true);
					}
				}
				// ...
				$output .= $this->render($tpls['ellipsis']);
				// sec last
				$output .= $this->render($tpls['secondlast'], array('href' => $pageurl . ($lastpage - 1) . $slash,
					'counter' => ($lastpage - 1)), true);
				// last
				$output .= $this->render($tpls['last'], array('href' => $pageurl . $lastpage . $slash,
					'counter' => $lastpage), true);
			}
			// middle pos; hide some front and some back
			elseif(($lastpage - ($adjacents * 2) > $page) && ($page > ($adjacents * 2)))
			{
				// first
				$output .= $this->render($tpls['first'], array('href' => $pageurl . '1' . $slash), true);
				// second
				$output .= $this->render($tpls['second'], array('href' => $pageurl . '2' . $slash, 'counter' => '2'), true);
				// ...
				$output .= $this->render($tpls['ellipsis']);

				for($counter = $page - $adjacents; $counter <= $page + $adjacents; $counter++)
				{
					if($counter == $page)
					{
						$output .= $this->render($tpls['central_inactive'], array('counter' => $counter), true);
					} else
					{
						$output .= $this->render($tpls['central'], array('href' => $pageurl . $counter . $slash,
							'counter' => $counter), true);
					}
				}
				// ...
				$output .= $this->render($tpls['ellipsis']);
				// sec last
				$output .= $this->render($tpls['secondlast'], array('href' => $pageurl . ($lastpage - 1) . $slash,
					'counter' => ($lastpage - 1)), true);
				// last
				$output .= $this->render($tpls['last'], array('href' => $pageurl . $lastpage . $slash,
					'counter' => $lastpage), true);
			}
			//close to end; only hide early pages
			else
			{
				// first ($pageurl . '1')
				$output .= $this->render($tpls['first'], array('href' => $pageurl . '1' . $slash), true);
				// second
				$output .= $this->render($tpls['second'], array('href' => $pageurl . '2' . $slash, 'counter' => '2'), true);
				// ...
				$output .= $this->render($tpls['ellipsis']);

				for($counter = $lastpage - (2 + ($adjacents * 2)); $counter <= $lastpage; $counter++)
				{
					if($counter == $page)
					{
						$output .= $this->render($tpls['central_inactive'], array('counter' => $counter), true);
					} else
					{
						$output .= $this->render($tpls['central'], array('href' => $pageurl . $counter . $slash,
							'counter' => $counter), true);
					}
				}
			}
		}
		//next link
		if($page < $counter - 1)
			$output .= $this->render($tpls['next'], array('href' => $pageurl . $next . $slash), true);
		else
			$output .= $this->render($tpls['next_inactive'], array(), true);

		return $this->render($tpls['wrapper'], array('value' => $output), true);
	}
}