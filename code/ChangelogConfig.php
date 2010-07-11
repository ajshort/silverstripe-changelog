<?php
/**
 * Contains field and relationship configuration for a specific model class.
 *
 * @package silverstripe-changelog
 */
class ChangelogConfig {

	/**
	 * @var ChangelogConfig[]
	 */
	protected static $configs = array();

	/**
	 * @var string
	 */
	protected $subjectClass;

	/**
	 * @var array
	 */
	protected $fields = array();

	/**
	 * Returns the changelog config object for a model class.
	 *
	 * @param  string $class
	 * @return ChangelogConfig
	 */
	public static function get_for_class($class) {
		if (!array_key_exists($class, self::$configs)) {
			self::$configs[$class] = new ChangelogConfig($class);
		}

		return self::$configs[$class];
	}

	/**
	 * @param string $subjectClass
	 */
	public function __construct($subjectClass) {
		$this->subjectClass = $subjectClass;
	}

	/**
	 * @return string
	 */
	public function getSubjectClass() {
		return $this->subjectClass;
	}

	/**
	 * Returns field names that should have change logging enabled.
	 *
	 * @return array
	 */
	public function getFields() {
		$ancestry = ClassInfo::ancestry($this->subjectClass);
		$fields   = (array) $this->getCustomFields();

		array_shift($ancestry);

		foreach ($ancestry as $ancestor) {
			$fields += self::get_for_class($ancestor)->getCustomFields();
		}

		return array_unique($fields);
	}

	/**
	 * Returns field names that have been explicity set on only this class.
	 *
	 * @return array
	 */
	public function getCustomFields() {
		$result = $this->fields;

		// include summary fields on the root class for some sensible defaults
		if (get_parent_class($this->subjectClass) == 'DataObject') {
			$result += singleton($this->subjectClass)->summaryFields();
		}

		return $result;
	}

	/**
	 * Registers one or more fields to be change logged.
	 *
	 * @param string,... $name One or more field names to enable
	 */
	public function registerFields() {
		$args = func_get_args();
		if (is_array($args[0])) $args = $args[0];

		foreach ($args as $field) {
			if (!array_key_exists($field, $this->fields)) {
				$this->fields[$field] = $field;
			}
		}
	}

	/**
	 * Returns has_many relationships that should have their children change
	 * logged.
	 *
	 * @return array
	 */
	public function getRelations() {
		$relations = singleton($this->subjectClass)->has_many();
		$result    = array();

		if ($relations) foreach ($relations as $relation => $class) {
			$isVersions   = $relation == 'Versions';
			$hasExtension = Object::has_extension($class, 'ChangelogExtension');

			if ($hasExtension && !$isVersions) $result[$relation] = $class;
		}

		return $result;
	}

}