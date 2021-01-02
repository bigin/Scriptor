<?php namespace Imanager;

class FieldMapper extends Mapper
{
	/**
	 * @var array of the objects of type Field
	 */
	public $fields = array();

	/**
	 * @var int - Fields counter
	 */
	public $total = 0;

	/**
	 * @var string - Path of the buffered fields
	 */
	public $path = null;

	/**
	 * @var bool - An initialize flag for intern use
	 */
	private static $initialized = false;

	/**
	 * @var null|int - Current initialized category id
	 */
	private static $category_id = null;

	/**
	 * Initializes fields of a category.
	 *
	 * This method uses control structure to avoid unnecessary
	 * reinitialization.
	 *
	 * Since ItemManager v 3.1.1 it supports a force parameter
	 * to force initialization.
	 *
	 * @param int $category_id
	 * @param bool $force
	 *
	 * @return bool
	 */
	public function init(int $category_id, bool $force = false)
	{
		if(self::$initialized && !$force && self::$category_id === $category_id) return true;
		self::$category_id = $category_id;

		parent::___init();
		$this->path = IM_BUFFERPATH.'fields/'.(int) $category_id.'.fields.php';

		if(!file_exists(dirname($this->path))) {
			Util::install($this->path);
		}
		if(file_exists($this->path)) {
			(!Util::isOpCacheEnabled()) or Util::clearOpCache($this->path);
			$this->fields = include $this->path;
			if(is_array($this->fields)) {
				$this->total = count($this->fields);
			} else {
				$this->fields = array();
				$this->total = 0;
			}
			self::$initialized = true;
			return true;
		}
		unset($this->fields);
		$this->fields = null;
		$this->total = 0;

		return false;
	}

	public function countFields(array $fields=array())
	{$locfields = !empty($fields) ? $fields : $this->fields; return count($locfields);}

	/**
	 *
	 * @since 3.0
	 * @param $stat
	 * @param array $fields
	 *
	 * @return bool|mixed
	 */
	public function getField($stat, array $fields=array())
	{
		$locfields = !empty($fields) ? $fields : $this->fields;
		if(!is_array($locfields)) { return null; }
		// nothing to select
		if(empty($fields)) {
			if(!$this->countFields() || $this->countFields() <= 0) { return false; }
		}

		// only id is entered
		if(is_numeric($stat)) {
			foreach($locfields as $fieldkey => $field) {
				if((int) $field->id == (int) $stat) return $field;
			}
		}

		if(false !== strpos($stat, '='))
		{
			$data = explode('=', $stat, 2);
			$key = strtolower(trim($data[0]));
			$val = trim($data[1]);
			if(false !== strpos($key, ' ')) return false;

			// Searching for the field name
			if($key == 'name') return isset($locfields[$val]) ? $locfields[$val] : false;

			foreach($locfields as $fieldkey => $field) {
				foreach($field as $k => $v) {
					// looking for the field id
					if($key == 'id' && (int) $field->id == (int) $val) return $field;
					if($key == $k && $val == $v) return $field;
				}
			}
		} else {
			if(isset($locfields[$stat])) return $locfields[$stat];
		}
		return false;
	}


	/**
	 * A public method for sorting the fields
	 *
	 * You can sort fields by using any attribute
	 * Default sortng attribute is "position":
	 * FieldMapper::sort('position', 'DESC', $your_fields_array)
	 *
	 * @param string $filterby
	 * @param string $order
	 * @param array $fields
	 *
	 * @return boolean|array of Field objects
	 */
	public function sort($filterby = null, $order = 'asc', array $fields = array())
	{
		$localFields = !empty($fields) ? $fields : $this->fields;

		if(empty($localFields)) return false;

		$this->filterby = ($filterby) ? $filterby : $this->imanager->config->filterByFields;

		usort($localFields, array($this, 'sortObjects'));
		// Sort in DESCENDING order
		if(strtolower($order) != 'asc') $localFields = $this->reverseFields($localFields);
		// Reviese field ids
		$localFields = $this->reviseFieldIds($localFields);

		if(!empty($fields)) return $localFields;

		$this->fields = $localFields;

		return $this->fields;
	}


	/**
	 * Reverse the array of fields
	 *
	 * @param array $fieldcontainer An array of objects
	 * @return boolean|array
	 */
	private function reverseFields($fieldcontainer)
	{
		if(!is_array($fieldcontainer)) return false;
		return array_reverse($fieldcontainer);
	}


	/**
	 * Revise keys of the array of fields and changes these into real field Ids
	 *
	 * @param array $fieldcontainer An array of objects
	 * @return boolean|array
	 */
	private function reviseFieldIds($fieldcontainer)
	{
		if(!is_array($fieldcontainer)) return false;
		$result = array();
		foreach($fieldcontainer as $val) { $result[$val->name] = $val; }
		return $result;
	}


	/**
	 * Sorts the field objects by an attribut
	 *
	 * @param $a $b objects to be sorted
	 * @return boolean
	 */
	private function sortObjects($a, $b)
	{
		$a = $a->{$this->filterby};
		$b = $b->{$this->filterby};
		if(is_numeric($a))
		{
			if($a == $b) {return 0;}
			else
			{
				if($b > $a) {return -1;}
				else {return 1;}
			}
		} else {return strcasecmp($a, $b);}
	}

	/**
	 * Method deletes the respective Field object.
	 *
	 * Before that, it checks whether this element exists.
	 *
	 * @param Field $field
	 *
	 * @return bool
	 */
	public function remove(Field & $field, $complete = true)
	{
		$this->init($field->categoryid);
		if(!isset($this->fields[$field->name])) {
			trigger_error('Field object does not exist', E_USER_WARNING);
			return false;
		}
		unset($this->fields[$field->name]);
		// Create a backup if necessary
		if($this->imanager->config->backupFields){
			Util::createBackup(dirname($this->path).'/', basename($this->path, '.php'), '.php');
		}
		$categoryid = $field->categoryid;
		$id = $field->id;
		$export = var_export($this->fields, true);
		if(false !== file_put_contents($this->path, '<?php return ' . $export . '; ?>')) {
			@chmod($this->path, $this->imanager->config->chmodFile);
			$field = null;
			unset($field);
			// Now, prepare and re-save all the items of this category
			if($complete) {
				$this->imanager->itemMapper->rebuild($categoryid, array('removeAssets' => true, 'fieldId' => $id));
			} else {
				$this->imanager->itemMapper->rebuild($categoryid);
			}
			return true;
		}
		trigger_error('Field object could not be deleted', E_USER_WARNING);
		return false;
	}
}