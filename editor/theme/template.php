<?php defined('IS_IM') or die('You cannot access this page directly'); ?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<title><?php echo $editor->pageTitle; ?></title>
	<meta name="description" content="">
	<!-- Mobile-friendly viewport -->
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link rel="icon" href="<?php echo $editor->siteUrl; ?>/theme/favicon.ico" type="image/x-icon" />
	<link rel="stylesheet" href="<?php echo $editor->siteUrl; ?>/theme/css/prism.css">
	<link href="<?php echo $editor->siteUrl; ?>/theme/css/css-gg.min.css" rel="stylesheet">
	<link rel="stylesheet" href="<?php echo $editor->siteUrl; ?>/theme/css/jquery-ui.css">
	<link rel="stylesheet" href="<?php echo $editor->siteUrl; ?>/theme/css/styles.min.css">
	<?php echo $editor->getResources('link'); ?>
	<script src="<?php echo $editor->siteUrl; ?>/theme/scripts/jquery.min.js"></script>
	<script src="<?php echo $editor->siteUrl; ?>/theme/scripts/jquery-ui.min.js"></script>
	<?php echo $editor->getResources('script'); ?>
</head>
<body>
<div id="delay"><div id="clamp"><span id="loader"></span></div></div>
<main role="main">
	<?php include 'summary.php'; ?>
	<?php include 'header.php'; ?>
	<div class="page-wrapper">
		<div class="page">
			<div class="page-inner arounder">
				<?php echo $editor->getProperty('messages'); ?>
				<?php echo $editor->getProperty('pageContent'); ?>
			</div>
		</div>
	</div>
	<footer role="contentinfo">
		<div>
			<a href="https://scriptor-cms.info">Scriptor</a> <?php echo
				Scriptor\Core\Scriptor::VERSION; ?> &copy; <time datetime="<?php echo date('Y'); ?>"><?php echo date('Y'); ?></time>
		</div>
	</footer>
</main>
<?php echo $editor->getProperty('jsConfig'); ?>
<script src="<?php echo $editor->siteUrl; ?>/theme/scripts/remarkable/remarkable.min.js"></script>
<script src="<?php echo $editor->siteUrl; ?>/theme/scripts/prism.js"></script>
<script src="<?php echo $editor->siteUrl; ?>/theme/scripts/editor.js"></script>
</body>
</html>