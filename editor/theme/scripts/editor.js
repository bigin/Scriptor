(function() {
	// https://highlightjs.org/
	var md = new Remarkable('full', {
		// Enable HTML tags in source
		html: (typeof editConf !== 'undefined') ? editConf.allowHtmlOutput : false,
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
	function copyToClipboard(text) {
		if (window.clipboardData && window.clipboardData.setData) {
			// Internet Explorer-specific code path to prevent textarea being shown while dialog is visible.
			return clipboardData.setData("Text", text);
		}
		else if (document.queryCommandSupported && document.queryCommandSupported("copy")) {
			var textarea = document.createElement("textarea");
			textarea.textContent = text;
			textarea.style.position = "fixed";  // Prevent scrolling to bottom of page in Microsoft Edge.
			document.body.appendChild(textarea);
			textarea.select();
			try {
				return document.execCommand("copy");  // Security exception may be thrown by some browsers.
			}
			catch (ex) {
				console.warn("Copy to clipboard failed.", ex);
				return false;
			}
			finally {
				document.body.removeChild(textarea);
			}
		}
	}
	$("#render").click(function (e) {
		e.preventDefault();
		var form = $("#page-form");
		var formData = new FormData(document.querySelector("#page-form"));
		formData.append("action", "render-markdown");
		var text = md.render($("#markdown").val());
		formData.append("content", text);
		sendData(form.attr("action"), formData, function(result) {
			if(result && result.status == 1) {
				$("#page-text").html(result.text);
			}
		});
		$("#screen").show();
		return false;
	});
	$(".close").click(function () {
		$("#screen").hide();
		$(".summary-wrapper").removeClass("down");
	});
	var mdf = document.getElementById("markdown");
	if(mdf) { 
		auto_grow(mdf); 
		mdf.onkeyup = function() { auto_grow(this); };
	}

	$(".remove").click(function() {
		return confirm($(this).attr("rel"));
	});

	$("#trigger").click(function(e) {
		e.preventDefault();
		$(".summary-wrapper, .page, header, footer").toggleClass("down");
	});

	$(document).on("click", ".copy", function(e) {
        e.preventDefault();
		copyToClipboard($(this).attr("rel"));
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

	// Fixed Width Sortable Tables Row with jQueryUI
	/* $('table tbody').sortable({
		helper: fixWidthHelper
	}).disableSelection(); */
	function fixWidthHelper(e, ui) {
		ui.children().each(function() {
			$(this).width($(this).width());
			$(this).height($(this).height());
		});
		return ui;
	}
})();