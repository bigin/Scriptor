<?php namespace Imanager;

class InputFileupload implements InputInterface
{
	protected $value;

	protected $field;

	protected $itemid;

	protected $tmpDir = null;

	protected $timestamp;

	public $errorCode = null;

	public $errorBuffer = array();

	public function __construct(Field $field)
	{
		$this->field = $field;
		$this->timestamp = time();
	}

	public function __set($name, $value) { $this->$name = $value; }

	public function prepareInput($value, $sanitize = false)
	{
		if(!is_array($value) || !is_array($value['file'])) {
			$this->remove(null);
			$this->value = null;
			Util::cleanUpTempContainers();
			return true;
		}

		$categoryid = (int) $this->field->categoryid;
		$itemid = $this->itemid;

		// It might be a new item?
		if($itemid && !empty($value['timestamp'])) {
			// 'uploads/.tmp_'.$timestamp.'_'.$categoryid.'.'.$fieldid.'/'
			$stamp = (int) $value['timestamp'];
			if(file_exists(IM_UPLOADPATH.".tmp_{$stamp}_{$categoryid}.{$this->field->id}") &&
				!file_exists(IM_UPLOADPATH."$categoryid.$itemid.{$this->field->id}")) {
				/*@mkdir(dirname(IM_UPLOADPATH."$categoryid.$itemid.{$this->field->id}"),
					imanager('config')->chmodDir, true);*/
				if(!rename(IM_UPLOADPATH.".tmp_{$stamp}_{$categoryid}.{$this->field->id}",
					IM_UPLOADPATH."$categoryid.$itemid.{$this->field->id}")) {
					trigger_error('Moving and renaming failed', E_USER_WARNING);
				}
			}
		}

		$newNames = array();
		// Check outside coming data for correctness
		foreach($value['file'] as $pos => $file) {
			if(!$this->field->categoryid) {
				$this->errorCode = self::UNDEFINED_CATEGORY_ID;
				return false;
			}
			settype($pos, 'integer');
			$this->value[$pos] = new FileuploadFieldValue();
			// Item id isn't empty
			if($itemid) {
				if(file_exists(IM_UPLOADPATH."$categoryid.$itemid.{$this->field->id}/$file")) {
					$this->value[$pos]->set('name', $file);
					$this->value[$pos]->set('path', IM_SITEROOT."uploads/$categoryid.$itemid.{$this->field->id}/");
					$this->value[$pos]->set('title', isset($value['title'][$pos]) ?
						(($sanitize) ? $this->sanitize($value['title'][$pos]) : $value['title'][$pos]) : '');
					$this->value[$pos]->set('position', $pos);

					$newNames[] = $file;
				}

			}
		}

		usort($this->value, array($this, 'sortObjects'));

		// The remaining files should be deleted now
		foreach(glob(IM_UPLOADPATH."$categoryid.$itemid.{$this->field->id}/*") as $file) {
			if(is_dir($file)) continue;
			$base = basename($file);
			if(!in_array($base, $newNames)) {
				// Remove all resized images
				foreach(glob(IM_UPLOADPATH."$categoryid.$itemid.{$this->field->id}/thumbnail/*x*_$base") as $resized) {
					$this->remove($resized);
				}
				// Remove thumbnail
				$this->remove(IM_UPLOADPATH."$categoryid.$itemid.{$this->field->id}/thumbnail/$base");
				// Remove original image
				$this->remove($file);
			}
		}

		Util::cleanUpTempContainers();

		return true;
	}


	protected function remove($file)
	{
		$categoryid = (int) $this->field->categoryid;
		$itemid = $this->itemid;
		if(!$file) {
			if(file_exists(IM_UPLOADPATH."$categoryid.$itemid.{$this->field->id}")) {
				Util::delTree(IM_UPLOADPATH."$categoryid.$itemid.{$this->field->id}");
			}
		} else {
			if(file_exists($file)) {
				@unlink($file);
			}
		}
		return true;
	}


	public function prepareOutput(){return $this->value;}


	protected function sanitize($value) { return imanager('sanitizer')->text($value); }


	private function sortObjects($a, $b)
	{
		$a = $a->position;
		$b = $b->position;

		if($a == $b) {return 0;}
		else
		{
			if($b > $a) {return -1;}
			else {return 1;}
		}
	}
}