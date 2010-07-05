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

	/**
	 * Returns the data object this changelog is attached to. Note that this is
	 * used instead of the default has_one implementation to allow connections
	 * to multiple classes.
	 *
	 * @return DataObject
	 */
	public function getSubject() {
		return DataObject::get_by_id($this->SubjectClass, $this->SubjectID);
	}

}