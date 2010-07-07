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
		var row = $('<tr>').appendTo(table.find('tbody'));

		$('<input>', {
			type:  'hidden',
			val:   field,
			name:  'FieldChangelogs[new][FieldName][]'
		}).appendTo(row);

		var summaryInput = $('<input>', {
			type:  'text',
			class: 'text',
			name:  'FieldChangelogs[new][EditSummary][]'
		});

		$('<td>', { text: field }).appendTo(row);
		$('<td>', { class: 'original', text: summarise(original) }).appendTo(row);
		$('<td>', { class: 'changed', text: summarise(changed) }).appendTo(row);
		$('<td>', { html: summaryInput }).appendTo(row);
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