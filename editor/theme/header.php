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
foreach ((array) ($editor->config['modules'] ?? []) as $slug => $module) {
    if (
        empty($module['active'])
        || ! \is_array($module['display_type'] ?? null)
        || ! \in_array('profile', $module['display_type'], true)
    ) {
        continue;
    }
    $isLogout = ($module['menu'] ?? '') === 'logout_menu';
    $href = $editor->siteUrl . '/' . $slug . '/' . ($isLogout ? 'logout/' . $editor->csrfQueryString() : '');
    $label = $editor->i18n[$module['menu']] ?? (string) $module['menu'];
    $icon  = (string) ($module['icon'] ?? '');
    $cls   = $activeSlug === $slug ? ' class="active"' : '';
?>
		<li<?= $cls ?>><a href="<?= htmlspecialchars($href, ENT_QUOTES) ?>"><i class="<?= htmlspecialchars($icon, ENT_QUOTES) ?>"></i><span><?= htmlspecialchars($label, ENT_QUOTES) ?></span></a></li>
<?php } ?>
	</ul>
</header>
