// https://github.com/jonschlinkert/remarkable
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
	// We use Prism - https://prismjs.com/
	highlight: function (str, lang) {
		if (lang && Prism.languages[lang]) {
			try {
				return Prism.highlight(str, Prism.languages[lang], lang);
			} catch (__) {}
		}
		return '';
	}
});
var text = md.render($("#markdown").val());
if(text.length > 0) {
	$("#content").html(text);
}