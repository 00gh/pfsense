<?php
/*
	diag_tables.php
	Copyright (C) 2010 Jim Pingle

	Portions borrowed from diag_dump_states.php:
	Copyright (C) 2010 Scott Ullrich
	All rights reserved.

	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:

	1. Redistributions of source code must retain the above copyright notice,
	   this list of conditions and the following disclaimer.

	2. Redistributions in binary form must reproduce the above copyright
	   notice, this list of conditions and the following disclaimer in the
	   documentation and/or other materials provided with the distribution.

	THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
	INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
	AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
	AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
	OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
	SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
	INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
	CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
	POSSIBILITY OF SUCH DAMAGE.
*/

/*
	pfSense_BUILDER_BINARIES:	/sbin/pfctl
	pfSense_MODULE:	filter
*/

##|+PRIV
##|*IDENT=page-diagnostics-tables
##|*NAME=Diagnostics: PF Table IP addresses
##|*DESCR=Allow access to the 'Diagnostics: Tables' page.
##|*MATCH=diag_tables.php*
##|-PRIV

$pgtitle = array(gettext("Diagnostics"), gettext("Tables"));

require_once("guiconfig.inc");

// Set default table
$tablename = "sshlockout";
	
if($_REQUEST['type']) 
	$tablename = $_REQUEST['type'];
	
if($_REQUEST['delete']) {
	if(is_ipaddr($_REQUEST['delete'])) {
		exec("/sbin/pfctl -t " . escapeshellarg($_REQUEST['type']) . " -T delete " . escapeshellarg($_REQUEST['delete']), $delete);
		echo htmlentities($_REQUEST['delete']);
	}
	exit;	
}

if($_REQUEST['deleteall']) {
	exec("/sbin/pfctl -t $tablename -T show", $entries);
	if(is_array($entries)) {
		foreach($entries as $entryA) {
			$entry = trim($entryA);
			exec("/sbin/pfctl -t " . escapeshellarg($tablename) . " -T delete " . escapeshellarg($entry), $delete);
		}
	}
}

exec("/sbin/pfctl -t $tablename -T show", $entries);
exec("/sbin/pfctl -sT", $tables);

include("head.inc");
include("fbegin.inc");

?>

<form method='post'>
<script src="/javascript/scriptaculous/prototype.js" type="text/javascript"></script>

<script language="javascript">
	function method_change(entrytype) {
		window.location='diag_tables.php?type=' + entrytype;
	}
	function del_entry(entry) {
		new Ajax.Request("diag_tables.php?type=<?php echo $tablename;?>&delete=" + entry, {
		onComplete: function(response) {
			if (200 == response.status) 
				new Effect.Fade($(response.responseText), { duration: 1.0 } ); 
		}
		});
	}
</script>
	
<?=gettext("Table:");?> 
<select id='type' onChange='method_change($F("type"));' name='type'>
	<?php foreach ($tables as $table) {
		echo "<option name='{$table}' value='{$table}'";
		if ($tablename == $table)
			echo " selected ";
		echo ">{$table}</option>\n";
		}
	?>
</select>

<p/>

<table class="tabcont" width="100%" border="0" cellspacing="0" cellpadding="0">
	<tr>
		<td class="listhdrr"><?=gettext("IP Address");?></td>
	</tr>
<?php $count = 0; foreach($entries as $entryA): ?>
	<?php $entry = trim($entryA); ?>
	<tr id='<?=$entry?>'>
		<td>
			<?php echo $entry; ?>
		</td>
		<td>
			<a onClick='del_entry("<?=$entry?>");'>
				<img img src="/themes/<?=$g['theme'];?>/images/icons/icon_x.gif">
			</a>
		</td>
	</tr>
<?php $count++; endforeach; ?>
<?php
	if($count == 0)
		echo "<tr><td>" . gettext("No entries exist in this table.") . "</td></tr>";
?>

</table>

<?php
	if($count > 0)
		echo "<p/>" . gettext("Delete") . " <a href='diag_tables.php?deleteall=true&type={$tablename}'>" . gettext("all") . "</a> " . gettext("entries in this table.");

?>

<?php include("fend.inc"); ?>
