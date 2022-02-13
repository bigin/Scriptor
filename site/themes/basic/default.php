<?php defined('IS_IM') or die('You cannot access this page directly'); ?>
<?php include 'chunks/_head.php' ?>
<body>
<main role="main" class="default">
	<?php include 'chunks/_header.php'; ?>
	<div class="uk-container">
		<?php echo $site->render('hero') ?>
		<div>
			<h1><?php echo $site->page->name ?></h1>
		</div>
		<div class="uk-container uk-container-xsmall uk-margin-large-bottom">
			<div id="content" role="article">
				<?php echo $site->render('messages') ?>
				<?php echo $site->render('content'); ?>
			</div>
		</div>
	</div>
	<!-- FOOTER -->
	<?php include 'chunks/_footer.php'; ?>
	<!-- /FOOTER -->

	<!-- OFFCANVAS -->
	<?php include 'chunks/_offcanvas.php'; ?>
	<!-- /OFFCANVAS -->
</main>
<script async src="https://cdn.jsdelivr.net/npm/uikit@3.9.4/dist/js/uikit.min.js"></script>
<script async src="<?php echo $site->themeUrl; ?>/scripts/main.js"></script>
<script async src="<?php echo $site->siteUrl.'/'.$site->config['admin_path']; ?>theme/scripts/prism.js"></script>
</body>
</html>