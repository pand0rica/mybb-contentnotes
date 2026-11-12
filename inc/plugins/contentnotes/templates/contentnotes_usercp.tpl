<html>
<head>
<title>{$mybb->settings['bbname']} - {$lang->contentnotes_usercp_title}</title>
{$headerinclude}
</head>
<body>
{$header}
<div class="sidemenu-wrapper">

	<!-- Menü + Handle (eine Einheit) -->
	<div class="sidemenu">
		<div class="sidemenu-inner">
			{$usercpnav}
		</div>

		<div class="sidemenu-handle">
			<span class="sidemenu-arrow">›</span>
		</div>
	</div>

	<!-- OVERLAY -->
	<div class="sidemenu-overlay"></div>

	<!-- Content -->
	<div class="sidemenu-content">
		<form action="usercp.php" method="post">
		<input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
		<input type="hidden" name="action" value="do_contentnotes" />
		<table border="0" cellspacing="{$theme['borderwidth']}" cellpadding="{$theme['tablespace']}" class="tborder">
		<tr>
		<td class="thead" colspan="2"><strong>{$lang->contentnotes_usercp_title}</strong></td>
		</tr>
		<tr>
		<td class="trow1 smalltext" colspan="2">{$lang->contentnotes_usercp_intro}</td>
		</tr>
		{$rows}
		</table>
		<br />
		<div style="text-align: center;">
		<input type="submit" class="button" value="{$lang->contentnotes_usercp_save}" />
		</div>
		</form>
	</div>

</div>
{$footer}
</body>
</html>
