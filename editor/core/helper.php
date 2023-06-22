<?php 
namespace Scriptor\Core;

class Helper 
{
	public static function isCallable($f) 
	{
		return (is_string($f) && function_exists($f)) || (is_object($f) && ($f instanceof \Closure));
	}

	/**
	 * Get the classname without the namespace
	 */
	public static function rawClassName($classname)
	{
		if ($pos = strrpos($classname, '\\')) return substr($classname, $pos + 1);
		return $pos;
	}

	/**
	 * Comparison function 
	 * used in sorting methods
	 * 
	 * @param array $a Operand
	 * @param array $b Operand
	 * 
	 * @return integer
	 */
	public static function order($a, $b)
	{
		if ($a['position'] == $b['position']) return 0;
		return ($a['position'] < $b['position']) ? -1 : 1;
	}

	/**
	 * Sends Json response and terminates the script execution.
	 *
	 * @param string|array $data - Data to send
	 */
	public static function sendJsonResponse($data = null, $code = null, $options = 0)
	{
		if ($code) {
			header('Access-Control-Allow-Headers: Authorization, Content-Type');
			http_response_code($code);
		}
		if ($data) {
			header('Access-Control-Allow-Headers: Authorization, Content-Type');
			header('Content-type: application/json; charset=utf-8');
			echo json_encode($data, $options);
		}
		exit;
	}

	/**
     * Extracts the namespace defined in a PHP file.
     *
     * @param string $file The path to the PHP file.
     * @return string|null The namespace defined in the file, or null if no namespace is defined.
     */
	public static function extractNamespace($file) 
	{
		$ns = null;
		$handle = fopen($file, "r");
		if ($handle) {
			while (($line = fgets($handle)) !== false) {
				if (strpos($line, 'namespace') === 0) {
					$parts = explode(' ', $line);
					$ns = rtrim(trim($parts[1]), ';');
					break;
				}
			}
			fclose($handle);
		}
		return $ns;
	}
}