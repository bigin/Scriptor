<?php declare(strict_types=1);

if (! $editor->isLoggedIn()) {
    return;
}
?>
<header class="arounder">
	<ul class="guillotine">
		<li><a id="trigger" href="#"><span class="gg-menu"></span></a></li>
	</ul>
	<ul class="breadcrumbs">
		<?php echo $editor->getProperty('breadcrumbs'); ?>
	</ul>
	<ul class="profile">
<?php
$activeSlug = $editor->urlSegments->first() ?? '';
/** @var \Scriptor\Boot\Editor\Menu\MenuRegistry $menu */
$menu = \Scriptor\Boot\App::container()->get(\Scriptor\Boot\Editor\Menu\MenuRegistry::class);
foreach ($menu->forDisplay('profile') as $item) {
    // Logout entry needs a CSRF query string in the URL. Anchored on
    // the label key the CoreEditorPlugin seeds from the config rather
    // than a flag on MenuItem, so plugin-contributed profile items
    // don't accidentally trigger the logout suffix.
    $isLogout = $item->slug === 'auth' && $item->label === 'logout_menu';
    $href = $item->href
        ?? $editor->siteUrl . '/' . $item->slug . '/' . ($isLogout ? 'logout/' . $editor->csrfQueryString() : '');
    $label = $editor->i18n[$item->label] ?? $item->label;
    $cls   = $activeSlug === $item->slug ? ' class="active"' : '';
?>
		<li<?= $cls ?>><a href="<?= htmlspecialchars($href, ENT_QUOTES) ?>"><i class="<?= htmlspecialchars($item->icon, ENT_QUOTES) ?>"></i><span><?= htmlspecialchars($label, ENT_QUOTES) ?></span></a></li>
<?php } ?>
	</ul>
</header>
