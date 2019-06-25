// https://highlightjs.org/
var md = new Remarkable('full', {
	html:         false,        // Enable HTML tags in source
	xhtmlOut:     false,        // Use '/' to close single tags (<br />)
	breaks:       false,        // Convert '\n' in paragraphs into <br>
	langPrefix:   'language-',  // CSS language prefix for fenced blocks
	linkify:      true,         // autoconvert URL-like texts to links
	linkTarget:   '',           // set target to open link in
	// Enable some language-neutral replacements + quotes beautification
	typographer:  false,
	// Double + single quotes replacement pairs, when typographer enabled,
	// and smartquotes on. Set doubles to '«»' for Russian, '„“' for German.
	quotes: '“”‘’',
	// Highlighter function. Should return escaped HTML,
	// or '' if input not changed
	highlight: function (str, lang) {
		if(lang && Prism.languages[lang]) {
			try {
				return Prism.highlight(str, Prism.languages[lang], lang);
			} catch (__) {}
		}
		return '';
	}
});

function auto_grow(element) {
	var scrollLeft = window.pageXOffset ||
		(document.documentElement || document.body.parentNode || document.body).scrollLeft;

	var scrollTop  = window.pageYOffset ||
		(document.documentElement || document.body.parentNode || document.body).scrollTop;
	element.style.height = "5px";
	element.style.height = (element.scrollHeight)+"px";

	window.scrollTo(scrollLeft, scrollTop);
	$(".summary-wrapper").height($(document).height());
}
function sendData(url, formData, _callback) {
	$.ajax({
		dataType: "json",
		type: "post",
		url: url,
		data: formData,
		cache: false,
		contentType: false,
		processData: false,
		success: function (data) {
			_callback(data);
		},
		error: function (data) {
			console.log(data);
		},
	});
};
function renumberPages() {
	var form = $("#page-list-form");
	var formData = new FormData(document.querySelector("#page-list-form"));
	sendData(form.attr("action"), formData, function(result) {
		//console.log(result);
		if(result && result.status == 1) { return true; }
	});
}
function fixWidthHelper(e, ui) {
	ui.children().each(function() {
		$(this).width($(this).width());
	});
	return ui;
}
$("#render").click(function (e) {
	e.preventDefault();
	var text = md.render($("#markdown").val());
	if(text.length > 0) {
		$("#page-text").html(text);
	}
	$("#screen").show();
	return false;
});
$(".close").click(function () {
	$("#screen").hide();
	$(".summary-wrapper").removeClass("down");
});
var mdf = document.getElementById("markdown");
if(mdf) { auto_grow(mdf); }

$(".remove").click(function() {
	return confirm($(this).attr("rel"));
});

$("#trigger").click(function(e) {
	e.preventDefault();
	$(".summary-wrapper, .page, header, footer").toggleClass("down");
});

$("#page-list-table tbody").sortable({
	helper: fixWidthHelper,
	items:"tr.sortable",
	handle:"td",
	update:function(e,ui) {
		renumberPages();
	}
}).disableSelection();

$("#delay").fadeOut();
$(document).on({
	ajaxStart: function () {
		$("#delay").show();
	},
	ajaxStop: function () {
		$("#delay").fadeOut();
	}
});