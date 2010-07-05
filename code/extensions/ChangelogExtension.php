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

		$log = $this->getNextChangelog();
		$log->SubjectID = $this->owner->ID;
		$log->Version   = $this->owner->Version;
		$log->write();

		$this->current = $log;
		$this->next    = null;
	}

	/**
	 * @param FieldSet $fields
	 */
	public function updateCMSFields($fields) {
		$fields->addFieldsToTab('Root.Changelog', array(
			new HeaderField('ChangelogHeader', 'Changelog'),
			new TextField('EditSummary', 'Edit summary')
		));
	}

}