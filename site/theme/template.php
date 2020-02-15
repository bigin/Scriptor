<?php defined('IS_IM') or die('You cannot access this page directly'); ?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<title><?php echo $page->title; ?> - <?php echo $page->config['site_name']; ?></title>
	<meta name="description" content="">
	<!-- Mobile-friendly viewport -->
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link rel="stylesheet" href="<?php echo $page->siteUrl.$page->config['admin_path']; ?>css/prism.css">
	<link rel="stylesheet" href="<?php echo $page->themeUrl; ?>css/styles.css">
	<link rel="icon" href="<?php echo $page->siteUrl.$page->config['admin_path']; ?>favicon.ico" type="image/x-icon" />
</head>
<body>
<main role="main">
	<?php include('header.php'); ?>
	<div class="page-wrapper">
		<div class="page">
			<div class="page-inner clear">
				<div id="page-header">
					<h1><?php echo $page->title; ?></h1>
				</div>
				<?php echo $page->messages; ?>
				<div id="content" role="article">
					<?php echo $page->render('content'); ?>
				</div>
				<?php include('sidebar.php'); ?>
			</div>
		</div>
	</div>
	<footer role="contentinfo">
		<div class="clip">
			Copyright &copy; <time datetime="<?php echo date('Y'); ?>"><?php echo date('Y'); ?></time>
			<a href="https://ehret-studio.com">Ehret Studio</a> | Scriptor v. <?php echo $page->config['version']; ?> |
			Powered by <a href="https://gitlab.com/bigin1/imanager">IManager</a>
		</div>
	</footer>
</main>
<script src="<?php echo $page->siteUrl.$page->config['admin_path']; ?>scripts/jquery.min.js"></script>
<script src="<?php echo $page->siteUrl.$page->config['admin_path']; ?>scripts/prism.js"></script>
</body>
</html>
