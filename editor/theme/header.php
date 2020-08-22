<?php defined('IS_IM') or die('You cannot access this page directly');

if(isset($_SESSION['loggedin'])) { ?>
<header>
	<ul class="guillotine">
		<li><a id="trigger" href="#"><span class="gg-menu"></span></a></li>
	</ul>
	<ul class="breadcrumbs">
		<?php echo $editor->getProperty('breadcrumbs'); ?>
	</ul>
	<ul class="profile">
	<?php if($editor->config['modules']) {
		foreach($editor->config['modules'] as $slug => $module) {
			if($module['active'] && in_array('profile', $module['display_type'])) { ?><li<?php 
			echo(($imanager->input->urlSegments->get(0) == $slug) ? ' class="active" ' : '');
				?>><a href="<?php echo $editor->siteUrl.'/'.$slug.'/'.(($module['menu'] == 'logout_menu') ?
					'logout/'.$editor->csrf->renderUrl() : ''); ?>"><i class="<?php echo $module['icon'] ?>"></i><span><?php
						echo (isset($editor->i18n[$module['menu']])) ? $editor->i18n[$module['menu']] : $module['menu'];
						?></span></a></li>
		<?php	}
			}
		}
		?>
	</ul>
</header>
<?php } ?>