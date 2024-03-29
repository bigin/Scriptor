<?php defined('IS_IM') or die('You cannot access this page directly'); ?>
<div class="summary-wrapper">
	<span class="close"><i class="gg-close"></i></span>
	<nav role="navigation">
		<div class="brand-wrapper">
			<a href="<?php echo $editor->siteUrl; ?>"><img alt="logo" width="200" src="<?php echo $editor->siteUrl; ?>/theme/images/logo.svg"></a>
		</div>
		<?php if(isset($_SESSION['loggedin'])) { ?>
		<ul class="summary">
			<?php if($editor->config['modules']) {
				foreach($editor->config['modules'] as $slug => $module) {
					if($module['active'] && in_array('sidebar', $module['display_type'])) {
						?>
						<li class="chapter<?php echo(($imanager->input->urlSegments->get(0) == $slug) ? ' active' : ''); ?>"
							data-level="1.1" data-path="./">
							<a href="<?php echo $editor->siteUrl.'/'.$slug; ?>/"><?php echo (isset($module['icon']) && !empty($module['icon'])) ? 
								'<i class="'.$module['icon'].'"></i>' : ''; ?><span><?php echo (isset($editor->i18n[$module['menu']])) ? 
								$editor->i18n[$module['menu']] : $module['menu']; ?></span></a>
						</li>
						<?php
					}
				}
			?>
			<?php } ?>
		</ul>
		<?php } ?>
	</nav>
</div>