<?php

declare(strict_types=1);

namespace Scriptor\Boot\Events\Frontend;

use Imanager\Http\UrlSegments;
use Scriptor\Boot\Frontend\Page;

/**
 * Last-chance event before {@see Scriptor\Boot\Frontend\Site::throw404()}
 * actually renders the 404 page. Listeners get the same cooperative
 * resolution slot as {@see PageResolving}: if anyone sets a non-null
 * Page, Site treats the request as resolved after all and skips the
 * 404 path.
 *
 * Typical use: a plugin that wants to handle deep dynamic paths but
 * does not want to claim resolution authority for every URL upfront.
 * It subscribes to RouteNotFound and only matches the patterns it
 * cares about, leaving everything else to the standard 404 flow.
 */
final class RouteNotFound
{
    public ?Page $resolution = null;

    public function __construct(
        public readonly UrlSegments $urlSegments,
    ) {}
}
