<?php include('_inc.php'); ?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<title><?php echo $editor->pageTitle; ?></title>
	<meta name="description" content="">
	<!-- Mobile-friendly viewport -->
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link rel="icon" href="<?php echo $editor->pageUrl; ?>favicon.ico?v=1" type="image/x-icon" />
	<link rel="stylesheet" href="<?php echo $editor->pageUrl; ?>css/prism.css">
	<link href="<?php echo $editor->pageUrl; ?>css/fontawesome/on-server/css/fontawesome-all.css" rel="stylesheet">
	<link rel="stylesheet" href="<?php echo $editor->pageUrl; ?>css/jquery-ui.css">
	<link rel="stylesheet" href="<?php echo $editor->pageUrl; ?>css/styles.css">
	<?php echo $editor->getHeaderResources('css'); ?>
	<script src="<?php echo $editor->pageUrl; ?>scripts/jquery.min.js"></script>
	<script src="<?php echo $editor->pageUrl; ?>scripts/jquery-ui.min.js"></script>
	<?php echo $editor->getHeaderResources('js'); ?>
</head>
<body>
<div id="delay"><div id="clamp"><span id="loader"></span></div></div>
<main role="main">
	<?php include('summary.php'); ?>
	<?php include('header.php'); ?>
	<div class="page-wrapper">
		<div class="page">
			<div class="page-inner">
				<?php echo $editor->messages; ?>
				<?php echo $editor->pageContent; ?>
			</div>
		</div>
	</div>
	<footer role="contentinfo">
		<div>
			<a href="https://github.com/bigin/Scriptor/releases">Scriptor <?php echo
				$editor->config['version']; ?></a> |
			Copyright &copy; <time datetime="<?php echo date('Y'); ?>"><?php echo date('Y'); ?></time>
			<a href="https://ehret-studio.com">Ehret Studio</a> |
			Powered by <a href="https://gitlab.com/bigin1/imanager">IManager</a>
			<a class="right-pos" href="https://ehret-studio.com/contact/"><?php echo
				$editor->i18n['contact_developer']; ?> <i class="far fa-paper-plane"></i></a>
		</div>
	</footer>
</main>
<script src="<?php echo $editor->pageUrl; ?>scripts/remarkable/remarkable.min.js"></script>
<script src="<?php echo $editor->pageUrl; ?>scripts/prism.js"></script>
<script src="<?php echo $editor->pageUrl; ?>scripts/editor.js"></script>
</body>
</html>
