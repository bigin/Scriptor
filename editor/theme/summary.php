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
/** @var \Scriptor\Boot\Editor\Menu\MenuRegistry $menu */
$menu = \Scriptor\Boot\App::container()->get(\Scriptor\Boot\Editor\Menu\MenuRegistry::class);
foreach ($menu->forDisplay('sidebar') as $item) {
    $href  = $item->href ?? ($editor->siteUrl . '/' . $item->slug . '/');
    $label = $editor->i18n[$item->label] ?? $item->label;
    $cls   = 'chapter' . ($activeSlug === $item->slug ? ' active' : '');
?>
			<li class="<?= htmlspecialchars($cls, ENT_QUOTES) ?>" data-level="1.1" data-path="./">
				<a href="<?= htmlspecialchars($href, ENT_QUOTES) ?>"><?= $item->icon !== '' ? '<i class="' . htmlspecialchars($item->icon, ENT_QUOTES) . '"></i>' : '' ?><span><?= htmlspecialchars($label, ENT_QUOTES) ?></span></a>
			</li>
<?php } ?>
		</ul>
<?php endif; ?>
	</nav>
</div>
