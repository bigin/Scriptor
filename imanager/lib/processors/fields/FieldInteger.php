<?php namespace Imanager;

class FieldInteger extends FieldText implements FieldInterface
{
	/**
	 * @var boolean - Field size attribute
	 */
    public $isUnsigned = false;
}