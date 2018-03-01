<?php defined('IS_IM') or die('You cannot access this page directly'); ?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<title><?php echo $page->title; ?> - <?php echo $page->config['site_name']; ?></title>
	<meta name="description" content="">
	<!-- Mobile-friendly viewport -->
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link rel="stylesheet" href="<?php echo $page->siteUrl; ?>editor/css/hljs.css">
	<link rel="stylesheet" href="<?php echo $page->themeUrl; ?>css/styles.css">
	<link rel="icon" href="<?php echo $page->siteUrl; ?>editor/favicon.ico?v=1" type="image/x-icon" />
</head>
<body>
<main role="main">
	<?php include('header.php'); ?>
	<div class="page-wrapper">
		<div class="page">
			<div class="page-inner clear">
				<div id="page-header">
					<h1><a href="<?php echo $page->siteUrl; ?>" class="page-hyperlink"><?php echo $page->title; ?></a></h1>
				</div>
				<?php echo $page->messages; ?>
				<div id="content" role="article">
					<noscript>
						<div class="message">
							<p class="error">We're sorry but our site <strong>requires</strong> JavaScript.
								This site uses JavaScript to parse the content client-side to generate dynamic HTML.</p>
						</div>
					</noscript>
					<input id="markdown" type="hidden" value="<?php echo $page->content; ?>">
				</div>
				<?php include('sidebar.php'); ?>
			</div>
		</div>
	</div>
	<footer role="contentinfo">
		<div class="clip">
			Copyright &copy; <time datetime="<?php echo date('Y'); ?>"><?php echo date('Y'); ?></time>
			<a href="https://ehret-studio.com">Ehret Studio</a> | Scriptor v. 1.0 |
			Created with <a href="https://gitlab.com/bigin1/imanager">IManager</a>
		</div>
	</footer>
</main>
<script src="<?php echo $page->siteUrl; ?>editor/scripts/jquery.min.js"></script>
<script src="<?php echo $page->siteUrl; ?>editor/scripts/remarkable/remarkable.min.js"></script>
<script src="<?php echo $page->siteUrl; ?>editor/scripts/highlight/highlight.pack.js"></script>
<script src="<?php echo $page->themeUrl; ?>scripts/main.js"></script>
</body>
</html>
