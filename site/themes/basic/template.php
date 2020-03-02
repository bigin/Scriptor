<?php defined('IS_IM') or die('You cannot access this page directly'); ?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<title><?php echo $site->title; ?> - <?php echo $site->config['site_name']; ?></title>
	<meta name="description" content="">
	<!-- Mobile-friendly viewport -->
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link rel="stylesheet" href="<?php echo $site->siteUrl.'/'.$site->config['admin_path']; ?>theme/css/prism.css">
	<link rel="stylesheet" href="<?php echo $site->themeUrl; ?>/css/styles.css">
	<link rel="icon" href="<?php echo $site->siteUrl.'/'.$site->config['admin_path']; ?>theme/favicon.ico" type="image/x-icon" />
</head>
<body>
<main role="main">
	<?php include('header.php'); ?>
	<div class="page-wrapper">
		<div class="page">
			<div class="page-inner clear">
				<div id="page-header">
					<h1><?php echo $site->title; ?></h1>
				</div>
				<?php echo $site->messages; ?>
				<div id="content" role="article">
					<?php echo $site->render('content'); ?>
				</div>
				<?php include('sidebar.php'); ?>
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
<script src="<?php echo $site->siteUrl.'/'.$site->config['admin_path']; ?>theme/scripts/jquery.min.js"></script>
<script src="<?php echo $site->siteUrl.'/'.$site->config['admin_path']; ?>theme/scripts/prism.js"></script>
</body>
</html>
