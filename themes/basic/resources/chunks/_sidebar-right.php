<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<title><?php echo $site->page->name; ?> - <?php echo $site->config['site_name']; ?></title>
	<meta name="description" content="">
	<!-- Mobile-friendly viewport -->
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link rel="stylesheet" href="<?php echo $site->editorAssetUrl('css/prism.css'); ?>">
	<link rel="stylesheet" href="<?php echo $site->themeAssetUrl('css/styles.css'); ?>">
	<link rel="icon" href="/favicon.ico" type="image/x-icon" />
</head>
<body>
<main role="main">
	<?php include '_header.php'; ?>
	<div class="page-wrapper">
		<div class="page">
			<div class="page-inner clear">
				<div id="page-header">
					<h1><?php echo $site->page->name; ?></h1>
				</div>
				<?php echo $site->messages; ?>
				<div id="content" role="article">
					<?php echo $site->render('content'); ?>
				</div>
				<?php include '_sidebar.php'; ?>
			</div>
		</div>
	</div>
	<footer role="contentinfo">
		<div class="clip">
			Copyright &copy; <time datetime="<?php echo date('Y'); ?>"><?php echo date('Y'); ?></time>
			<a href="https://ehret-studio.com">Ehret Studio</a> | Scriptor v. <?php echo $site->version; ?> |
			Powered by <a href="https://github.com/bigin/ItemManager-3">ItemManager</a>
		</div>
	</footer>
</main>
<script src="<?php echo $site->editorAssetUrl('scripts/jquery.min.js'); ?>"></script>
<script src="<?php echo $site->editorAssetUrl('scripts/prism.js'); ?>"></script>
</body>
</html>