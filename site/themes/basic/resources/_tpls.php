<?php

return [
'art_list_image_caption' => 
<<<EOD
	<div class="info-layer">[[TEXT]]</div>
EOD,

'art_list_figure' => 
<<<EOD
<figure class="uk-position-relative">
	<a href="[[URL]]"><img data-src="[[DATA_SRC]]" alt="[[ALT]]" uk-img></a>
	[[INFO_ROW]]
</figure>
EOD,

'article_row' =>
<<<EOD
<article class="uk-section uk-padding-remove-top">
	<header[[HEADER_CLASS]]>
		<h2 class="uk-margin-remove-adjacent uk-margin-small-bottom">
			<a title="[[HEADER_LINK_TITLE]]" class="uk-link-reset" href="[[URL]]">[[HEADER_TEXT]]</a>
		</h2>
		<p class="uk-article-meta">Written on [[CREATED_DATE]]</p>
	</header>
	[[FIGURE]]
	[[CONTENT]]
	<a href="[[URL]]" title="Read More" class="uk-button uk-button-default uk-button-small">READ MORE</a>
</article>
EOD,

'article_date' =>
<<<EOD
<p class="uk-article-meta uk-text-center">Written on [[CREATED_DATE]] | [[MODIFIED_DATE]]</p>
EOD,

'article_date' =>
<<<EOD
<p class="uk-article-meta uk-text-center">[[CREATED_DATE]][[MODIFIED_DATE]]</p>
EOD,

'created_date' => 'Written on [[DATE]]',

'modified_date' => ' | Modified on [[DATE]]',

'empty_article_row' =>
<<<EOD
<div class="uk-alert-primary" uk-alert>
    <p>&sdot; [[TEXT]]</p>
</div>
EOD,

'csrf_token_fields' =>
<<<EOD
<input type="hidden" name="tokenName" value="[[NAME]]">
<input type="hidden" name="tokenValue" value="[[VALUE]]">
EOD,

'icon_nav_row' => '<li><a href="[[URL]]"><i class="icon [[ICON_NAME]]"></i></a></li>',

'archive_nav_past_row' => '<li><a href="[[URL]]">[[MONTH]] <small>[[YEAR]]</small></a></li>',

'archive_nav_current_row' => '<li><a href="[[URL]]">[[MONTH]]</li>',

'hero' =>
<<<EOD
<section class="uk-section uk-padding-remove-vertical">
	<div class="uk-container">
		<div class="uk-height-large uk-cover-container uk-border-rounded">
			<img src="[[SRC]]" alt="" data-uk-cover="" class="uk-cover" style="height: 462px; width: 1200px;">
			<div class="info-layer">
				[[INFO]]
			</div>
		</div>
	</div>
</section>
EOD,

'msg' =>
<<<EOD
<div class="uk-alert-[[TYPE]]" uk-alert>
	<a class="uk-alert-close"><i class="gg-close"></i></a>
	[[HEADER]]
	<p>[[TEXT]]</p>
</div>
EOD,

'msg_header' => '<h3>[[TEXT]]</h3>',

'footer_nav' =>
<<<EOD
<h3>[[MENU_TITLE]]</h3>
<p>[[INFO]]</p>
<ul class="uk-nav">
	[[ITEM_ROWS]]
</ul>
EOD,

'nav_item' => '<li class="[[CLASS]]"><a href="[[URL]]">[[ICON]]<span>[[TITLE]]</span></a></li>',

'pagination_wrapper' => 
<<<EOD
<div class="uk-container uk-padding">
	<ul class="uk-pagination uk-flex-center">
		[[value]]
	</ul>
</div>
EOD,

'pagination_inactive' => '<li class="uk-active"><span>[[counter]]</span></li>',
'pagination_active' => '<li><a href="[[href]]">[[counter]]</a></li>',
'pagination_prev' => '<li><a href="[[href]]"><span class="gg-chevron-left"></span></a></li>',
'pagination_prev_inactive' => '<li class="uk-disabled"><a href=""><span class="gg-chevron-left"></span></a></li>',
'pagination_next' => '<li><a href="[[href]]"><span class="gg-chevron-right"></span></a></li>',
'pagination_next_inactive' => '<li class="uk-disabled"><a href=""><span class="gg-chevron-right"></span></a></li>',
'pagination_ellipsis' => '<li class="uk-disabled"><span>...</span></li>'

];
