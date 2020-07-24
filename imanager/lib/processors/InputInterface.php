<?php namespace Imanager;

interface InputInterface
{
	const        EMPTY_REQUIRED = -1;

	const        ERR_MIN_LENGTH = -2;

	const        ERR_MAX_LENGTH = -3;

	const    WRONG_VALUE_FORMAT = -4;

	const     COMPARISON_FAILED = -5;

	const UNDEFINED_CATEGORY_ID = -6;

	public function __construct(Field $field);

	public function prepareInput($value, $sanitize = false);

	public function prepareOutput();
}