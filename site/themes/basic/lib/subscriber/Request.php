<?php

namespace Scriptor;

class Request
{

    /**
     * API URL 
     */
    private $baseurl = '';

    /**
     * The path and resource name e.g: '/pages/my-page' or '/pages'
     * 
     * etc.
     */
    private $path = '';

    /**
     * POST/PATCH or PUT parameters
     */
    private $params = '';

    /**
     * Scriptor's API secret key
     */
    private $apiKey;

    /**
	 * CURL default options array
	 * @var array
	 */
	private $options = [
		CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => false,
        CURLOPT_HTTPHEADER => true,
		CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_FOLLOWLOCATION => true
        // Comment out next line on productive:
        //CURLOPT_SSL_VERIFYPEER => false
    ];

    public function __construct($username, $key)
    {
        $this->options[CURLOPT_USERPWD] = $username . ":" . $key;
    }

    public function build()
	{
        $this->options[CURLOPT_URL] = $this->baseurl.$this->path;

		if($this->options[CURLOPT_CUSTOMREQUEST] == 'POST') {
			$this->options[CURLOPT_POST] = true;
			$this->options[CURLOPT_POSTFIELDS] = $this->params;
		} elseif($this->options[CURLOPT_CUSTOMREQUEST] == 'PATCH') {
			//$this->options[CURLOPT_POST] = true;
			$this->options[CURLOPT_POSTFIELDS] = $this->params;
		} elseif($this->options[CURLOPT_CUSTOMREQUEST] == 'PUT') {
			//$this->options[CURLOPT_PUT] = true;
			$this->options[CURLOPT_POSTFIELDS] = $this->params;
        }

        $this->options[CURLOPT_HTTPHEADER] = ['Content-Type: application/json'];   
        return $this->options;
    }

    /**
     * Is used to fill arrays, e.g. $options
     * 
     * @var string $name - Array name e.g. $options.
     * @var mixed $value - Value to use for filling.
     * @var string|int $key - (optional) If no key is specified, it will be incremented automatically.
     * 
     * @return self|null
     */
    public function add($name, $value, $key = null)
    {
        if(property_exists($this, $name)) {
            if($key) $this->{$name}[$key] = $value;
            else $this->{$name}[] = $value;
            return $this;
        }
        return null;
    }

    /**
     * Used to fill the attributes
     * 
     * @var string $name - Attribute name
     * @var mixed $value - Value to use for filling.
     * 
     * @return self|null
     */
    public function set($name, $value) 
    {
        if(property_exists($this, $name)) {
            if($name == 'params') {
                $this->$name = (is_array($value)) ? http_build_query($value) : $value;
            } else {
                $this->$name = $value;
            }
            return $this;
        }
        return null;
    }

    /**
     * Retrieves one of the object attributes.
     * 
     * @var string $name - Attribute name to be returned
     * 
     * @return mixed|null
     */
    public function get($name)
    {
        if(property_exists($this, $name)) {
            return $this->$name;
        }
        return null;
    }
}
