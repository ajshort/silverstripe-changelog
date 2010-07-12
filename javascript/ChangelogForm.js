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
	var createChangelogRow = function(table, type, field, class, title, original, changed) {
		$('#FieldChangelogs .triggerOpened, #FieldChangelogs .contentMore').show();
		$('#FieldChangelogs .triggerClosed').hide();
		table.find('tr.notfound').remove();

		var row = $('<tr>')
			.addClass(class)
			.appendTo(table.find('tbody'));

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
	$(':input.changelog').live('change', function() {
		var input    = $(this);
		var form     = input.parents('form');
		var field    = input.attr('name');
		var original = input[0].defaultValue;
		var table    = form.find('.TableField[id$=FieldChangelogs]');
		var dialog   = $('#ChangelogDialog');

		// convert the field name to a css class and try to find an existing
		// changelog entry
		var class = field.replace(/[^a-zA-Z0-9-_]/g, '-');
		var exist = table.find('tr.' + class);

		if (exist.length) {
			exist.find('td.changed').text(summarise(input.val()));
			return;
		}

		// if the field name contains brackets, assume its a sub-field on a
		// loggable relation
		if (field.indexOf('[') >= 0) {
			var match = field.match(/([a-zA-Z0-9_]+)\[([0-9]+)\]\[([a-zA-Z0-9_]+)\]/);

			// dont add changelog records for new objects
			if (!match) return;

			if (input.metadata() && input.metadata().title) {
				titlePart = input.metadata().title;
			} else {
				titlePart = match[3];
			}

			var type  = '[' + match[1] + '][' + match[2] + ']';
			var name  = match[3];
			var title = match[1] + ' #' + match[2] + ' ' + match[3];
		} else {
			var type  = '[new]';
			var name  = field;

			if (input.metadata() && input.metadata().title) {
				title = input.metadata().title;
			} else {
				title = field;
			}
		}

		// check for an explitly defined title

		createChangelogRow(table, type, name, class, title, original, input.val());

		// if the config options is set, prompt for a changelog message with
		// a dialog
		var isPrompt     = input.hasClass('changelog-prompt');
		var isRequired   = input.hasClass('changelog-required');

		if (!isPrompt && !isRequired) return;

		var summaryInput = dialog.find('input[name=EditSummary]');
		var requiredMsg  = dialog.find('#RequiredMessage');
		var buttons      = {}

		buttons['Save'] = function() {
			var summary = summaryInput.val();
			var input   = table.find('tr.' + class + ' input.text');

			input.val(summary);
			summaryInput.val('');

			dialog.dialog('close');
		}

		if (isRequired) {
			requiredMsg.show();
		} else {
			buttons['Cancel'] = function() {
				summaryInput.val('');
				dialog.dialog('close');
			}

			requiredMsg.hide();
		}

		dialog.find('.original').text(original);
		dialog.find('.changed').text(input.val());

		dialog.dialog({
			draggable: false,
			buttons: buttons,
			height: 380,
			modal: true,
			overlay: { backgroundColor: '#000', opacity: .5 },
			resizable: false,
			width: 500
		});
	});
})(jQuery);