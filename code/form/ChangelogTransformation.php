<?php
/**
 * Transforms an existing FieldSet to add changelog support.
 *
 * @package silverstripe-changelog
 */
class ChangelogTransformation extends FormTransformation {

	/**
	 * @var DataObject
	 */
	protected $record;

	/**
	 * @var ChangelogConfig
	 */
	protected $config;

	/**
	 * @var array
	 */
	protected $fields = array(), $relations = array();

	/**
	 * @param DataObject $record
	 * @param ChangelogConfig $config
	 */
	public function __construct($record, $config = null) {
		if (!$config) {
			$config = ChangelogConfig::get($record->class);
		}

		$this->record    = $record;
		$this->config    = $config;
		$this->fields    = $config->getFields();
		$this->relations = $config->getRelations();

		parent::__construct();
	}

	/**
	 * A working  partialimplementation of {@link FormTransformation::transform()},
	 * as the default 2.4 one is broken.
	 */
	public function transform(FormField $field) {
		if ($field instanceof TableField) {
			return $this->transformTableField($field);
		} elseif ($field instanceof CompositeField) {
			return $this->transformCompositeField($field);
		} else {
			return $this->transformFormField($field);
		}
	}

	/**
	 * Transforms a FieldSet by adding changelog form fields and annotating
	 * fields that should be logged.
	 *
	 * This method should be called to add changelog support rather than
	 * passing this object into {@link FieldSet->transform()}.
	 *
	 * @param  FieldSet $fields
	 * @return FieldSet
	 */
	public function transformFieldSet($fields) {
		$fields = clone $fields;
		$extra  = $this->getChangelogFormFields();

		if ($fields->hasTabSet()) {
			$fields->addFieldsToTab('Root.Changelog', $extra);
		} else {
			foreach ($extra as $extraField) $fields->push($extraField);
		}

		return $fields->transform($this);
	}

	public function transformFormField($field) {
		$name        = $field->Name();
		$isComposite = $field->isComposite();
		$isLoggable  = array_key_exists($name, $this->fields);

		if (!$isComposite && $isLoggable) {
			$this->annotateField($field, $this->fields[$name]);
		}

		return $field;
	}

	public function transformCompositeField($field) {
		$transformed = new FieldSet();
		$clone = clone $field;

		foreach ($field->getChildren() as $id => $child) {
			$transformed->push($child->transform($this), $id);
		}

		$clone->setChildren($transformed);
		return $clone;
	}

	/**
	 * Loops through each table row and adds a class to each field on an
	 * existing record that has logging enabled.
	 *
	 * @param  TableField $table
	 * @return TableField
	 */
	public function transformTableField($table) {
		if (!array_key_exists($table->Name(), $this->relations)) {
			return $table;
		}

		$source    = $table->sourceClass();
		$config    = ChangelogConfig::get($source);
		$fields    = $table->FieldSetForRow();
		$loggable  = $config->getFields();
		$newFields = array();

		foreach ($fields->dataFields() as $name => $field) {
			if (array_key_exists($name, $loggable)) {
				$this->annotateField($field, $loggable[$name]);
			}

			$newFields[$name] = $field;
		}

		$table->setFieldTypes($newFields);
		return $table;
	}

	protected function annotateField($field, $config) {
		$field->addExtraClass('changelog');

		if (in_array('prompt', $config)) {
			$field->addExtraClass('changelog-prompt');
		}

		if (in_array('required', $config)) {
			$field->addExtraClass('changelog-required');
		}

		if (array_key_exists('title', $config)) {
			$title = $config['title'];
		} elseif ($field->Title()) {
			$title = $field->Title();
		}

		if (isset($title)) $field->addExtraClass(sprintf(
			'{title:\'%s\'}', Convert::raw2js($title)
		));
	}

	/**
	 * Returns an array of all form fields to add changelog support to a form.
	 *
	 * @return array
	 */
	protected function getChangelogFormFields() {
		Requirements::javascript(THIRDPARTY_DIR . '/jquery/jquery.js');
		Requirements::javascript(THIRDPARTY_DIR . '/jquery-metadata/jquery.metadata.js');
		Requirements::javascript(THIRDPARTY_DIR . '/jquery-ui/jquery-ui-1.8rc3.custom.js');
		Requirements::javascript(CHANGELOG_DIR  . '/javascript/ChangelogForm.js');
		Requirements::css(THIRDPARTY_DIR . '/jquery-ui-themes/base/jquery.ui.all.css');

		$fieldLogs = new TableField(
			'FieldChangelogs', 'FieldChangelog', null,
			array(
				'FieldName'       => 'ReadonlyField',
				'OriginalSummary' => 'DatalessField',
				'ChangedSummary'  => 'DatalessField',
				'EditSummary'     => 'TextField'
			)
		);
		$fieldLogs->setCustomSourceItems(new DataObjectSet());
		$fieldLogs->setPermissions(array('show', 'edit'));
		$fieldLogs->showAddRow = false;

		$pastLogs = new ComplexTableField(
			$this->record, 'Changelogs', 'Changelog', null, null, sprintf(
				'"SubjectClass" = \'%s\' AND "SubjectID" = %d',
				$this->record->class, $this->record->ID
			)
		);
		$pastLogs->setPermissions(array('show'));

		return array(
			new HeaderField('ChangelogHeader', 'Changelog'),
			new TextField('EditSummary', 'Edit summary'),
			new ToggleCompositeField(
				'FieldChangelogs', 'Field Changelogs', array($fieldLogs)
			),
			new ToggleCompositeField(
				'PastChangelogs', 'Past Changelogs', array($pastLogs)
			),
			new LiteralField(
				'ChangelogDialog', $this->record->renderWith('ChangelogDialog')
			)
		);
	}

}