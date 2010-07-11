<?php
/**
 * Contains a set of messages describing the changes made on a specific version
 * of a {@link ChangelogExtension} data object.
 *
 * @package silverstripe-changelog
 */
class Changelog extends DataObject {

	public static $db = array(
		'SubjectClass' => 'Varchar(80)',
		'SubjectID'    => 'Int',
		'Version'      => 'Int',
		'EditSummary'  => 'Varchar(255)'
	);

	public static $indexes = array(
		'SubjectClass' => true,
		'SubjectID'    => true,
		'Version'      => true
	);

	public static $has_one = array(
		'Parent' => 'Changelog'
	);

	public static $has_many = array(
		'Children'        => 'Changelog',
		'FieldChangelogs' => 'FieldChangelog'
	);

	public static $default_sort = 'Created DESC';

	public static $summary_fields = array(
		'Version',
		'Created',
		'EditSummary'
	);

	/**
	 * Returns the data object this is attached to.
	 *
	 * @return DataObject
	 */
	public function getSubject() {
		return Versioned::get_version(
			$this->SubjectClass, $this->SubjectID, $this->Version
		);
	}

	/**
	 * Returns the most recent version of the subject this is attached to.
	 *
	 * @return DataObject
	 */
	public function getLatestSubject() {
		return DataObject::get_by_id($this->SubjectClass, $this->SubjectID);
	}

	/**
	 * @return DataDifferencer
	 */
	public function diffWithPrevious() {
		if ($this->Version > 1) {
			$from = Versioned::get_version(
				$this->SubjectClass, $this->SubjectID, $this->Version - 1
			);
		} else {
			$from = null;
		}

		$diff = new DataDifferencer($from, $this->getSubject());
		$diff->ignoreFields('LastEdited', 'WasPublished');

		return $diff;
	}

	/**
	 * @return FieldSet
	 */
	public function getCMSFields() {
		$subject   = $this->getSubject();
		$diff      = $this->diffWithPrevious();
		$relations = $subject->getChangelogConfig()->getRelations();

		$fields = new FieldSet(new TabSet('Root',
			new Tab('Main',
				new NumericField('Version', 'Version'),
				new DateField('Created', 'Created'),
				new TextField('EditSummary', 'Edit summary'),
				new CheckboxField('WasPublished', 'Was published?')
			),
			new Tab('Detail',
				new HeaderField('FieldChangelogHeader', 'Field Change Log'),
				new TableListField(
					'FieldChangelogs', 'FieldChangelog', null,
					'"ChangelogID" = ' . $this->ID
				),
				new ToggleCompositeField('ViewDiff', 'View Differences', array(
					new LiteralField('Diff', $diff->renderWith('ChangelogDiff'))
				))
			)
		));

		// also add a section for each relationship that is also logged
		foreach ($relations as $relation) {
			$children = DataObject::get(
				'Changelog', '"ParentID" = ' . $this->ID
			);

			if ($children) foreach ($children as $changed) {
				$diff   = $changed->diffWithPrevious();
				$name   = $relation . $changed->ID;

				$fields->addFieldsToTab("Root.$relation", array(
					new HeaderField("{$name}Title", $changed->getSubject()->Title),
					new TableListField(
						"{$name}FieldChangelogs", 'FieldChangelog', null,
						'"ChangelogID" = ' . $changed->ID
					),
					new ToggleCompositeField("{$name}ViewDiff", 'View Differences',
						array(new LiteralField(
							"{$name}Diff", $diff->renderWith('ChangelogDiff')
						))
					)
				));
			}
		}

		return $fields;
	}

	public function getRequirementsForPopup() {
		Requirements::css('changelog/css/ChangelogDiff.css');
	}

}