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

		<!-- PAGE CONTENT -->
		<div class="uk-container uk-container-xsmall uk-margin-medium-bottom">
			<div id="contact" role="article">
				<?php echo $site->render('messages') ?>
				<div class="uk-margin-medium-bottom uk-text-center">
					<?php echo $site->render('content') ?>
				</div>
				<section class="uk-section uk-padding-remove-top">
					<form class="scriptor-forms" id="contact-form" action="" method="POST">
						<div class="uk-margin">
							<label class="uk-form-label bot-reader-text" for="form-name">Your Name</label>
							<input class="uk-input" id="form-name" type="text" name="name" placeholder="Name" required>
						</div>
						<div class="uk-margin">
							<label class="form-label bot-reader-text" for="form-email">Your Email</label>
							<input class="uk-input" id="form-email" type="email" name="replyto" placeholder="Email" required>
						</div>
						<div class="uk-margin">
							<label class="form-label bot-reader-text" for="form-text">Your Message</label>
							<textarea class="uk-textarea" id="form-text" name="text" rows="10" placeholder="Message" required></textarea>
						</div>
						<div class="form-group">
							<div class="token-loader">
								<?php echo $site->render('emptyCsrfFields') ?>
							</div>
							<input type="hidden" name="actionName" value="contact">
							<input type="hidden" name="function" value="contact">
							<button class="button" type="submit">Send Message</button>
						</div>
					</form>
				</section>
			</div>
		</div>
	</div>
	<!-- /PAGE CONTENT -->

	<!-- FOOTER -->
	<?php include 'chunks/_footer.php'; ?>
	<!-- /FOOTER -->

	<!-- OFFCANVAS -->
	<?php include 'chunks/_offcanvas.php'; ?>
	<!-- /OFFCANVAS -->
</main>
<script async src="https://cdn.jsdelivr.net/npm/uikit@3.9.4/dist/js/uikit.min.js"></script>
<script async src="<?php echo $site->themeUrl; ?>scripts/main.js"></script>
<script async src="<?php echo $site->siteUrl.'/'.$site->config['admin_path']; ?>theme/scripts/prism.js"></script>
</body>
</html>