<tr>
	<td class="{$trow}" valign="top" width="50%"><strong>{$name}</strong>{$description_html}</td>
	<td class="{$trow}" valign="top">
		<label><input type="radio" class="radio" name="contentnotes_mode[{$cid_value}]" value="0"{$checked_warn} /> {$lang->contentnotes_mode_warn}</label><br />
		<label><input type="radio" class="radio" name="contentnotes_mode[{$cid_value}]" value="1"{$checked_show} /> {$lang->contentnotes_mode_show}</label>
	</td>
</tr>
