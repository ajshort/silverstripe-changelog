<% require css(changelog/css/ChangelogDialog.css) %>

<div id="ChangelogDialog" title="Changelog Edit Summary Message">
	<h2>Field Change</h2>

	<h3>Original Value</h3>
	<p class="original">
	</p>

	<h3>Changed Value</h3>
	<p class="changed">
	</p>

	<h3>Edit Message/Summary</h3>
	<p>Please enter a short edit summary message describing why you have
	made this field change:</p>

	<p id="RequiredMessage" class="message">Note: you must enter a message to
	save this record.</p>

	<div class="field">
		<label class="left">Edit summary</label>
		<div class="middleColumn">
			<input class="text" type="text" name="EditSummary">
		</div>
	</div>
</div>