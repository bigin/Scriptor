<?php

declare(strict_types=1);

/**
 * Template fragments for the Basic theme. Variables use `{{name}}` syntax
 * so the iManager 2.0 `Imanager\Templating\TemplateRenderer` (Phase 11)
 * resolves them — the legacy `[[VAR]]` syntax is gone with 14b-2.
 *
 * The `pagination` sub-array maps to the keys the
 * `Imanager\Templating\PaginationRenderer` expects (wrapper, prev,
 * prev_inactive, next, next_inactive, link, current, ellipsis).
 */

return [
    'art_list_image_caption' =>
        '<div class="info-layer">{{TEXT}}</div>',

    'art_list_figure' => <<<'EOD'
<figure class="uk-position-relative">
	<a href="{{URL}}"><img data-src="{{DATA_SRC}}" alt="{{ALT}}" uk-img></a>
	{{INFO_ROW}}
</figure>
EOD,

    'article_row' => <<<'EOD'
<article class="uk-section uk-padding-remove-top">
	<header{{HEADER_CLASS}}>
		<h2 class="uk-margin-remove-adjacent uk-margin-small-bottom">
			<a title="{{HEADER_LINK_TITLE}}" class="uk-link-reset" href="{{URL}}">{{HEADER_TEXT}}</a>
		</h2>
		<p class="uk-article-meta">Written on {{CREATED_DATE}}</p>
	</header>
	{{FIGURE}}
	{{CONTENT}}
	<div>
		<a href="{{URL}}" title="Read More" class="uk-button uk-button-default uk-button-small">READ MORE</a>
	</div>
</article>
EOD,

    'article_date' =>
        '<p class="uk-article-meta uk-text-center">{{CREATED_DATE}}{{MODIFIED_DATE}}</p>',

    'created_date'  => 'Written on {{DATE}}',
    'modified_date' => ' | Modified on {{DATE}}',

    'empty_article_row' => <<<'EOD'
<div class="uk-alert-primary" uk-alert>
    <p>&sdot; {{TEXT}}</p>
</div>
EOD,

    'csrf_token_fields' => <<<'EOD'
<input type="hidden" name="tokenName" value="{{NAME}}">
<input type="hidden" name="tokenValue" value="{{VALUE}}">
EOD,

    'icon_nav_row' =>
        '<li><a href="{{URL}}"><i class="icon {{ICON_NAME}}"></i></a></li>',

    'archive_nav_past_row' =>
        '<li><a href="{{URL}}">{{MONTH}} <small>{{YEAR}}</small></a></li>',

    'archive_nav_current_row' =>
        '<li><a href="{{URL}}">{{MONTH}}</a></li>',

    'hero' => <<<'EOD'
<section class="uk-section uk-padding-remove-vertical">
	<div class="uk-container">
		<div class="uk-height-large uk-cover-container uk-border-rounded">
			<img src="{{SRC}}" alt="" data-uk-cover="" class="uk-cover" style="height: 462px; width: 1200px;" uk-cover>
			<div class="info-layer">
				{{INFO}}
			</div>
		</div>
	</div>
</section>
EOD,

    'msg' => <<<'EOD'
<div class="uk-alert-{{TYPE}}" uk-alert>
	<a class="uk-alert-close"><i class="gg-close"></i></a>
	{{HEADER}}
	<p>{{TEXT}}</p>
</div>
EOD,

    'msg_header' => '<h3>{{TEXT}}</h3>',

    'footer_nav' => <<<'EOD'
<h3>{{MENU_TITLE}}</h3>
<p>{{INFO}}</p>
<ul class="uk-nav">
	{{ITEM_ROWS}}
</ul>
EOD,

    'nav_item' =>
        '<li class="{{CLASS}}"><a href="{{URL}}">{{ICON}}<span>{{TITLE}}</span></a></li>',

    // PaginationRenderer template overrides; variables are `counter`,
    // `href`, and `value` (lowercase, that's what the renderer emits).
    'pagination' => [
        'wrapper'        => '<div class="uk-container uk-padding"><ul class="uk-pagination uk-flex-center">{{value}}</ul></div>',
        'link'           => '<li><a href="{{href}}">{{counter}}</a></li>',
        'current'        => '<li class="uk-active"><span>{{counter}}</span></li>',
        'prev'           => '<li><a href="{{href}}"><span class="gg-chevron-left"></span></a></li>',
        'prev_inactive'  => '<li class="uk-disabled"><a href=""><span class="gg-chevron-left"></span></a></li>',
        'next'           => '<li><a href="{{href}}"><span class="gg-chevron-right"></span></a></li>',
        'next_inactive'  => '<li class="uk-disabled"><a href=""><span class="gg-chevron-right"></span></a></li>',
        'ellipsis'       => '<li class="uk-disabled"><span>&hellip;</span></li>',
    ],
];
