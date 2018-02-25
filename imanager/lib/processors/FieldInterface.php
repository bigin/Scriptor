<?php namespace Imanager;

interface FieldInterface
{
	const CUSTOM_PREFIX = 'custom-';

	public function render();

	public function getConfigFieldtype();
}