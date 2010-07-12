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
	public static function get($class) {
		if (!array_key_exists($class, self::$configs)) {
			self::$configs[$class] = new ChangelogConfig($class);
		}

		return self::$configs[$class];
	}

	/**
	 * @param string $subjectClass
	 */
	public function __construct($subjectClass) {
		$fields  = array();
		$summary = singleton($subjectClass)->summaryFields();

		if ($summary) foreach ($summary as $field => $title) {
			$fields[$field] = array('title' => $title);
		}

		$this->fields       = $fields;
		$this->subjectClass = $subjectClass;
	}

	/**
	 * @return string
	 */
	public function getSubjectClass() {
		return $this->subjectClass;
	}

	/**
	 * Returns all fields that should be change logged, as well as their
	 * options.
	 *
	 * @return array A map of field name to an array of options.
	 */
	public function getFields() {
		$fields   = $this->fields;
		$ancestry = array_reverse(ClassInfo::ancestry($this->subjectClass));

		array_shift($ancestry);

		foreach ($ancestry as $ancestor) {
			if (!is_subclass_of($ancestor, 'DataObject')) break;

			$fields = array_merge_recursive(
				self::get($ancestor)->getFields(), $fields
			);
		}

		return $fields;
	}

	/**
	 * Registers a field to be change logged, along with additional options.
	 *
	 * @param string $name
	 * @param array  $options
	 */
	public function registerField($name, array $options = array()) {
		$this->fields = array_merge_recursive($this->fields, array(
			$name => $options
		));
	}

	/**
	 * Registers multiple fields to be changelogged, all with the same settings.
	 *
	 * @param array $names
	 * @param array $options
	 */
	public function registerFields(array $names, array $options = array()) {
		foreach ($names as $name) $this->registerField($name, $options);
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