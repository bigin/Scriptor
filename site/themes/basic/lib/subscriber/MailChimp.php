<?php

namespace Scriptor;

use Imanager\Util;

/**
 * MailChimp Class
 * 
 * Can be used to add new subscribers to the subscriber list 
 * or modify existing ones. 
 * 
 */
class MailChimp
{
	private $username;
	
	private $api_key;

	private $dc;

	private $list_id;

	public $code;

	public function __construct(array $params)
	{
		$this->username = $params['username'];
		$this->api_key = $params['api_key'];
		$this->dc = $params['dc'];
		$this->list_id = $params['list_id'];
	}

	public function getRequest() : Request
	{
		return new Request($this->username, $this->api_key);
	}

	/**
	 * Returns a subscriber with the passed email address.
	 * 
	 * @param string $email
	 */
	public function get(string $email)
	{
		$request = $this->getRequest();
        $request->set('baseurl', 'https://'.$this->dc.'.api.mailchimp.com')
            ->set('path', '/3.0/lists/'.$this->list_id.'/members/'.md5($email));

		return $this->parseResult(Connector::execute($request));
	}

	public function change(array $data)
	{
		$request = $this->getRequest();
		$request->set('params', \json_encode($data));
		$request->add('options', 'PATCH', CURLOPT_CUSTOMREQUEST);
		$request->set('baseurl', 'https://'.$this->dc.'.api.mailchimp.com')
				->set('path', '/3.0/lists/'.$this->list_id.'/members/'.md5($data['email_address']));
		Util::dataLog($request);
		return $this->parseResult(Connector::execute($request));
	}

	public function add(array $data)
	{
		$request = $this->getRequest();
		$request->set('params', \json_encode($data));
		$request->add('options', 'POST', CURLOPT_CUSTOMREQUEST);
		$request->set('baseurl', 'https://'.$this->dc.'.api.mailchimp.com')
				->set('path', '/3.0/lists/'.$this->list_id.'/members');

		return $this->parseResult(Connector::execute($request));
	}

	private function parseResult($result) 
	{
		$this->code = $result['code'];
        return json_decode($result['response'], true);
	}
}