<tr class="contentnotes_formrow">
	<td class="trow2" valign="top" width="20%"><strong>{$lang->contentnotes_form_title}</strong><br /><span class="smalltext">{$lang->contentnotes_form_hint}</span></td>
	<td class="trow2">
		<input type="hidden" name="contentnotes_form" value="1" />
		<details class="contentnotes_details"{$open_attr}>
			<summary>{$lang->contentnotes_form_summary}</summary>
			<div class="contentnotes_form_rows">
				{$rows}
			</div>
		</details>
	</td>
</tr>
