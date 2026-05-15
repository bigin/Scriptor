<?php declare(strict_types=1); ?>
<div class="summary-wrapper">
	<span class="close"><i class="gg-close"></i></span>
	<nav role="navigation">
		<div class="brand-wrapper">
			<a href="<?php echo htmlspecialchars($editor->siteUrl, ENT_QUOTES); ?>"><img alt="logo" width="200" src="<?php echo htmlspecialchars($editor->assetUrl('images/logo.svg'), ENT_QUOTES); ?>"></a>
		</div>
<?php if ($editor->isLoggedIn()): ?>
		<ul class="summary">
<?php
$activeSlug = $editor->urlSegments->first() ?? '';
foreach ((array) ($editor->config['modules'] ?? []) as $slug => $module) {
    if (
        empty($module['active'])
        || ! \is_array($module['display_type'] ?? null)
        || ! \in_array('sidebar', $module['display_type'], true)
    ) {
        continue;
    }
    $href  = $editor->siteUrl . '/' . $slug . '/';
    $label = $editor->i18n[$module['menu']] ?? (string) $module['menu'];
    $icon  = (string) ($module['icon'] ?? '');
    $cls   = 'chapter' . ($activeSlug === $slug ? ' active' : '');
?>
			<li class="<?= htmlspecialchars($cls, ENT_QUOTES) ?>" data-level="1.1" data-path="./">
				<a href="<?= htmlspecialchars($href, ENT_QUOTES) ?>"><?= $icon !== '' ? '<i class="' . htmlspecialchars($icon, ENT_QUOTES) . '"></i>' : '' ?><span><?= htmlspecialchars($label, ENT_QUOTES) ?></span></a>
			</li>
<?php } ?>
		</ul>
<?php endif; ?>
	</nav>
</div>
