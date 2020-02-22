<?php defined('IS_IM') or die('You cannot access this page directly'); ?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<title><?php echo $editor->pageTitle; ?></title>
	<meta name="description" content="">
	<!-- Mobile-friendly viewport -->
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link rel="icon" href="<?php echo $editor->siteUrl; ?>theme/favicon.ico" type="image/x-icon" />
	<link rel="stylesheet" href="<?php echo $editor->siteUrl; ?>theme/css/prism.css">
	<link href="<?php echo $editor->siteUrl; ?>theme/css/fontawesome/on-server/css/fontawesome-all.css" rel="stylesheet">
	<link rel="stylesheet" href="<?php echo $editor->siteUrl; ?>theme/css/jquery-ui.css">
	<link rel="stylesheet" href="<?php echo $editor->siteUrl; ?>theme/css/styles.css">
	<?php echo $editor->getHeaderResources('css'); ?>
	<script src="<?php echo $editor->siteUrl; ?>theme/scripts/jquery.min.js"></script>
	<script src="<?php echo $editor->siteUrl; ?>theme/scripts/jquery-ui.min.js"></script>
	<?php echo $editor->getHeaderResources('js'); ?>
</head>
<body>
<div id="delay"><div id="clamp"><span id="loader"></span></div></div>
<main role="main">
	<?php include 'summary.php'; ?>
	<?php include 'header.php'; ?>
	<div class="page-wrapper">
		<div class="page">
			<div class="page-inner">
				<?php echo $editor->getProperty('messages'); ?>
				<?php echo $editor->getProperty('pageContent'); ?>
			</div>
		</div>
	</div>
	<footer role="contentinfo">
		<div>
			<a href="https://github.com/bigin/Scriptor/releases">Scriptor <?php echo
				Scriptor\Scriptor::VERSION; ?></a> |
			Copyright &copy; <time datetime="<?php echo date('Y'); ?>"><?php echo date('Y'); ?></time>
			<a href="https://ehret-studio.com">Ehret Studio</a> |
			Powered by <a href="https://github.com/bigin/ItemManager-3">ItemManager</a>
			<a class="right-pos" href="https://ehret-studio.com/contact/"><?php echo
				$editor->i18n['contact_developer']; ?> <i class="far fa-paper-plane"></i></a>
		</div>
	</footer>
</main>
<script src="<?php echo $editor->siteUrl; ?>theme/scripts/remarkable/remarkable.min.js"></script>
<script src="<?php echo $editor->siteUrl; ?>theme/scripts/prism.js"></script>
<script src="<?php echo $editor->siteUrl; ?>theme/scripts/editor.js"></script>
</body>
</html>
