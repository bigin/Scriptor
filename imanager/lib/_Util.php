<?php namespace Imanager;

class Util
{
	/**
	 * Build ItemManager configuration
	 *
	 * @return Config object
	 */
	public static function buildConfig(): Config
	{
		$config = new Config();
		include(IM_ROOTPATH.'imanager/inc/config.php');
		if(file_exists(IM_SETTINGSPATH.'custom.config.php')) { include(IM_SETTINGSPATH.'custom.config.php'); }
		if($config->debug) { error_reporting(E_ALL); }
		else { error_reporting(0); }
		if($config->imErrorHandler) {
			set_error_handler(__NAMESPACE__.'\Util::imErrorHandler');
			register_shutdown_function(__NAMESPACE__.'\Util::imShutdownErrorHandler');
		}
		return $config;
	}

	/**
	 * Stores the passed string "$data" in the log file "$file".
	 *
	 * @param $data
	 * @param string $file
	 */
	public static function dataLog($data, $file = '')
	{
		$filename = empty($file) ? IM_LOGPATH.'imlog_'.date('Ym').'.txt' : IM_LOGPATH.$file.'.txt';
		if(!file_exists($filename)) { self::install($filename);}
		if(!$handle = fopen($filename, 'a+')) { return; }
		$datum = date('d.m.Y H:i:s', time());
		if(!fwrite($handle, '[ '.$datum.' ]'. ' ' . print_r($data, true) . "\r\n")) { return; }
		fclose($handle);
		chmod($filename, 0644);
	}

	/**
	 * Just a simple preformat method
	 *
	 * @param $data
	 */
	public static function preformat($data, $return = false)
	{
		if(!$return) echo '<pre>'.print_r($data, true).'</pre>';
		else return '<pre>'.print_r($data, true).'</pre>';
	}


	/**
	 * Recursive creating directories
	 *
	 * @param $path
	 */
	public static function install($path)
	{
		if(!file_exists(dirname($path)) && !mkdir(dirname($path), imanager('config')->chmodDir, true)) {
			self::logException(new \ErrorException('Unable to create path: '.dirname($path)));
		}
	}

	/**
	 * Recursive deleting a directory
	 *
	 * @param $dir
	 *
	 * @return bool
	 */
	public static function delTree($dir)
	{
		if(!file_exists($dir)) {return false;}
		$files = array_diff(scandir($dir), array('.','..'));
		foreach ($files as $file) {
			(is_dir("$dir/$file") && !is_link($dir)) ? self::delTree("$dir/$file") : unlink("$dir/$file");
		}
		return rmdir($dir);
	}

	/**
	 * Check the PHP_INT_SIZE constant. It'll vary based on the size of the register (i.e. 32-bit vs 64-bit)
	 * In 32-bit systems PHP_INT_SIZE should be 4, for 64-bit it should be 8
	 *
	 * @return int
	 */
	public static function getIntSize() {return PHP_INT_SIZE;}

	/**
	 * Function to compute the unsigned crc32 value.
	 * PHP crc32 function returns int which is signed, so in order to get the correct crc32 value
	 * we need to convert it to unsigned value.
	 *
	 * NOTE: it produces different results on 64-bit compared to 32-bit PHP system
	 *
	 * @param $str - String to compute the unsigned crc32 value.
	 * @return $var - Unsinged inter value.
	 */
	public static function computeUnsignedCRC32($str)
	{
		sscanf(crc32($str), "%u", $var);
		return $var;
	}

	/**
	 * Method to create a random token.
	 * We use it in order to prevent CSRF attack.
	 *
	 * @param int $length
	 *
	 * @return string
	 * @throws \Exception
	 */
	public static function randomToken($length = 32)
	{
		if(!isset($length) || intval($length) <= 8 ) {
			$length = 32;
		}
		if(function_exists('random_bytes')) {
			return bin2hex(random_bytes($length));
		}
		if(function_exists('mcrypt_create_iv')) {
			return bin2hex(mcrypt_create_iv($length, MCRYPT_DEV_URANDOM));
		}
		if(function_exists('openssl_random_pseudo_bytes')) {
			return bin2hex(openssl_random_pseudo_bytes($length));
		}
	}

	/**
	 * A method for performing various redirects
	 *
	 * @param $url
	 * @param bool $flag
	 * @param int $statusCode
	 */
	public static function redirect($url, $flag = true, $statusCode = 303)
	{
		header('Location: ' . htmlspecialchars($url), $flag, $statusCode);
		die();
	}

	/**
	 * Just a simple method for creating backups of categories, fields and items
	 *
	 * @param $path
	 * @param $file
	 * @param $suffix
	 * @param $stamp - timestamp (optionally)
	 *
	 * @return bool
	 */
	public static function createBackup($path, $file, $suffix, $stamp = null)
	{
		if(!file_exists($path.$file.$suffix)) return false;
		$s = (!$stamp) ? time() : $stamp;
		if(!copy($path.$file.$suffix, IM_BACKUPPATH.'backup_'.$s.'_'.$file)) return false;
		chmod(IM_BACKUPPATH.'backup_'.$s.'_'.$file, imanager('config')->chmodFile);
		self::deleteOutdatedBackups();
		return true;
	}

	/**
	 * This method checks and deletes outdated backups
	 */
	public static function deleteOutdatedBackups()
	{
		$min_days = (int) imanager('config')->minBackupTimePeriod;
		foreach(glob(IM_BACKUPPATH.'backup_*_*') as $file) {
			if(self::isCacheFileExpired($file, $min_days)) { self::removeFilename($file);}
		}
	}

	/**
	 * Is the given backup filename expired?
	 *
	 * @param string $filename
	 * @return bool
	 *
	 */
	protected static function isCacheFileExpired($filename, $min_days)
	{
		if(!$mtime = @filemtime($filename)) return false;
		if(($mtime + (60 * 60 * 24 * $min_days)) < time()) {
			return true;
		}
		return false;
	}

	/**
	 * Removes the given file
	 *
	 * @param string $filename
	 *
	 */
	protected static function removeFilename($filename){@unlink($filename);}

	/**
	 * Removes dated temporary directories
	 *
	 * @return bool
	 */
	public static function cleanUpTempContainers()
	{
		if(!file_exists(IM_UPLOADPATH)) return false;
		$tp = (int) imanager('config')->tmpFilesCleanPeriod;
		foreach(glob(IM_UPLOADPATH.'.tmp_*_*') as $file)
		{
			$base = basename($file);
			$strp = explode('_', $base);
			// wrong file name, continue
			if(count($strp) < 3) continue;
			$storagetime =  time() - (60 * 60 * 24 * $tp);
			if($strp[1] < $storagetime && $storagetime > 0) { self::delTree($file); }
		}
	}

	/**
	 * Use isOpCacheEnabled() method to determine if OPcache is enabled.
	 *
	 * The method uses php function "opcache_get_status" to get specific
	 * information about current cache usage.
	 *
	 * @return bool
	 *
	 */
	public static function isOpCacheEnabled()
	{
		if(function_exists('opcache_invalidate')
			&& (isset(opcache_get_status(false)['opcache_enabled']) && 
				@opcache_get_status(false)['opcache_enabled'] == 1)) { return true; }
		return false;
	}

	/**
	 * Invalidates a cached script.
	 *
	 * If Opcache is enabled, it should not be used for the include,
	 * because ItemManager must always utilize uncached data.
	 *
	 * @param $path
	 */
	public static function clearOpCache($path)
	{
		if(function_exists('opcache_invalidate') && strlen(ini_get('opcache.restrict_api')) < 1) {
			opcache_invalidate($path, true);
		} elseif (function_exists('apc_compile_file')) {
			apc_compile_file($path);
		}
	}

	/**
	 * ItemManager internal error handler
	 *
	 * To trigger the ERROR/WARNING/NOTICE messages, use trigger_error() function:
	 *     trigger_error('Object type is unknown', E_USER_WARNING);
	 *
	 *     E_USER_NOTICE             // Notice (default)
	 *     E_USER_WARNING            // Warning
	 *     E_USER_ERROR              // Error
	 * ------------------------------------------------------------
	 * @param $number
	 * @param $string
	 * @param $file
	 * @param $line
	 *
	 * @return bool
	 */
	public static function imErrorHandler($number, $string, $file, $line)
	{
        // Determine if this error is one of the enabled ones in php config (php.ini, .htaccess, etc)
        $error_is_enabled = (bool)($number & ini_get('error_reporting'));
		
		self::dataLog($number);
        // DISABLED by @ e.g. @your_function() ...
        if(error_reporting() === 0) return;

		// -- FATAL ERROR
		// throw an Error Exception, to be handled by whatever Exception handling logic is available in this context
		if(in_array($number, array(E_USER_ERROR, E_RECOVERABLE_ERROR)) && $error_is_enabled) {
			self::dataLog("Type: FATAL ERROR; $string in $file on line $line");
			throw new \ErrorException($string, 0, $number, $file, $line);
		}

		// -- NON-FATAL ERROR/WARNING/NOTICE
		// Log the error if it's enabled, otherwise just ignore it
		else if($error_is_enabled) {
			error_log($string, 0 );
			self::dataLog("Type: WARNING/NOTICE; $string in $file on line $line");
			return false;
		}

		// -- DISABLED ERRORS/WARNINGS, just write internal log
		else {
			self::dataLog("Type: WARNINGS; $string in $file on line $line");
		}
	}

	public static function imShutdownErrorHandler(): void
	{
		$err = error_get_last();
		if(! is_null($err)) {
			self::logException(new \ErrorException($err['message'], $err['type'], 1, $err['file'], $err['line']));
		}
	}

	/**
	 * This method is used to log and show ItemManager internal exceptions
	 *
	 * @param \Exception $e
	 */
	public static function logException(\Exception $e): void
	{
		$error_is_enabled = (bool)(ini_get('error_reporting'));

		if($error_is_enabled) {
			print "<div style='text-align: center; font-family: monospace;'>";
			print "<h2 style='color: rgb(190, 50, 50);'>Exception Occured:</h2>";
			print "<table style='text-align: left; display: inline-block;'>";
			print "<tr style='background-color:rgb(230,230,230);'><th style='width: 80px;'>Type</th><td>".self::humanErrorType($e->getCode())."</td></tr>";
			print "<tr style='background-color:rgb(240,240,240);'><th>Message</th><td>{$e->getMessage()}</td></tr>";
			print "<tr style='background-color:rgb(230,230,230);'><th>File</th><td>{$e->getFile()}</td></tr>";
			print "<tr style='background-color:rgb(240,240,240);'><th>Line</th><td>{$e->getLine()}</td></tr>";
			print "</table><p>This error message was shown because site is in debug mode (\$config->debug = true;). Error has been logged</p>";
			print "<h3 style='color: rgb(190, 50, 50);'>Trace:</h3>";
			print "<table style='text-align: left; display: inline-block;'>";
			print "<tr style='background-color:rgb(230,230,230);'><td>".nl2br($e->getTraceAsString())."</td></tr>";
			print "</table>";
		}

		$message = "Type: ".self::humanErrorType($e->getCode())."; Message: {$e->getMessage()}; File: {$e->getFile()}; Line: {$e->getLine()};";
		self::dataLog($message);
		exit();
	}

	/**
	 * Returns user-friendly error code.
	 * 
	 * @param integer $type
	 * 
	 * @return string 
	 */
	public static function humanErrorType($type): string
	{
		switch($type) {
			case E_ERROR: // 1
				return 'E_ERROR';
			case E_WARNING: // 2
				return 'E_WARNING';
			case E_PARSE: // 4
				return 'E_PARSE';
			case E_NOTICE: // 8
				return 'E_NOTICE';
			case E_CORE_ERROR: // 16
				return 'E_CORE_ERROR';
			case E_CORE_WARNING: // 32
				return 'E_CORE_WARNING';
			case E_COMPILE_ERROR: // 64
				return 'E_COMPILE_ERROR';
			case E_COMPILE_WARNING: // 128
				return 'E_COMPILE_WARNING';
			case E_USER_ERROR: // 256
				return 'E_USER_ERROR';
			case E_USER_WARNING: // 512
				return 'E_USER_WARNING';
			case E_USER_NOTICE: // 1024
				return 'E_USER_NOTICE';
			case E_STRICT: // 2048
				return 'E_STRICT';
			case E_RECOVERABLE_ERROR: // 4096
				return 'E_RECOVERABLE_ERROR';
			case E_DEPRECATED: // 8192
				return 'E_DEPRECATED';
			case E_USER_DEPRECATED: // 16384
				return 'E_USER_DEPRECATED';
		}
		return '';
	}
}