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

	/**
	 * Returns the data object this changelog is attached to.
	 *
	 * @return DataObject
	 */
	public function getSubject() {
		return Versioned::get_version(
			$this->SubjectClass, $this->SubjectID, $this->Version
		);
	}

}