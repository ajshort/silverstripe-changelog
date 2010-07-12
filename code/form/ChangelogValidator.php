<?php
/**
 * A form validator that ensures all fields changed that require changelog
 * messages have them
 *
 * @package silverstripe-changelog
 */
class ChangelogValidator extends Validator {

	/**
	 * @return bool
	 */
	public function php($data) {
		$record   = $this->form->getRecord();
		$config   = $record->getChangelogConfig();
		$changed  = $record->getChangedFields(true, 2);
		$messages = array();
		$required = array();

		if (isset($data['FieldChangelogs']['new'])) {
			foreach(ArrayLib::invert($data['FieldChangelogs']['new']) as $item) {
				$messages[$item['FieldName']] = $item['EditSummary'];
			}
		}

		foreach ($config->getFields() as $name => $options) {
			if (in_array('required', $options)) $required[] = $name;
		}

		foreach ($required as $name) {
			$original = $record->$name;
			$current  = $data[$name];

			if ($original != $current && !strlen($messages[$name])) {
				$this->validationError('FieldChangelogs', sprintf(
					'You must enter a changelog message for the "%s" field.',
					$name
				), 'required');

				return false;
			}
		}

		return true;
	}

	/**
	 * @ignore
	 */
	public function javascript() { return null; }

}