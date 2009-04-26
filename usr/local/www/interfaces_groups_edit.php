<?php
/*
	Copyright (C) 2009 Ermal Lu�i
	Copyright (C) 2004 Scott Ullrich
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

##|+PRIV
##|*IDENT=page-interfacess-groups
##|*NAME=Interfaces: Groups: Edit page
##|*DESCR=Edit Interface groups
##|*MATCH=interfaces_groups_edit.php*
##|-PRIV


$pgtitle = array("Interfaces","Groups", "Edit");

require("guiconfig.inc");

if (!is_array($config['ifgroups']['ifgroupentry']))
	$config['ifgroups']['ifgroupentry'] = array();

$a_ifgroups = &$config['ifgroups']['ifgroupentry'];

if (isset($_GET['id']))
	$id = $_GET['id'];
if (isset($_POST['id']))
	$id = $_POST['id'];

if (isset($id) && $a_ifgroups[$id]) {
	$pconfig['ifname'] = $a_ifgroups[$id]['ifname'];
	$pconfig['members'] = $a_ifgroups[$id]['members'];
	$pconfig['descr'] = html_entity_decode($a_ifgroups[$id]['descr']);

}

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	if (!isset($id)) {
		foreach ($a_ifgroups as $groupentry)
			if ($groupentry['ifname'] == $_POST['ifname'])
				$input_errors[] = "Group name already exists!";
	}
	if (preg_match("/([^a-zA-Z])+/", $_POST['ifname'], $match))
		$input_errors[] = "Only characters in a-z A-Z are allowed as interface name.";

	$ifgroupentry = array();
	$ifgroupentry['ifname'] = $_POST['ifname'];
	$members = "";
	$isfirst = 0;
	/* item is a normal ifgroupentry type */
	for($x=0; $x<9999; $x++) {
		if($_POST["members{$x}"] <> "") {
			if ($isfirst > 0)
				$members .= " ";
			$members .= $_POST["members{$x}"];
			$isfirst++;
		}
	}

	if (!$input_errors) {
		$ifgroupentry['members'] = $members;
		$ifgroupentry['descr'] = mb_convert_encoding($_POST['descr'],"HTML-ENTITIES","auto");

		if (isset($id) && $a_ifgroups[$id]) {
			$omembers = explode(" ", $a_ifgroups[$id]['members']);
			$nmembers = explode(" ", $members);
			$delmembers = array_diff($omembers, $nmembers);
			if (count($delmembers) > 0) {
				foreach ($delmembers as $ifs) {
					$realif = get_real_interface($ifs);
					if ($realif)
						mwexec("/sbin/ifconfig {$realif} -group " . $a_ifgroups[$id]['ifname']);
				}
			}
			$a_ifgroups[$id] = $ifgroupentry;
		} else
			$a_ifgroups[] = $ifgroupentry;

		write_config();

		interface_group_setup($ifgroupentry);

		header("Location: interfaces_groups.php");
		exit;
	} else {
		$pconfig['descr'] = mb_convert_encoding($_POST['descr'],"HTML-ENTITIES","auto");
		$pconfig['members'] = $members;
	}
}

include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC" onload="<?= $jsevents["body"]["onload"] ?>">
<?php
	include("fbegin.inc");
?>

<script type="text/javascript">
// Global Variables
var rowname = new Array(9999);
var rowtype = new Array(9999);
var newrow  = new Array(9999);
var rowsize = new Array(9999);

for (i = 0; i < 9999; i++) {
        rowname[i] = '';
        rowtype[i] = 'select';
        newrow[i] = '';
        rowsize[i] = '30';
}

var field_counter_js = 0;
var loaded = 0;
var is_streaming_progress_bar = 0;
var temp_streaming_text = "";

var addRowTo = (function() {
    return (function (tableId) {
        var d, tbody, tr, td, bgc, i, ii, j;
        d = document;
        tbody = d.getElementById(tableId).getElementsByTagName("tbody").item(0);
        tr = d.createElement("tr");
        for (i = 0; i < field_counter_js; i++) {
                td = d.createElement("td");
		<?php
                        $innerHTML="\"<INPUT type='hidden' value='\" + totalrows +\"' name='\" + rowname[i] + \"_row-\" + totalrows + \"'></input><select size='1' name='\" + rowname[i] + totalrows + \"'>\" +\"";

			$iflist = get_configured_interface_with_descr();
                        foreach ($iflist as $ifnam => $ifdescr)
                                $innerHTML .= "<option value={$ifnam}>{$ifdescr}</option>";
			$innerHTML .= "</select>\";";
                ?>
			td.innerHTML=<?=$innerHTML;?>
                tr.appendChild(td);
        }
        td = d.createElement("td");
        td.rowSpan = "1";

        td.innerHTML = '<input type="image" src="/themes/' + theme + '/images/icons/icon_x.gif" onclick="removeRow(this);return false;" value="Delete">';
        tr.appendChild(td);
        tbody.appendChild(tr);
        totalrows++;
    });
})();

function removeRow(el) {
    var cel;
    while (el && el.nodeName.toLowerCase() != "tr")
            el = el.parentNode;

    if (el && el.parentNode) {
        cel = el.getElementsByTagName("td").item(0);
        el.parentNode.removeChild(el);
    }
}

	rowname[0] = "members";
	rowtype[0] = "textbox";
	rowsize[0] = "30";

	rowname[2] = "detail";
	rowtype[2] = "textbox";
	rowsize[2] = "50";
</script>
<input type='hidden' name='members_type' value='textbox' class="formfld unknown" />

<?php if ($input_errors) print_input_errors($input_errors); ?>
<div id="inputerrors"></div>

<form action="interfaces_groups_edit.php" method="post" name="iform" id="iform">
<table width="100%" border="0" cellpadding="6" cellspacing="0">
  <tr>
	<td colspan="2" valign="top" class="listtopic">Interface Groups Edit</td>
  </tr>
  <tr>
    <td valign="top" class="vncellreq">Interface</td>
    <td class="vtable">
	<input class="formfld unknown" name="ifname" id="ifname" value="<?=$pconfig['ifname'];?>" />
	<br />
	No numbers or spaces are allowed. Only characters in a-zA-Z
    </td>
  </tr>
  <tr>
    <td width="22%" valign="top" class="vncell">Description</td>
    <td width="78%" class="vtable">
      <input name="descr" type="text" class="formfld unknown" id="descr" size="40" value="<?=$pconfig['descr'];?>" />
      <br />
      <span class="vexpl">
        You may enter a description here for your reference (not parsed).
      </span>
    </td>
  </tr>
  <tr>
    <td width="22%" valign="top" class="vncellreq"><div id="membersnetworkport">Member (s)</div></td>
    <td width="78%" class="vtable">
      <table id="maintable">
        <tbody>
          <tr>
            <td><div id="onecolumn">Interface</div></td>
          </tr>

	<?php
	$counter = 0;
	$members = $pconfig['members'];
	if ($members <> "") {
		$item = explode(" ", $members);
		foreach($item as $ww) {
			$members = $item[$counter];
			$tracker = $counter;
	?>
        <tr>
	<td class="vtable">
	        <select name="members<?php echo $tracker; ?>" class="formselect" id="members<?php echo $tracker; ?>">
			<?php
				foreach ($iflist as $ifnam => $ifdescr) {
					echo "<option value={$ifnam}";
					if ($ifnam == $members)
						echo " selected";
					echo ">{$ifdescr}</option>";
				}
			?>
                        </select>
	</td>
        <td>
	<input type="image" src="/themes/<?echo $g['theme'];?>/images/icons/icon_x.gif" onclick="removeRow(this); return false;" value="Delete" />
	      </td>
          </tr>
<?php
		$counter++;

		} // end foreach
	} // end if
?>
        </tbody>
        <tfoot>

        </tfoot>
		  </table>
			<a onclick="javascript:addRowTo('maintable'); return false;" href="#">
        <img border="0" src="/themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" alt="" title="add another entry" />
      </a>
		</td>
  </tr>
  <tr>
    <td width="22%" valign="top">&nbsp;</td>
    <td width="78%">
      <input id="submit" name="submit" type="submit" class="formbtn" value="Save" />
      <a href="interfaces_groups.php"><input id="cancelbutton" name="cancelbutton" type="button" class="formbtn" value="Cancel" /></a>
      <?php if (isset($id) && $a_ifgroups[$id]): ?>
      <input name="id" type="hidden" value="<?=$id;?>" />
      <?php endif; ?>
    </td>
  </tr>
</table>
</form>

<script type="text/javascript">
	field_counter_js = 1;
	rows = 1;
	totalrows = <?php echo $counter; ?>;
	loaded = <?php echo $counter; ?>;
</script>

<?php include("fend.inc"); ?>
</body>
</html>
