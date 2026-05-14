<?php

namespace Themes\Basic\Subscriber;

/**
 * MailChimp Class
 *
 * Can be used to add new subscribers to the subscriber list
 * or modify existing ones.
 */
class MailChimp
{
	private string $username;
	private string $api_key;
	private string $dc;
	private string $list_id;

	public ?int $code = null;

	/**
	 * @param array<string, mixed> $params
	 */
	public function __construct(array $params)
	{
		$this->username = (string) ($params['username'] ?? '');
		$this->api_key  = (string) ($params['api_key']  ?? '');
		$this->dc       = (string) ($params['dc']       ?? '');
		$this->list_id  = (string) ($params['list_id']  ?? '');
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
