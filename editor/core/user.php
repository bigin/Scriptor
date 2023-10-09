<?php

namespace Scriptor\Core;

use Imanager\Item;

/**
 * Namespace: Scriptor\Core
 * Class: User
 * Represents a user item in the Scriptor CMS.
 */
class User extends Item
{
	/**
	 * @var string $email - Represents an email address
	 */
	public string $email = '';

	/**
	 * @var string $role - Represents a role or permission level
	 */
	public string $role = '';

	
	public $password;

	/**
	 * @var array $fields - Can contain any number of fields or attributes related to the class
	 */
	public $fields = [];

	/**
	 * Constructor for the User class.
	 * 
	  * Auto set id of Scriptor's users category if the $category_id isn't specified.
	 * 
	 * @param null|int $category_id - Optional
	 */
	public function __construct(?int $category_id = null)
	{
		if (!$category_id) {
			$this->categoryid = imanager()->getCategory('name=Users')->id;
		} else {
			$this->categoryid = (int) $category_id;
		}
		parent::__construct($this->categoryid);
	}


	/**
	 * Intercepts the setting of the 'password' and 'password_confirmation' values, 
	 * because we define our own setting for these values here.
	 * 
	 * @param $name - Name of the fild
	 * @param $value - Value of the field
	 * @param bool $sanitize - (Optional)
	 */
	public function set($name, mixed $value, $sanitize = true) :?object
	{
		if ($name != 'password') {
			return parent::set($name, $value, $sanitize);
		}

		if ($name == 'password') {
			// let's first layer check
			is_array($value) OR throw new \ErrorException('The value of password/password_confirmation must be of type string.');
			parent::set($name, $value) OR throw new \ErrorException('Setting the password is not possible.');
		}

		$this->$name = $value;
		return $this;
	}

	/**
	 * Tries to save user, with a previous check and adjustment of particular 
	 * parameters.
	 * Required: name, password, password_confirmation attributes.
	 * 
	 * @throws ErrorException
	 * @return bool
	 */
	public function save() :bool
	{
		$sanitizer = imanager('sanitizer');
		$self = ($this->id) ? (new Users())->getUser((int) $this->id) : null;
		
		if (!$this->name) {
			throw new \ErrorException('The value of the name attribute is required');
		}
		$this->name = $sanitizer->text(str_replace('"', '', $this->name));

		if (!$self) {
			if (!$this->password) {
				throw new \ErrorException('The value of the password field is required');
			}
			if(! $this->password_confirmation) {
				throw new \ErrorException('The value of the password_confirmation field is required');
			}
		}

		// check username exists
		if ($this->userNameExists()) throw new \ErrorException('The name is already taken.');

		if ($this->password && is_array($this->password)) {
			if (mb_strlen($this->password['password']) < Scriptor::getProperty('config')['minPasswordLength']) {
				throw new \ErrorException('The value of the password field is too short.');
			}		
			if (!parent::set('password', ['password' => $this->password['password'], 'confirm_password' => $this->password['confirm_password']])) {
				throw new \ErrorException('The values of password and password_confirmation do not match');
			}
		}

		$this->label = ($this->label) ? $sanitizer->text($this->label) : '';
		$this->position  = (int) $this->position ?? $this->id;
		$this->active = (bool) $this->active ?? false;
		$this->email = ($this->email) ? $sanitizer->email($this->email) : '';
		$this->role = ($this->role) ? $sanitizer->text($this->role) : '';

		return parent::save();
	}

	/**
	 * Checks if the username already exists.
	 * @return bool
	 */
	private function userNameExists() :bool
	{
		$users = (new Users())->getUsers("name=$this->name");
		if (! $users) return false;
		foreach ($users as $user) {
			if (!$this->id || $this->id != $user->id) return true;
		}
		return false;
	}

	/**
	 * This static method is called for pages exported by var_export()
	 *
	 * @param $an_array
	 *
	 * @return User
	 */
	public static function __set_state($an_array) :User
	{
		$_instance = new User($an_array['categoryid']);
		foreach ($an_array as $key => $val) {
			if (is_array($val)) $_instance->{$key} = $val;
			else $_instance->{$key} = $val;
		}
		return $_instance;
	}
}