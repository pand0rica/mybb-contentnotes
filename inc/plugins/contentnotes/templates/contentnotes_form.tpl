<div class="h4w-post-item contentnotes_formrow"><strong>{$lang->contentnotes_form_title}</strong><br /><span class="smalltext">{$lang->contentnotes_form_hint}</span></div>
<div class="h4w-post-item contentnotes_formrow">
	<input type="hidden" name="contentnotes_form" value="1" />
	<details class="contentnotes_details"{$open_attr}>
		<summary>{$lang->contentnotes_form_summary}</summary>
		<div class="contentnotes_form_rows">
			{$rows}
		</div>
	</details>
</div>
