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
	 * @return ChangelogConfig
	 */
	public function getChangelogConfig() {
		return ChangelogConfig::get($this->owner->class);
	}

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
			$this->current->VersionNum   = $this->owner->Version;
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
			'"SubjectClass" = \'%s\' AND "SubjectID" = %d AND "VersionNum" = %d',
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
		$relations = $this->getChangelogConfig()->getRelations();
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

		foreach ($relations as $relation => $class) {
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
			$log->SubjectID  = $this->owner->ID;
			$log->VersionNum = $this->owner->Version;
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
			foreach ($this->getChangelogConfig()->getRelations() as $relation => $class) {
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
			$changes  = $this->owner->getChangedFields(true, 2);
			$loggable = $this->getChangelogConfig()->getFields();

			foreach ($changes as $field => $change) {
				if (!array_key_exists($field, $loggable)) continue;

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
		$transform = new ChangelogTransformation($this->owner);
		$transform->transformFieldSet($fields);
	}

	/**
	 * @return ChangelogValidator
	 */
	public function getCMSValidator() {
		return new ChangelogValidator();
	}

}