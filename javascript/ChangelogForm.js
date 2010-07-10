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
	var createChangelogRow = function(table, type, field, title, original, changed) {
		$('#FieldChangelogs .triggerOpened, #FieldChangelogs .contentMore').show();
		$('#FieldChangelogs .triggerClosed').hide();
		table.find('tr.notfound').remove();

		var row = $('<tr>').appendTo(table.find('tbody'));

		$('<input>', {
			type:  'hidden',
			val:   field,
			name:  'FieldChangelogs' + type + '[FieldName][]'
		}).appendTo(row);

		var summaryInput = $('<input>', {
			type:  'text',
			class: 'text',
			name:  'FieldChangelogs' + type + '[EditSummary][]'
		});

		$('<td>', { text: title }).appendTo(row);
		$('<td>', { class: 'original', text: summarise(original) }).appendTo(row);
		$('<td>', { class: 'changed', text: summarise(changed) }).appendTo(row);
		$('<td>', { html: summaryInput }).appendTo(row);
	}

	/**
	 * Automatically creates changelog table entries for changed fields.
	 */
	$('.changelog:input').live('change', function() {
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
			createChangelogRow(
				table, '[new]', field, field, input[0].defaultValue, input.val()
			);
		}
	});

	/**
	 * Creates changelog table entries for changed fields on has_many table
	 * fields.
	 */
	$('.relation-changelog :input').live('change', function() {
		var input = $(this);
		var name  = input.attr('name');
		var form  = input.parents('form');
		var table = form.find('.TableField[id$=FieldChangelogs]');
		var match = name.match(/([a-zA-Z0-9_]+)\[([0-9]+)\]\[([a-zA-Z0-9_]+)\]/);

		if (match && (rel = match[1]) && (id = match[2]) && (field = match[3])) {
			createChangelogRow(
				table,
				'[' + rel + '][' + id + ']',
				field,
				rel + ' #' + id + ' ' + field,
				input[0].defaultValue,
				input.val()
			);
		}
	});
})(jQuery);