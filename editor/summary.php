<?php defined('IS_IM') or die('You cannot access this page directly'); ?>
<div class="summary-wrapper">
	<span class="close">×</span>
	<nav role="navigation">
		<div class="brand-wrapper">
			<a href="<?php echo $editor->pageUrl; ?>"><img alt="logo" src="<?php echo $editor->pageUrl; ?>images/logo.png"></a>
		</div>
		<?php if(isset($_SESSION['loggedin'])) { ?>
		<ul class="summary">
			<li class="chapter<?php echo (($imanager->input->urlSegments->get(0) == 'pages') ? ' active' : ''); ?>" data-level="1.1" data-path="./">
				<a href="<?php echo $editor->pageUrl; ?>pages/"><?php echo $editor->i18n['pages_menu']; ?></a>
			</li>
			<li class="chapter<?php echo (($imanager->input->urlSegments->get(0) == 'settings') ? ' active' : ''); ?>" data-level="1.1" data-path="./">
				<a href="<?php echo $editor->pageUrl; ?>settings/"><?php echo $editor->i18n['settings_menu']; ?></a>
			</li>
		</ul>
		<?php } ?>
	</nav>
</div>