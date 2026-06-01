<?php include 'resources/chunks/_head.php' ?>
<body>
<main role="main" class="default">
	<?php include 'resources/chunks/_header.php'; ?>
	<article class="uk-container">
		<?php echo $site->render('hero') ?>
		<div class="ui-padding uk-padding-small uk-padding-remove-top">
			<h1 class="uk-padding-remove-bottom"><?php echo $site->page->name ?></h1>
			<?php echo $site->render('articleDate') ?>
		</div>
		<div class="uk-container uk-container-xsmall uk-margin-large-bottom">
			<div id="content">
				<?php echo $site->render('messages') ?>
				<?php echo $site->render('content'); ?>
			</div>
		</div>
	</article>
	<!-- FOOTER -->
	<?php include 'resources/chunks/_footer.php'; ?>
	<!-- /FOOTER -->

	<!-- OFFCANVAS -->
	<?php include 'resources/chunks/_offcanvas.php'; ?>
	<!-- /OFFCANVAS -->
</main>
<script async src="<?php echo $site->themeAssetUrl('scripts/uikit.min.js'); ?>"></script>
<script async src="<?php echo $site->themeAssetUrl('scripts/main.js'); ?>"></script>
<script async src="<?php echo $site->editorAssetUrl('scripts/prism.js'); ?>"></script>
</body>
</html>