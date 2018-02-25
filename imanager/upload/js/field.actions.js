function renumberImages() {
	$('.table tbody tr').each(function(i,tr) {
		$(tr).find('input').each(function(k,elem) {
			var name = $(elem).attr('name').replace(/\d+/, (i));
			$(elem).attr('name', name);
		});
	});
}