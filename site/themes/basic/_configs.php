<?php

defined('IS_IM') or die('You cannot access this page directly');

/**
 * Let's populate the main configuration variable with our theme 
 * specific configuration settings, so they are available from 
 * anywhere in our theme.
 */

return [
	/**
	 * Container page id used to store the blog posts
	 */
	'articles_page_id' => 2,

	/**
	 * A page that represents a container for pages to display in 
	 * the footer menu.
	 */
	'footer_container_id' => 8,

	/**
	 * The number of how many articles should be displayed on a 
	 * page before the page splits.
	 */
	'articles_per_page' => 3,

	/**
	 * Main navi
	 * Enter IDs of the pages you want to exclude
	 */
	'main_nav_exclude_ids' => [
		8
	],

	/**
	 * An array with allowed user actions
	 */
	'allowed_actions' => [
		'contact',
		'loadToken',
		'subscribe'
	],

	/**
	 * See /data/settings/scriptor-config.php resp. custom.scriptor-config.php
	 * or change to a static value: 262974383
	 */
	'markup_cache_time' => \Scriptor\Scriptor::getProperty('config')['markup_cache_time'],

	/**
	 * Enter the templates of the pages where you want the output to be cached.
	 */
	'cacheable_templates' => [
		'blog-post',
		'contact',
		'default'
	],

	/**
	 * DateTimeZone
	 * If you must have use time zones eg. 'Europe/Helsinki'
	 */
	'datetime_zone' => '',

	/**
	 * DateTimeFormat
	 */
	'datetime_format' => 'd F Y',

	/**
	 * Article Summary character length
	 */
	'summary_character_len' => 400,

	/**
	 * Social media icons/links
	 */
	'soc' => [
		'facebook' => [
			'href' => 'https://facebook.com/your-link...'
		],
		'twitter' => [
			'href' => 'https://twitter.com/your-link...'
		]
	],

	/**
	 * Email configs
	 */
	'email' => [
		/**
		 * Email sending library
		 * 
		 * Enter the name of a module that will be used to send the email – or 
		 * leave it 'default' or empty '', if the mail() function should be used.
		 */
		'module_name' => 'default',

		/**
		 * Contact form subject
		 */
		'subject_contact' => 'Subject contact',

		/**
		 * The email address from which the email will be sent
		 * 
		 */
		'email_from' => 'juri.ehret@gmail.com',

		'email_from_name' => 'Website name - contact',

		'email_to' => 'juri.ehret@gmail.com',

		'email_to_name' => 'Website admin'
	],

	/**
	 * The MailChimp API configurations
	 * 
	 * All information you can find in your MailChimp account.
	 * More info: https://mailchimp.com/en/help/about-api-keys/
	 */
	'mail_chimp' => [
		'username' => 'Bigizmund',
		'api_key' => '1985a17d6d102dcc570ac4e8e87b64b8-us10',
    	'dc' => 'us10',
    	'list_id' => 'a48be57b78'
	],

	/**
	 * User messages 
	 */
	'msgs' => [
		/**
		 * This message means that your browser couldn’t create a secure cookie, 
		 * or couldn’t access that cookie to authorize your
		 * 
		 */
		'csrf_token_mismatch' => 'Invalid or missing CSRF token. Reload the page and try again.',

		'empty_from_field' => 'The email address provided is invalid.',

		'empty_email_field' => 'Please enter your e-mail address.',

		'empty_mandatory_fields' => 'All fields must be completed',

		'error_sending_email' => 'Error sending email.',

		'email_received' => 'We have received your email and we will answer you soon.',

		'no_articles_found' => 'No articles found',

		'subsc_email_exists' => 'Your email address is already subscribed with us.',

		'subsc_email_header' => 'Awesome',

		'subsc_email_confirmation' => 'One last thing – we have sent you a confirmation email to [[EMAIL]], please confirm your Scriptor CMS newsletter subscription.',

		'subsc_faild' => 'Failed to add your email address to our Newsletter mailing list.'
	]

	//'recent_post_date_format' => 'M j, Y',
	//'num_of_recent_posts' => 3
];
