<?php
/**
 * Allows a data object to have each version annotated with a change log.
 *
 * @package silverstripe-changelog
 */
class ChangelogExtension extends DataObjectDecorator {

	/**
	 * Contains all changelogs that have been written, and are not root
	 * changelogs but also have no parent. This is used for later processing.
	 *
	 * @var Changelog[]
	 */
	protected static $orphans = array();

	/**
	 * @var Changelog
	 */
	protected $current, $next;

	/**
	 * @var array
	 */
	protected $messages = array();

	/**
	 * TRUE if this changelog is a root changelog (i.e. all changelogs written
	 * should fall under this changelog).
	 *
	 * @var bool
	 */
	protected $isRoot = false;

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
	 * Returns an array of all form fields to add changelog support to a form.
	 *
	 * @return array
	 */
	public function getChangelogFormFields() {
		Requirements::javascript(THIRDPARTY_DIR . '/jquery/jquery.js');
		Requirements::javascript(CHANGELOG_DIR  . '/javascript/ChangelogForm.js');

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
			$this, 'Changelogs', 'Changelog', null, null, sprintf(
				'"SubjectClass" = \'%s\' AND "SubjectID" = %d',
				$this->owner->class, $this->owner->ID
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
			)
		);
	}

	/**
	 * Annotates all fields in a FieldSet that are changeloggable.
	 *
	 * @param FieldSet $fields
	 */
	public function annotateChangelogFields($fields) {
		$names = array_merge(
			array_keys($this->owner->inheritedDatabaseFields()),
			array('ClassName', 'LastEdited')
		);

		// annotate simple database fields - they can all be tracked
		foreach ($names as $name) {
			if ($field = $fields->dataFieldByName($name)) {
				$field->addExtraClass('changelog');
			}
		}

		// also annotate tablefields that correspond to a has_many relationship
		// which also has this extension applied
		foreach ($this->getChangelogRelations() as $relation) {
			$field = $fields->dataFieldByName($relation);

			if ($field instanceof TableField) {
				$field->addExtraClass('relation-changelog');
			}
		}
	}

	/**
	 * Returns all relations that have changelog support. At the moment this
	 * is limited to has_many.
	 *
	 * @return array
	 */
	public function getChangelogRelations() {
		$relations = array();

		foreach ($this->owner->has_many() as $relation => $class) {
			if($relation == 'Versions') continue;
			if(!Object::has_extension($class, 'ChangelogExtension')) continue;

			$relations[] = $relation;
		}

		return $relations;
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
	public function saveFieldChangelogs($raw, $form) {
		$relations = $this->getChangelogRelations();
		$messages  = array();

		// assume that this method being called means this object is being
		// directly saved form a form, so it's the root changelog
		$this->isRoot = true;

		if (isset($raw['new'])) {
			foreach (ArrayLib::invert($raw['new']) as $item) {
				$field   = $item['FieldName'];
				$message = $item['EditSummary'];

				$this->messages['root'][$field] = $message;
			}
		}

		foreach ($this->getChangelogRelations() as $relation) {
			$this->messages[$relation] = array();

			if (isset($raw[$relation])) foreach ($raw[$relation] as $id => $raw) {
				$this->messages[$relation][$id] = array();

				foreach (ArrayLib::invert($raw) as $item) {
					$field   = $item['FieldName'];
					$message = $item['EditSummary'];

					$this->messages[$relation][$id][$field] = $message;
				}
			}
		}
	}

	/**
	 * Writes a new changelog record if a version has been created.
	 */
	public function onAfterWrite() {
		$changed   = $this->owner->isChanged('Version');
		$exists    = $this->getChangelogForVersion($this->owner->Version);
		$createNew = $this->owner->Version && $changed && !$exists;
		$messages  = $this->messages;

		// ensure the owner has the versioned extension
		if (!Object::has_extension($this->owner->class, 'Versioned')) {
			throw new Exception('The Changelog extension requires Versioned.');
		}

		if ($createNew) {
			// create the new main changelog entry
			$log = $this->getNextChangelog();
			$log->SubjectID = $this->owner->ID;
			$log->Version   = $this->owner->Version;
			$log->write();
		} else {
			$log = $this->getChangelog();
		}

		if ($this->isRoot) {
			// if we are the root changelog object, write all the orphans parent
			// ids to point to this
			foreach (self::$orphans as $key => $orphan) {
				$orphan->getChangelog()->ParentID = $log->ID;
				$orphan->getChangelog()->write();

				unset(self::$orphans[$key]);
			}

			// also loop through each child relation and write any child
			// changelog messages
			foreach ($this->getChangelogRelations() as $relation) {
				$class = $this->owner->has_many($relation);

				if (isset($messages[$relation])) {
					foreach ($messages[$relation] as $id => $messages) {
						if (!$child = DataObject::get_by_id($class, $id)) {
							continue;
						}

						foreach ($messages as $field => $message) {
							$fieldLog = DataObject::get_one('FieldChangelog', sprintf(
								'"FieldName" = \'%s\' AND "ChangelogID" = %d',
								Convert::raw2sql($field),
								$child->getChangelog()->ID
							));

							if ($fieldLog) {
								$fieldLog->EditSummary = $message;
								$fieldLog->write();
							}
						}
					}
				}
			}
		} elseif ($createNew) {
			// if this is not the root record, add it to the orphans and assume
			// the root record will populate it later
			self::$orphans[] = $this->owner;
		}

		// and then create a field changelog entry for each field change, unless
		// we have a new record
		if ($createNew && !$this->owner->isChanged('ID')) {
			$changes = $this->owner->getChangedFields(true, 2);
			$db      = array_keys($this->owner->db());

			foreach ($changes as $field => $change) {
				if (!in_array($field, $db) || $field == 'Version') continue;

				$fieldLog = new FieldChangelog();
				$fieldLog->FieldName   = $field;
				$fieldLog->Original    = $change['before'];
				$fieldLog->Changed     = $change['after'];

				if ($this->isRoot && isset($messages['root'][$field])) {
					$fieldLog->EditSummary = $messages['root'][$field];
				}

				$log->FieldChangelogs()->add($fieldLog);
			}
		}

		// since all the parent ids have been set, we are no longer the
		// root record
		$this->isRoot   = false;
		$this->messages = array();

		if ($createNew) {
			$this->current = $log;
			$this->next    = null;
		}
	}

	/**
	 * @param FieldSet $fields
	 */
	public function updateCMSFields($fields) {
		$this->annotateChangelogFields($fields);
		$fields->addFieldsToTab('Root.Changelog', $this->getChangelogFormFields());
	}

}