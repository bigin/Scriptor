<?php defined('IS_IM') or die('You cannot access this page directly'); ?>
<?php include 'resources/chunks/_head.php' ?>
<body>
<main role="main" class="blog">
	<?php include 'resources/chunks/_header.php'; ?>
	<div class="uk-container">
		<?php echo $site->render('messages') ?>
		<?php echo $site->render('hero') ?>
		<div>
			<h1 class="uk-text-left"><?php echo $site->page->name ?></h1>
		</div>

		<!-- PAGE CONTENT -->
		<div id="content" class="uk-section uk-section-xsmall">
			<div class="uk-container uk-margin-bottom">
				<div class="uk-grid">
					<div class="uk-width-2-3@m">
						<h4 class="uk-heading-line"><span><?php echo $site->getTCP('blog')['article_list_header'] ?></span></h4>
						<?php echo $site->render('archivesContent'); ?>
						<?php echo $site->render('pagination'); ?>
					</div>

					<div class="sidebar uk-width-1-3@m">
						<h4 class="uk-heading-line"><span><?php echo $site->getTCP('blog')['archive_header'] ?></span></h4>
						<ul class="uk-list">
							<?php echo $site->render('archiveNav'); ?>
						</ul>
						<h4 class="uk-heading-line"><span><?php echo $site->getTCP('blog')['about_header'] ?></span></h4>
						<div class="uk-tile uk-tile-small uk-tile-muted uk-border-rounded">
							<?php echo $site->getTCP('blog')['about_text'] ?>
						</div>
					</div>	
				</div>
			</div>
		</div>
	</div>
	<!-- /PAGE CONTENT -->

	<!-- FOOTER -->
	<?php include 'resources/chunks/_footer.php'; ?>
	<!-- /FOOTER -->

	<!-- OFFCANVAS -->
	<?php include 'resources/chunks/_offcanvas.php'; ?>
	<!-- /OFFCANVAS -->
</main>
<script async src="https://cdn.jsdelivr.net/npm/uikit@3.9.4/dist/js/uikit.min.js"></script>
<script async src="<?php echo $site->themeUrl; ?>scripts/main.js"></script>
<script async src="<?php echo $site->siteUrl.'/'.$site->config['admin_path']; ?>theme/scripts/prism.js"></script>
</body>
</html>