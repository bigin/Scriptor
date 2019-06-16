<?php
$i18n = [
	'pre_delete_msg' => 'Are you sure you want to delete the page?',
	'dashboard_menu' => 'Dashboard',
	'pages_menu' => 'Pages',
	'pages_edit_menu' => 'Edit',
	'pages_create_menu' => 'New',
	'settings_menu' => 'Settings',
	'page_successful_removed' => 'The page was successfully deleted.',
	'error_deleting_page' => 'Error deleting the page.',
	'no_page' => 'No page found.',
	'settings_page_text' => 'The settings can be found under <mark>data/settings/scriptor-config.php</mark>',
	'profile_menu' => 'Profile',
	'error_login' => 'The login and password you entered are not correct.',
	'username_label' => 'Username',
	'password_label' => 'Password',
	'new_password_label' => 'New Password',
	'password_confirm_label' => 'Repeat password',
	'email_label' => 'Email address',
	'profile_editor_header' => 'User profile',
	'login_header' => 'Login',
	'pages_header' => 'Pages',
	'successful_login' => 'You have been successfully logged in.',
	'profile_incomplete' => 'Please fill in all required fields.',
	'short_password' => 'The password you specified is too short, please enter a new password with a length of at least 6 characters.',
	'error_password_comparison' => 'Password does not match the confirm password.',
	'profile_successful_saved' => 'Your profile data has been saved successfully.',
	'create_button' => 'Create',
	'logout_menu' => 'Logout',
	'position_table_header' => 'Pos',
	'id_table_header' => 'ID',
	'parent_table_header' => 'Parent',
	'title_table_header' => 'Title',
	'delete_table_header' => 'Delete',
	'save_button' => 'Save',
	'login_button' => 'Login',
	'view_button' => 'View',
	'content_label' => 'Content',
	'title_label' => 'Title',
	'parent_label' => 'Parent Page',
	'published_label' => 'Published',
	'page_edit_header' => 'Edit page',
	'page_create_header' => 'New page',
	'error_page_title' => 'Please enter a page title.',
	'error_page_title_exists' => 'Page could not be saved, a page with the specified title already exists.',
	'error_page_content' => 'The Content field is mandatory.',
	'successful_saved_page' => 'The page was saved successfully.',
	'parent_select_option' => 'Select option',
	'error_deleting_first_page' => 'Error when deleting primary page. The page with ID 1, cannot be deleted.',
	'error_remove_parent_page' => 'Error deleting the page, you cannot delete a page that contains children pages.',
	'dashboard_header' => '',
	'dashboard_content' => '
			<img src="images/dashboard-screen.png">
			<h1>Welcome to Scriptor</h1>
			<hr>
			<h3>What is Scriptor?</h3>
			<p>Scriptor is a simple flat-file CMS based on IManager. Specially developed for publishing web-based 
			manuals, instructions and other online publications, supports Markdown and Syntax highlighting.</p>
			
			<h3>Install Requirements</h3>
			<ul>
				<li>A Unix or Windows-based web server running Apache.</li>
				<li>PHP 7 or newer (7+ preferable). However, it will also work with PHP 5.6, but it has not been tested 
				yet.</li>
				<li>Write permission has to be granted into the complete <mark>data/</mark> directory except 
				<mark>data/config/</mark> folder.</li>
				<li>Apache must have <mark>mod_rewrite</mark> enabled.</li>
				<li>Apache must support <mark>.htaccess</mark> file.</li>
			</ul>
			
			<h3>Installing from zip</h3>
			<ol>
				<li>Go to the <a href="https://github.com/bigin/Scriptor/releases">download</a> page and download the 
				latest version of Scriptor.</li>
				<li>Unzip the archive and rename the <mark>Scriptor-*</mark> folder to your liking.</li>
				<li>Upload the contents of the folder to your server, or upload the folder if you want to run the 
				application in a subfolder.</li>
			</ol>
			
			<h3>Admin</h3>
			<p>Once installed, to access the administrator area of your Scriptor site go to your websites homepage, then 
			simply add the text <mark>editor/</mark> to the URL in your browsers, for example:</p>
			
			<pre><code>https://your-website.com/editor/</code></pre>
			
			<p>If you are using Scriptor in a subdirectory:</p>
			
			<pre><code>https://your-website.com/scriptor-directory/editor/</code></pre>
			
			<h4>Your access data for the admin</h4>
			<p>User: <mark>admin</mark><br>
			Password: <mark>gT5nLazzyBob</mark></p>
			
			<p>For security reasons, please change these immediately after installation.</p>
			
			<h3>Other settings</h3>
			<p>All further settings are made in the <mark>scriptor-config.php</mark> file, which is located in the 
			<mark>data/settings/</mark> directory.</p>
			
			<p><br><i>Enjoy your Scriptor!</i></p>
	'
];