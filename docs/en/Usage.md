Usage
=====

The Changelog module adds the ability to maintain a changelog at the record and
field level, as well as at the field level across relations.

In order to add changelog support an extension must first be applied, then extra
change-loggable fields registered, and then any forms used to edit a
change-loggable model can be transformed to include changelog fields.

Change logging on all simple field types (TextField, DropdownField, ...) is
supported, as well as logging acrpss `has_many` or `many_many` relationships via
a TableField.

_Note: Change logging via a ComplexTableField is not supported._

Enabling Change Logging
-----------------------
In order to set up a DataObject for change logging, all that is needed is to
apply the relevant changelog extension. By default all fields present in the
model's sumary fields will have the ability to be changelogged.

	Object::add_extension('SiteTree', 'ChangelogExtension');

_Note: Change-loggable DataObjects must also have the Versioned extension._

Registering Fields
------------------
In order to enable change logging on additional fields they will need to be
registered. Each change-loggable model has an attached ChangelogConfig
instance, which can be retrieved using the `get` function, or calling a method
on a change-loggable DataObject:

	$config = ChangelogConfig::get($this->class);
	$config = $this->getChangelogConfig();

To register additional fields either `registerField` to register one field at
a time, or `registerFields` to register a set of field names can be called. The
first argument is the field name(s), and the second an array of options.

	$config->registerField('FieldName', array('option'));
	$config->registerFields(array('FieldName', ...), array('option'));

The array of options passed to the register methods can consist of the
following:

title
:	An element with a key of "title" will be used as a custom field title in the
	changelog modal prompt, as well as in the table field which summarises
	entered changelog summaries. This defaults to the form field title.

prompt
:	This prompts the used using an optional modal dialog to enter a changelog
	message when a field is changed.

required
:	If this is set for a field, then when it is changed a changelog message will
	need to be entered before the record can be saved. This also enables the
	`prompt` option.

### Configuration Examples

	$config->registerField('Title', array('title' => 'Page title', 'required'))
	$config->registerFields(array('MenuTitle', 'URLSegment'))

Adding Changelog Support To Forms
---------------------------------
Whenever a change-loggable page type is managed via the CMS, it will
automatically have a Changelog tab where changelog summaries can be entered, and
past changelog items viewed.

In order to add changelog support to custom forms, it is a simple matter of
passing the root FieldSet through a FormTransformation:

	/**
	 * Returns a form used to edit a Car object in the front-end.
	 *
	 * @return Form
	 */
	public function EditCarForm() {
		$fields  = $this->getFrontendFields();
		$actions = new FieldSet(new FormAction('save', 'Save Car'));

		$transform = new ChangelogTransformation($this->data);
		$fields    = $transform->transformFieldSet($fields);

		return new Form($this, 'EditCarForm', $fields, $actions);
	}

Now this form will have all the neccesary JavaScript to prompt users to enter
changelog messages, as well as edit them later and view previous changelogs.