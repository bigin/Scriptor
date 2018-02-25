<?php namespace Imanager;

class FileuploadFieldValue
{

	public function __get($name) {
		if($name == 'url') {
			return isset($this->path) ? $this->path.$this->name : null;
		}
	}

	/**
	 * We use this method to prevent the setting of illegal attributes
	 *
	 * @param $name
	 * @param $value
	 */
	public function __set($name, $value) {
		if(in_array($name, array('name', 'path', 'title', 'position'))) {
			$this->$name = $value;
		}
	}

	/**
	 * Setting standard attributes
	 *
	 * @param $key
	 * @param $value
	 */
	public function set($key, $value) { $this->{$key} = $value; }

	/**
	 * This static method is called for complex field values
	 *
	 * @param $an_array
	 *
	 * @return PasswordFieldValue object
	 */
	public static function __set_state($an_array)
	{
		$_instance = new FileuploadFieldValue();
		foreach($an_array as $key => $val) {
			if(is_array($val)) $_instance->{$key} = $val;
			else $_instance->{$key} = $val;
		}
		return $_instance;
	}

	/**
	 * Returns a scaled image
	 *
	 * @param int $width
	 * @param int $height
	 * @param string $type
	 *
	 * @return FileuploadFieldValue
	 */
	public function resize($width = 0, $height = 0, $quality = null, $type = 'resize')
	{
		$relpath = $this->path.$this->name;
		$dir = basename($this->path);

		if(!file_exists(IM_UPLOADPATH.$dir.'/thumbnail/'.$width.'x'.$height.'_'.$this->name)) {
			$path_parts = pathinfo(IM_UPLOADPATH.$dir.'/'.$this->name);
			if($quality && (@$path_parts['extension'] == 'png' ||
				@$path_parts['extension'] == 'jpeg' || @$path_parts['extension'] == 'jpg')) {
				if($path_parts['extension'] != 'png') {
					$option = array('jpegQuality' => (int) $quality);
				} else {
					$option = array('pngQuality' => (int) $quality);
				}
				$thumb = \PhpThumbFactory::create(IM_UPLOADPATH.$dir.'/'.$this->name, $option);
			} else {
				$thumb = \PhpThumbFactory::create(IM_UPLOADPATH.$dir.'/'.$this->name);
			}
			$thumb->{$type}($width, $height);
			$thumb->save(IM_UPLOADPATH.$dir.'/thumbnail/'.$width.'x'.$height.'_'.$this->name, @$path_parts['extension']);
		}
		//$resized = clone $this;
		//$resized->urls = $this->path.'thumbnail/'.$width.'x'.$height.'_'.$this->name;
		return $this->path.'thumbnail/'.$width.'x'.$height.'_'.$this->name;
	}

}
