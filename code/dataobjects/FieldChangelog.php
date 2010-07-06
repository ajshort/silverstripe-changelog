<?php
/**
 * Contains a changelog message for a single field change.
 *
 * @package silverstripe-changelog
 */
class FieldChangelog extends DataObject {

	public static $db = array(
		'FieldName'   => 'Varchar(100)',
		'Original'    => 'Text',
		'Changed'     => 'Text',
		'EditSummary' => 'Varchar(255)'
	);

	public static $has_one = array(
		'Changelog' => 'Changelog'
	);

	public static $summary_fields = array(
		'FieldName'       => 'Field',
		'OriginalSummary' => 'Original',
		'ChangedSummary'  => 'Changed',
		'EditSummary'     => 'Edit Summary'
	);

	/**
	 * Returns the DB field type this changelog corresponds to.
	 *
	 * @return string
	 */
	public function getFieldType() {
		return $this->Changelog()->getSubject()->db($this->FieldName);
	}

	/**
	 * @return string
	 */
	public function getOriginalSummary() {
		return $this->getFieldSummary('Original');
	}

	/**
	 * @return string
	 */
	public function getChangedSummary() {
		return $this->getFieldSummary('Changed');
	}

	/**
	 * @param  string $field
	 * @return string
	 */
	public function getFieldSummary($field) {
		$field = DBField::create($this->getFieldType(), $this->$field);

		if (method_exists($field, 'LimitCharacters')) {
			return $field->LimitCharacters(30);
		} else {
			return $field;
		}
	}

}