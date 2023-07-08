<?php


defined('IS_IM') or die('You cannot access this page directly');

/**
 * Let's populate the main configuration variable with our theme 
 * specific configuration settings, so they are available from 
 * anywhere in our theme.
 */
return [

	/**
	 * The name of the website used throughout the Basic theme.
	 */
	'site_name' => \Scriptor\Core\Scriptor::getConfig('site_name'),

	/**
	 * The version of the Basic theme being used.
	 */
	'theme_version' => Themes\Basic\BasicTheme::VERSION,

	/**
	 * The copyright information for your website.
	 */
	'copyright_info' => '<a class="decent" href="https://ehret-studio.com">Ehret Studio</a>',

	/**
	 * The blog page configuration.
	 */
	'blog' => [
		/**
		 * The header text for the article list section.
		 */
		'article_list_header' => 'Latest Articles',

		/**
		 * The header text for the archive section (sidebar).
		 */
		'archive_header' => 'Archive',

		/**
		 * The header text for the about section (sidebar).
		 */
		'about_header' => 'About Us',

		/**
		 * The text content for the about section (sidebar).
		 */
		'about_text' => 'Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in.'
	],

	/**
	 * The contact page configuration.
	 */
	'contact_page' => [

		/**
		 * The label for the submit button on the contact page.
		 */
		'submit_button_label' => 'Send Message',

		/**
		 * The placeholder text for the message field on the contact page.
		 */
		'message_field_placeholder' => 'Message',

		/**
		 * The label for the message field on the contact page.
		 */
		'message_field_label' => 'Your Message',

		/**
		 * The placeholder text for the email field on the contact page.
		 */
		'email_field_placeholder' => 'Email',

		/**
		 * The label for the email field on the contact page.
		 */
		'email_field_label' => 'Your Email',

		/**
		 * The placeholder text for the name field on the contact page.
		 */
		'name_field_placeholder' => 'Name',

		/**
		 * The label for the name field on the contact page.
		 */
		'name_field_label' => 'Your Name'
	],

	/**
	 * Footer section configs
	 */
	'footer' => [
		
		/**
		 * The heading for the subscription section in the footer, inviting users to get news.
		 */
		'sub_heading' => 'Get news',

		/**
		 * The paragraph in the subscription section of the footer, prompting users to subscribe to the email newsletter.
		 */
		'sub_paragraph' => 'Subscribe to our email newsletter.',

		/**
		 * The heading for the middle section of the footer.
		 */
		'middle_heading' => 'Basic Theme',

		/**
		 * The paragraph in the middle section of the footer.
		 */
		'middle_paragraph' => 'Scriptor offers the Basic theme, a theme that allows admins to create their content instantly. This theme is intended for demonstration purposes, if you want to give your website its own look, just create a custom theme.',

		/**
		 * The label for the button in the footer used for submitting forms or actions.
		 */
		'submit_button_label' => 'Submit'

	],

	/**
	 * Container page id used to store the blog posts
	 */
	'articles_page_id' => 2,

	/**
	 * A page that represents a container for pages to display in the footer menu.
	 */
	'footer_container_id' => 8,

	/**
	 * The number of articles to be displayed on a page before pagination.
	 */
	'articles_per_page' => 3,

	/**
	 * Main navigation
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
	 * or change to a static value e.g. 262974383
	 */
	'markup_cache_time' => \Scriptor\Core\Scriptor::getProperty('config')['markup_cache_time'],

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
	 * If you must use time zones, e.g., 'Europe/Helsinki'
	 */
	'datetime_zone' => '',

	/**
	 * DateTimeFormat
	 */
	'datetime_format' => 'd F Y',

	/**
	 * Article summary character length
	 */
	'summary_character_len' => 400,

	/**
	 * Social media icons/links
	 */
	'social_media' => [
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
		* Specify the name of the module that will be used to send emails.
		* Use 'default' or leave it empty ('') to use the mail() function.
		*/
		'module_name' => 'default',

		/**
		 * Subject for the contact form email
		 */
		'subject_contact' => 'Scriptor Demo Page Contact',

		/**
		 * The email address from which the email will be sent.
		 * Will be set by the contact form.
		 */
		'email_from' => '',

		/**
		 * The name associated with the email address from which the email will be sent.
		 * Will be set by the contact form.
		 */
		'email_from_name' => '',

		/**
		 * The email address to which the contact form email will be sent
		 */
		'email_to' => 'your@mail.com',

		/**
	 	 * The name associated with the email address to which the contact form email will be sent
	 	 */
		'email_to_name' => 'Your Name'
	],

	/**
	 * The MailChimp API configurations
	 * 
	 * You can find all the information in your MailChimp account.
	 * More info: https://mailchimp.com/en/help/about-api-keys/
	 */
	'mail_chimp' => [
		'username' => '*******',
		'api_key' => '*******************',
    	'dc' => '****',
    	'list_id' => '*******'
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
		'csrf_token_mismatch' =>  'Invalid or missing CSRF token. Please reload the page and try again.',

		'empty_from_field' => 'The provided email address is invalid.',

		'empty_email_field' => 'Please enter your email address.',

		'empty_mandatory_fields' => 'All fields must be completed.',

		'error_sending_email' => 'Error sending email.',

		'email_received' =>  'We have received your email and will respond soon.',

		'no_articles_found' => 'No articles found',

		'subsc_email_exists' => 'Your email address is already subscribed.',

		'subsc_email_header' => 'Awesome',

		'subsc_email_confirmation' => 'One last thing - we have sent you a confirmation email to [[EMAIL]]. Please confirm your Scriptor CMS newsletter subscription.',

		'subsc_faild' => 'Failed to add your email address to our newsletter mailing list.'
	]

	//'recent_post_date_format' => 'M j, Y',
	//'num_of_recent_posts' => 3
];
