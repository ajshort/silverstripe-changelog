<?php
/**
 * Contains various utility functions used in the changelog module.
 *
 * @package silverstripe-changelog
 */
class ChangelogUtil {

	/**
	 * Converts raw table field data into an array of edit summary messages.
	 *
	 * @param  array $data
	 * @return array
	 */
	public static function data_to_messages($data) {
		$messages = array();

		if (isset($data['new']) && is_array($data['new'])) {
			foreach (ArrayLib::invert($data['new']) as $message) {
				$name    = $message['FieldName'];
				$summary = $message['EditSummary'];

				$messages['root'][$name] = $summary;
			}
		}

		if (is_array($data)) foreach ($data as $type => $data) {
			if ($type == 'new') continue;
			$messages[$type] = array();

			foreach ($data as $id => $data) {
				$messages[$type][$id] = array();
				foreach (ArrayLib::invert($data) as $message) {
					$name    = $message['FieldName'];
					$summary = $message['EditSummary'];

					$messages[$type][$id][$name] = $summary;
				}
			}
		}

		return $messages;
	}

}