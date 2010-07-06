<?php
/**
 * Allows a data object to have each version annotated with a change log.
 *
 * @package silverstripe-changelog
 */
class ChangelogExtension extends DataObjectDecorator {

	/**
	 * @var Changelog
	 */
	protected $current, $next;

	/**
	 * @var array
	 */
	protected $messages = array();

	/**
	 * Returns the {@link Changelog} object associated with the current version.
	 * If one does not exist a new object is returned but not written.
	 *
	 * @return Changelog
	 */
	public function getChangelog() {
		if (!$this->current) {
			// attempt to load an existing changelog from the database
			if ($this->owner->ID && $this->owner->Version) {
				$log = $this->getChangelogForVersion($this->owner->Version);

				if ($log) {
					return ($this->current = $log);
				}
			}

			// create a new blank changelog
			$this->current = new Changelog();
			$this->current->SubjectClass = $this->owner->class;
			$this->current->SubjectID    = $this->owner->ID;
			$this->current->Version      = $this->owner->Version;
		}

		return $this->current;
	}

	/**
	 * Returns the changelog object for a specific object if it exists.
	 *
	 * @param  int $version
	 * @return Changelog
	 */
	public function getChangelogForVersion($version) {
		return DataObject::get_one('Changelog', sprintf(
			'"SubjectClass" = \'%s\' AND "SubjectID" = %d AND "Version" = %d',
			$this->owner->class,
			$this->owner->ID,
			$version
		));
	}

	/**
	 * Returns the changelog object that will be written with the creation of
	 * the next new version.
	 *
	 * @return Changelog
	 */
	public function getNextChangelog() {
		if (!$this->next) {
			$this->next = new Changelog();
			$this->next->SubjectClass = $this->owner->class;
			$this->next->SubjectID    = $this->owner->ID;
		}

		return $this->next;
	}

	/**
	 * @param string $summary
	 */
	public function saveEditSummary($summary) {
		$this->getNextChangelog()->EditSummary = $summary;
	}

	/**
	 * Stores the field edit summaries for later use.
	 *
	 * @param array $raw
	 */
	public function saveFieldChangelogs($raw) {
		$raw        = ArrayLib::invert($raw['new']);
		$messages   = array();

		foreach ($raw as $data) if ($data['FieldName']) {
			$messages[$data['FieldName']] = $data['EditSummary'];
		}

		$this->messages = $messages;
	}

	/**
	 * Writes a new changelog record if a version has been created.
	 */
	public function onAfterWrite() {
		$changed = $this->owner->isChanged('Version');
		$exists  = $this->getChangelogForVersion($this->owner->Version);

		// ensure the owner has the versioned extension
		if (!Object::has_extension($this->owner->class, 'Versioned')) {
			throw new Exception('The Changelog extension requires Versioned.');
		}

		// make sure not to create a changelog entry for version migrations
		if (!$this->owner->Version || !$changed || $exists) {
			return;
		}

		// create the new main changelog entry
		$log = $this->getNextChangelog();
		$log->SubjectID = $this->owner->ID;
		$log->Version   = $this->owner->Version;
		$log->write();

		// and then create a field changelog entry for each field change, unless
		// we have a new record
		if (!$this->owner->isChanged('ID')) {
			$changes = $this->owner->getChangedFields(true, 2);

			foreach ($changes as $field => $change) {
				if ($field == 'Version') continue;

				$fieldLog = new FieldChangelog();
				$fieldLog->FieldName   = $field;
				$fieldLog->Original    = $change['before'];
				$fieldLog->Changed     = $change['after'];

				if (isset($this->messages[$field])) {
					$fieldLog->EditSummary = $this->messages[$field];
				}

				$log->FieldChangelogs()->add($fieldLog);
			}
		}

		$this->current = $log;
		$this->next    = null;
	}

	/**
	 * @param FieldSet $fields
	 */
	public function updateCMSFields($fields) {
		$names = ArrayLib::valuekey(array_merge(
			array_keys($this->owner->inheritedDatabaseFields()),
			array('ClassName', 'LastEdited')
		));

		foreach ($names as $name) {
			if ($title = $this->owner->fieldLabel($name)) {
				$names[$name] = $title;
			}
		}

		$fieldNames = new DropdownField(
			'FieldName', '', $names, '', null, '(choose)'
		);

		$filter = sprintf(
			'"SubjectClass" = \'%s\' AND "SubjectID" = %d',
			$this->owner->class, $this->owner->ID
		);

		$fields->addFieldsToTab('Root.Changelog', array(
			new HeaderField('ChangelogHeader', 'Changelog'),
			new TextField('EditSummary', 'Edit summary'),
			new HeaderField('FieldChangelogHeader', 'Field Change Log'),
			$fields = new TableField('FieldChangelogs', 'FieldChangelog', array(
				'FieldName'   => 'Field',
				'Original'    => 'Original Value',
				'Changed'     => 'Changed Value',
				'EditSummary' => 'Edit Summary'
			), array(
				'FieldName'   => $fieldNames,
				'Original'    => 'ReadonlyField',
				'Changed'     => 'ReadonlyField',
				'EditSummary' => 'TextField'
			), null, null, false),
			new ToggleCompositeField('PastChangelogs', 'Past Changelogs', array(
				$past = new ComplexTableField(
					$this, 'Changelogs', 'Changelog', null, null, $filter
				)
			))
		));

		$fields->setCustomSourceItems(new DataObjectSet());
		$fields->setPermissions(array('add'));
		$past->setPermissions(array('show'));
	}

}