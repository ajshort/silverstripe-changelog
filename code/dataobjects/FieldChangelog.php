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

}