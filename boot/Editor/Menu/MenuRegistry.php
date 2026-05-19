<?php

declare(strict_types=1);

namespace Scriptor\Boot\Editor\Menu;

/**
 * Holds the editor's menu entries and filters them by display slot.
 *
 * Both `summary.php` (sidebar) and `header.php` (profile cluster) read
 * from this registry. The legacy `$config['modules']` driven menus are
 * preserved by {@see Scriptor\Boot\Plugin\CorePlugins\CoreEditorPlugin}
 * which seeds this registry from the config at boot. Plugins add
 * entries via {@see Scriptor\Boot\Plugin\PluginContext::addEditorMenuItem()}.
 */
final class MenuRegistry
{
    /** @var list<MenuItem> */
    private array $items = [];

    public function add(MenuItem $item): void
    {
        $this->items[] = $item;
    }

    /**
     * Items registered for the given display slot, sorted by ascending
     * position then by registration order.
     *
     * @return list<MenuItem>
     */
    public function forDisplay(string $displayType): array
    {
        $matching = [];
        foreach ($this->items as $index => $item) {
            if ($item->displayType === $displayType) {
                $matching[] = [$index, $item];
            }
        }
        usort($matching, static function (array $a, array $b): int {
            $cmp = $a[1]->position <=> $b[1]->position;
            return $cmp !== 0 ? $cmp : ($a[0] <=> $b[0]);
        });
        return array_map(static fn (array $pair): MenuItem => $pair[1], $matching);
    }
}
