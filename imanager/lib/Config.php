<?php namespace Imanager;

class Config
{
	public $scriptUrl;

	public $url;
	/**
	 * Provides direct reference access to set values in the $data array
	 *
	 * @param string $key
	 * @param mixed $value
	 * return $this
	 *
	 */
	public function __set($key, $value) {
		$this->{$key} = $value;
	}

	public function getScriptUrl() {
		return ($this->scriptUrl) ? $this->scriptUrl : $this->buildScriptUrl();
	}

	public function getUrl() {
		return ($this->url) ? $this->url : $this->buildUrl();
	}

	protected function buildUrl() {
		$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? 'https://' : 'http://';
		$path_parts = pathinfo(htmlentities($_SERVER['PHP_SELF'], ENT_QUOTES));
		//$path_parts = str_replace('/manager', "", $path_parts['dirname']);
		$port = ($p = $_SERVER['SERVER_PORT']) != '80' && $p != '443' ? ':'.$p : '';
		$this->url = $protocol.htmlentities($_SERVER['SERVER_NAME'], ENT_QUOTES).$port.rtrim($path_parts['dirname'], '/');
		return $this->url;
	}

	protected function buildScriptUrl()
	{
		$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? 'https://' : 'http://';
		//list($prot)	= explode('/',strtolower($_SERVER['SERVER_PROTOCOL']));
		$port = ($p = $_SERVER['SERVER_PORT']) != '80' && $p != '443' ? ':'.$p : '';
		$this->scriptUrl = $protocol.htmlentities($_SERVER['SERVER_NAME'], ENT_QUOTES).$port.htmlentities($_SERVER['REQUEST_URI'], ENT_QUOTES);
		return $this->scriptUrl;
	}

	/**
	 * Safely determine the HTTP host
	 *
	 * @param Config $config
	 * @return string
	 *
	 */
	public function getHttpHost()
	{
		$port = (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] != 80) ? (':' . ((int) $_SERVER['SERVER_PORT'])) : '';
		$host = '';
		if(isset($_SERVER['SERVER_NAME']) && $host = $_SERVER['SERVER_NAME']) {
			// no whitelist available, so defer to server_name
			$host .= $port;
		} else if(isset($_SERVER['HTTP_HOST']) && $host = $_SERVER['HTTP_HOST']) {
			// fallback to sanitized http_host if server_name not available
			// note that http_host already includes port if not 80
			$host = $_SERVER['HTTP_HOST'];
		}
		// sanitize since it did not come from a whitelist
		if(!preg_match('/^[-a-zA-Z0-9.:]+$/D', $host)) $host = '';

		return $host;
	}

}
?>