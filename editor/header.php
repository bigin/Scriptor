<?php
defined('IS_IM') or die('You cannot access this page directly');
if(isset($_SESSION['loggedin'])) { ?>
<header>
	<ul class="guillotine">
		<li><a id="trigger" href="#">&nbsp</a></li>
	</ul>
	<ul class="breadcrumbs">
		<?php echo $editor->breadcrumbs; ?>
	</ul>
	<ul class="profil">
		<li><a href="<?php echo $editor->pageUrl; ?>profil/edit/?profil=<?php echo (int) $_SESSION['userid'];
			?>"><i class="fas fa-user-circle"></i> <?php echo $editor->i18n['profil_menu'];
				?></a></li><li><a href="<?php echo $editor->pageUrl; ?>logout/"><i class="fas fa-sign-out-alt"></i>
				<?php echo $editor->i18n['logout_menu']; ?></a></li>
	</ul>
</header>
<?php } ?>