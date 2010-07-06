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

	public static $has_many = array(
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
	 * @return FieldSet
	 */
	public function getCMSFields() {
		if ($this->Version > 1) {
			$from = Versioned::get_version(
				$this->SubjectClass, $this->SubjectID, $this->Version - 1
			);
		} else {
			$from = null;
		}

		$diff = new DataDifferencer($from, $this->getSubject());
		$diff->ignoreFields('LastEdited', 'WasPublished');

		return new FieldSet(new TabSet('Root', new Tab('Main',
			new HeaderField('ChangelogHeader', 'Changelog'),
			new NumericField('Version', 'Version'),
			new DateField('Created', 'Created'),
			new TextField('EditSummary', 'Edit summary'),
			new CheckboxField('WasPublished', 'Was published?'),
			new HeaderField('FieldChangelogHeader', 'Field Change Log'),
			new TableListField(
				'FieldChangelogs', 'FieldChangelog', null,
				'"ChangelogID" = ' . $this->ID
			),
			new ToggleCompositeField('ViewDiffHeader', 'View Differences', array(
				new LiteralField('Diff', $diff->renderWith('ChangelogDiff'))
			))
		)));
	}

	public function getRequirementsForPopup() {
		Requirements::css('changelog/css/ChangelogDiff.css');
	}

}