<footer role="contentinfo">
	<div class="uk-container uk-section">
		<div class="uk-grid-divider uk-child-width-expand@s" uk-grid>
			<div>
				<?php echo $site->render('footerNav') ?>
			</div>
            <div>
				<h3>Default theme</h3>
				<p>Scriptor offers the Basic, a default theme that allows admins to create their content 
					instantly. This theme is intended for demonstration purposes, if you want to give your website its own 
					look, just create a <a href="https://scriptor-cms.info/tutorials/themes-tutorial/creating-a-simple-theme/">custom theme</a>.</p>
            </div>
            <div>
				<h3>Get news</h3>
				<p>Subscribe to our email newsletter.</p>
				<div>
					<form class="scriptor-forms" id="subscribe-form" method="post" action="<?php echo $site->siteUrl.'/' ?>">
						<div class="uk-inline uk-width-1-1">
							<label class="bot-reader-text" for="s-email">Enter email</label>
							<input class="uk-input" type="email" id="s-email" placeholder="Enter email" name="email" autocomplete="off">
							<div class="token-loader" token-loader-url="<?php echo $site->siteUrl.'/' ?>">
								<?php echo $site->render('emptyCsrfFields') ?>
							</div>
							<input type="hidden" name="actionName" value="subscribe">
							<input type="hidden" name="function" value="subscribe">
							<button type="submit">Submit</button>
						</div>
					</form>
				</div>
            </div>
        </div>
	</div>
	<div class="uk-container">
		<hr>
		<div class="uk-child-width-expand@s" uk-grid>
			<div class="uk-flex uk-flex-middle">
				<p>Copyright &copy; <time datetime="<?php echo date('Y'); ?>"><?php echo date('Y'); ?></time>
				<a href="https://ehret-studio.com">Ehret Studio</a> | <a href="https://scriptor-cms.info">Scriptor</a> v. <?php echo $site->version; ?></p>
			</div>
			<div class="uk-flex uk-flex-middle uk-flex-right@s">
				<ul class="soc-icons">
					<?php echo $site->render('socIcons') ?>
				</ul>
			</div>
		</div>
	</div>
</footer>