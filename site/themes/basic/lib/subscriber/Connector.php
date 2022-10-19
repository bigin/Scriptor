<?php
namespace Themes\Basic\Subscriber;


/**
 * A simple API connection class
 * 
 * Initializes a cURL connection to the MailChimp API
 * 
 */
class Connector
{
    /**
     * HTTP message body
     */
    private static $response;

    /**
     * HTTP response status code
     */
    private static $httpcode;

    /**
     * cURL error
     */
    private static $error;

    /**
     * An array of the cURL options
     */
    private static $ch_options;

	/**
	 * Executes cURL action and returns the results and HTTP code.
     * 
     * @return array 
	 */
	public static function execute(Request $request)
	{
        self::$ch_options = $request->build();
        self::execCurl();
        return [
            'code' => self::$httpcode,
            'response' => self::$response
        ];
    }

    public static function getCurlError()
    {
        return self::$error;
    }

	private static function execCurl()
	{
		$ch = curl_init();
		curl_setopt_array($ch, self::$ch_options);
		self::$response = curl_exec($ch);
        self::$httpcode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        if(curl_errno($ch)) {
            self::$error = curl_error($ch);
        }
		curl_close($ch);
    }
    
}