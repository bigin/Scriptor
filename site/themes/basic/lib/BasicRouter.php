<?php

declare(strict_types=1);

namespace Themes\Basic;

/**
 * Routing for the Basic theme.
 *
 * Thin wrapper around `BasicTheme` that
 *   1. dispatches user-action POSTs (contact, subscribe, loadToken),
 *   2. resolves the page using the blog-aware route helper.
 *
 * Replaces the legacy 1.x router that took a `Scriptor\Core\Module`.
 */
final class BasicRouter
{
    public function __construct(private readonly BasicTheme $site) {}

    public function execute(): void
    {
        $this->actions();
        $this->site->routeArticles();
    }

    public function actions(): void
    {
        $this->site->actions();
    }
}
