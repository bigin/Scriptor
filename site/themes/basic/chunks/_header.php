<?php defined('IS_IM') or die('You cannot access this page directly'); ?>
<header class="uk-container">
	<nav class="uk-navbar uk-navbar-container uk-margin uk-navbar-transparent">
		<div class="uk-navbar-left brand-wrapper">
			<a class="uk-navbar-item uk-logo" href="<?php echo $site->siteUrl; ?>/">SCRIPTOR</a>
		</div>
		<div class="uk-navbar-right">
			<ul class="uk-navbar-nav uk-visible@s">
				<?php echo $site->render('mainNavItems') ?>
			</ul>
			<a class="uk-navbar-toggle uk-navbar-item uk-hidden@s" data-uk-toggle href="#offcanvas-nav"><i class="gg-menu"></i></a>
		</div>
	</nav>
</header>