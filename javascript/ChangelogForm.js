(function($) {
	/**
	 * Summarises a string, adding "..." if it is longer than 30 characters.
	 */
	var summarise = function(string) {
		if (string.length > 30) {
			return string.substr(0, 27) + '...';
		} else {
			return string;
		}
	}

	/**
	 * Creates a changelog table row.
	 */
	var createChangelogRow = function(table, field, original, changed) {
		var row = $('<tr></tr>')
			.attr('id', 'New_' + (table.find('tbody tr').length + 1))
			.addClass('row')
			.appendTo(table.find('tbody'));

		var fieldCol = $('<td></td>')
			.text(field)
			.appendTo(row);

		$('<input type="hidden">')
			.attr('name', 'FieldChangelogs[new][FieldName][]')
			.val(field)
			.appendTo(fieldCol);

		$('<td></td>')
			.addClass('original')
			.text(summarise(original))
			.appendTo(row)

		$('<td></td>')
			.addClass('changed')
			.text(summarise(changed))
			.appendTo(row)

		var editSummaryCol = $('<td></td>')
			.appendTo(row)

		$('<input type="text">')
			.addClass('text')
			.attr('name', 'FieldChangelogs[new][EditSummary][]')
			.appendTo(editSummaryCol);
	}

	/**
	 * Automatically creates changelog table entries for changed fields.
	 */
	$(':input.changelog').live('change', function() {
		var input = $(this);
		var form  = input.parents('form');
		var field = input.attr('name');
		var table = form.find('.TableField[id$=FieldChangelogs]');

		// check if we need to create a new row in the changelog table for
		// this change
		var existing = $('input:hidden[value=' + field + ']', table);

		if (existing.length) {
			existing
				.parents('tr')
				.find('td.changed')
				.text(summarise(input.val()))
		} else {
			table.find('tr.notfound').remove();
			createChangelogRow(
				table, input.attr('name'), input[0].defaultValue, input.val()
			);
		}
	});
})(jQuery);