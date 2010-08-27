<?php
/**
 * A form validator that ensures all fields changed that require changelog
 * messages have them
 *
 * @package silverstripe-changelog
 */
class ChangelogValidator extends Validator {
	protected $enabled = true;

	/**
	 * @return bool
	 */
	public function php($data) {
		if (!$this->enabled) {
			return true;
		}
		$record    = $this->form->getRecord();
		$config    = $record->getChangelogConfig();
		$relations = $config->getRelations();
		$fields    = array_keys($this->form->Fields()->dataFields());
		$messages  = ChangelogUtil::data_to_messages($data['FieldChangelogs']);

		// loop through each field, and if it is changed and a message required
		// ensure we have one
		foreach ($config->getFields() as $name => $settings) {
			if (!in_array('required', $settings)) continue;
			if (!in_array($name, $fields)) continue;

			$original = $record->$name;
			$current  = $data[$name];
			$hasMsg   = isset($messages['root'][$name]) && strlen($messages['root'][$name]);

			if ($original != $current && !$hasMsg) {
				$this->validationError(
					'FieldChangelogs',
					sprintf('You must enter a changelog message for "%s"', $name),
					'required'
				);
				return false;
			}
		}

		// loop through each relation and each record and do the same
		foreach ($config->getRelations() as $relation => $class) {
			if (!in_array($relation, $fields)) continue;
			
			$table  = $this->form->dataFieldByName($relation);
			$fields = array_keys($table->getFieldTypes());
			$class  = $table->sourceClass();
			$config = ChangelogConfig::get($class);

			if (isset($data[$relation])) foreach ($data[$relation] as $id => $item) {
				// dont validate new records
				if (!is_numeric($id)) continue;
				$record = DataObject::get_by_id($class, $id);

				// loop through each field
				foreach ($config->getFields() as $field => $settings) {
					if (!in_array('required', $settings)) continue;
					if (!in_array($name, $fields)) continue;

					$original = $record->$name;
					$current  = $item[$name];
					$hasMsg   = isset($messages[$relation][$id][$name]) && strlen($messages[$relation][$id][$name]);

					if ($original != $current && !$hasMsg) {
						$this->validationError(
							'FieldChangelogs',
							sprintf('You must enter a changelog message for "%s"', $name),
							'required'
						);
						return false;
					}
				}
			}
		}

		return true;
	}


	/**
	 * Clears all the validation from this object.
	 */
	public function removeValidation(){
		$this->enabled = false;
	}

	/**
	 * @ignore
	 */
	public function javascript() { return null; }

}