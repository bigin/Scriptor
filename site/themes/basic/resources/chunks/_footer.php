<footer role="contentinfo">
	<div class="uk-container uk-section">
		<div class="uk-grid-divider uk-child-width-expand@s" uk-grid>
			<div>
				<?php echo $site->render('footerNav') ?>
			</div>
            <div>
				<h3><?php echo $site->getTCP('footer')['middle_heading'] ?></h3>
				<p><?php echo $site->getTCP('footer')['middle_paragraph'] ?></p>
            </div>
            <div>
				<h3><?php echo $site->getTCP('footer')['sub_heading'] ?></h3>
				<p><?php echo $site->getTCP('footer')['sub_paragraph'] ?></p>
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
							<button type="submit"><?php echo $site->getTCP('footer')['submit_button_label'] ?></button>
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
				<?php echo $site->getTCP('copyright_info') ?> | <a class="decent" href="https://scriptor-cms.info/"><strong>Scriptor</strong></a> <?php echo Scriptor\Core\Scriptor::VERSION; ?></p>
			</div>
			<div class="uk-flex uk-flex-middle uk-flex-right@s">
				<ul class="soc-icons">
					<?php echo $site->render('socIcons') ?>
				</ul>
			</div>
		</div>
	</div>
</footer>