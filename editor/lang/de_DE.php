<?php
$i18n = [
	'pre_delete_msg' => 'Sind Sie sicher, dass Sie diese Seite löschen wollen?',
	'dashboard_menu' => 'Dashboard',
	'pages_menu' => 'Seiten',
	'pages_edit_menu' => 'Bearbeiten',
	'pages_create_menu' => 'Estellen',
	'settings_menu' => 'Einstellungen',
	'page_successful_removed' => 'Die Seite wurde erfolgreich gelöscht.',
	'error_deleting_page' => 'Fehler beim Löschen der Seite.',
	'no_page' => 'Es wurde keine Seite gefunden.',
	'settings_page_text' => 'Die Einstellungen finden Sie unter <mark>data/settings/scriptor-config.php</mark>',
	'profile_menu' => 'Profil',
	'error_login' => 'Die von Ihnen angegebenen Zugangsdaten waren nicht korrekt.',
	'username_label' => 'Benutzername',
	'password_label' => 'Kennwort',
	'new_password_label' => 'Neues Kennwort',
	'password_confirm_label' => 'Kennwort wiederholen',
	'email_label' => 'E-Mail-Adresse',
	'profile_editor_header' => 'Benutzerprofil',
	'login_header' => 'Login',
	'pages_header' => 'Seiten',
	'successful_login' => 'Sie wurden erfolgreich eingeloggt.',
	'profile_incomplete' => 'Bitte füllen Sie alle Pflichtfelder aus.',
	'short_password' => 'Ihr Kennwort ist zu kurz, bitte geben Sie ein neues Kennwort mit einer Länge von mindestens 6 Zeichen ein.',
	'error_password_comparison' => 'Ihr Kennwort und Kennwort-Wiederholung stimmen nicht überein.',
	'profile_successful_saved' => 'Ihre Profildaten wurden erfolgreich gespeichert.',
	'create_button' => 'Erstellen',
	'logout_menu' => 'Logout',
	'position_table_header' => 'Pos',
	'id_table_header' => 'ID',
	'parent_table_header' => 'Parent',
	'title_table_header' => 'Titel',
	'delete_table_header' => 'Löschen',
	'save_button' => 'Speichern',
	'login_button' => 'Login',
	'view_button' => 'Ansehen',
	'content_label' => 'Inhalt',
	'title_label' => 'Titel',
	'parent_label' => 'Übergeordnete Seite',
	'published_label' => 'Veröffentlicht',
	'page_edit_header' => 'Seite bearbeiten',
	'page_create_header' => 'Neue Seite',
	'error_page_title' => 'Bitte geben Sie einen Seitentitel ein.',
	'error_page_title_exists' => 'Seite konnte nicht gespeichert werden, da bereits eine Seite mit demselben Titel existiert.',
	'error_page_content' => 'Feld Inhalt darf nicht leer sein.',
	'successful_saved_page' => 'Die Seite wurde erfolgreich gespeichert.',
	'parent_select_option' => 'Auswählen',
	'error_deleting_first_page' => 'Fehler beim Löschen von primären Seite. Die Seite mit der ID 1, kann nicht gelöscht werden.',
	'error_remove_parent_page' => 'Fehler beim Löschen der Seite, Sie können eine Seite mit untergeordneten Seiten nicht löschen.',
	'dashboard_content' => '
			<img src="images/dashboard-screen.png">
			<h1>Willkommen bei Scriptor</h1>
			<hr>
			<h3>Was ist Scriptor?</h3>
			<p>Scriptor ist ein einfaches Flat-File-CMS auf Basis von IManager, es wurde entwickelt, um 
			webbasierte Manuals, Anleitungen und anderen Online-Publikationen erstellen zu können. Scriptor unterstützt 
			Markdown und Syntaxhervorhebung.</p>
			
			<h3>Installationsanforderungen</h3>
			<ul>
				<li>Ein Unix- oder Windows-basierter Webserver, auf dem Apache ausgeführt wird.</li>
				<li>PHP 7 oder höher (vorzugsweise 7+). Es kann auch unter PHP 5.6 verwendet werden, wurde aber noch 
				nicht getestet.</li>
				<li>Das gesamte Verzeichnis <mark>data/</mark> mit Ausnahme des Ordners <mark>data/config/</mark> muss 
				beschreibbar sein.</li>
				<li>Das Apache-Modul <mark>mod_rewrite</mark> muss aktiviert sein.</li>
				<li>Der Apache muss die <mark>.htaccess</mark> unterstützen.</li>
			</ul>
			
			<h3>Scriptor aus einer Zip-Datei installieren</h3>
			<ol>
				<li>Gehe dazu auf die <a href="https://github.com/bigin/Scriptor/releases">Downloadseite</a> und lade 
				dir die letzte Scriptor Version herunter.</li>
				<li>Entpacke das Archiv und benenne das <mark>Scriptor-*</mark> Ordner nach Belieben um.</li>
				<li>Lade den Inhalt des Ordners auf deinen Server, oder lade den gesamten Ordner hoch, wenn du die 
				Anwendung in einem Unterordner ausführen möchtest.</li>
			</ol>
			
			<h3>Administrator</h3>
			<p>Nach der Installation erreichst du Administratorbereich deiner Scriptor-Website, indem du in die 
			Adresszeile deines Browsers <mark>editor/</mark> eingibst, Bsp:</p>
			
			<pre><code>https://your-website.com/editor/</code></pre>
			
			<p>Oder wenn du dein Scriptor in einem Unterordner installiert hast:</p>
			
			<pre><code>https://your-website.com/scriptor-directory/editor/</code></pre>
			
			<h4>Deine Zugangsdaten für den Adminbereich</h4>
			<p>User: <mark>admin</mark><br>
			Password: <mark>gT5nLazzyBob</mark></p>
			
			<p>Bitte ändere diese aus Sicherheitsgründen gleich nach der Installation.</p>
			
			<h3>Weitere Einstellungen</h3>
			<p>Alle anderen Einstellungen nimmst du direkt in der <mark>scriptor-config.php</mark> Datei vor, diese 
			befindet sich im <mark>data/settings/</mark> Verzeichnis.</p>
			
			<p><br><i>Viel Spaß mit Scriptor!</i></p>
	'
];