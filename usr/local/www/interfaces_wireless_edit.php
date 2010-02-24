<?php
/* $Id$ */
/*
	interfaces_wireless_edit.php

	Copyright (C) 2010 Erik Fonnesbeck
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
	pfSense_MODULE:	interfaces
*/

##|+PRIV
##|*IDENT=page-interfaces-wireless-edit
##|*NAME=Interfaces: Wireless edit page
##|*DESCR=Allow access to the 'Interfaces: Wireless : Edit' page.
##|*MATCH=interfaces_wireless_edit.php*
##|-PRIV

require("guiconfig.inc");

if (!is_array($config['wireless']['clone']))
	$config['wireless']['clone'] = array();

$a_clones = &$config['wireless']['clone'];

function clone_inuse($cloneif) {
	global $config;

	$iflist = get_configured_interface_list(false, true);
	foreach ($iflist as $if) {
		if ($config['interfaces'][$if]['if'] == $cloneif)
			return true;
	}

	return false;
}

function clone_compare($a, $b) {
	return strcmp($a['cloneif'], $b['cloneif']);
}

$portlist = get_interface_list();

$id = $_GET['id'];
if (isset($_POST['id']))
	$id = $_POST['id'];

if (isset($id) && $a_clones[$id]) {
	$pconfig['if'] = $a_clones[$id]['if'];
	$pconfig['cloneif'] = $a_clones[$id]['cloneif'];
	$pconfig['mode'] = $a_clones[$id]['mode'];
	$pconfig['descr'] = $a_clones[$id]['descr'];
}

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "if mode");
	$reqdfieldsn = explode(",", "Parent interface,Mode");

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);

	if (!$input_errors) {
		$clone = array();
		$clone['if'] = $_POST['if'];
		$clone['mode'] = $_POST['mode'];
		$clone['descr'] = $_POST['descr'];

		if (isset($id) && $a_clones[$id]) {
			if ($clone['if'] == $a_clones[$id]['if'])
				$clone['cloneif'] = $a_clones[$id]['cloneif'];
		}
		if (!$clone['cloneif']) {
			$clone_id = 1;
			do {
				$clone_exists = false;
				$clone['cloneif'] = "{$_POST['if']}_wlan{$clone_id}";
				foreach ($a_clones as $existing) {
					if ($clone['cloneif'] == $existing['cloneif']) {
						$clone_exists = true;
						$clone_id++;
						break;
					}
				}
			} while ($clone_exists);
		}

		if (isset($id) && $a_clones[$id]) {
			if (clone_inuse($a_clones[$id]['if'])) {
				if ($clone['if'] != $a_clones[$id]['if'])
					$input_errors[] = "This wireless clone cannot be modified because it is still assigned as an interface.";
				else if ($clone['mode'] != $a_clones[$id]['mode'])
					$input_errors[] = "Use the configuration page for the assigned interface to change the mode.";
			}
		}
		if (!$input_errors) {
			if (!interface_wireless_clone($clone['cloneif'], $clone)) {
				$input_errors[] = "Error creating interface with mode {$clone['mode']}.  The {$clone['if']} interface may not support creating more clones with the selected mode.";
			} else {
				if (isset($id) && $a_clones[$id]) {
					if ($clone['if'] != $a_clones[$id]['if'])
						mwexec("/sbin/ifconfig " . $a_clones[$id]['cloneif'] . " destroy");
					$input_errors[] = "Created with id {$id}";
					$a_clones[$id] = $clone;
				} else {
					$input_errors[] = "Created without id";
					$a_clones[] = $clone;
				}

				usort($a_clones, "clone_compare");
				write_config();

				header("Location: interfaces_wireless.php");
				exit;
			}
		}
	}
}

$pgtitle = array("Firewall","Wireless","Edit");
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<?php if ($input_errors) print_input_errors($input_errors); ?>
            <form action="interfaces_wireless_edit.php" method="post" name="iform" id="iform">
              <table width="100%" border="0" cellpadding="6" cellspacing="0">
                <tr>
                  <td colspan="2" valign="top" class="listtopic">Wireless clone configuration</td>
                </tr>
                <tr>
                  <td width="22%" valign="top" class="vncellreq">Parent interface</td>
                  <td width="78%" class="vtable">
                    <select name="if" class="formselect">
                      <?php
                      foreach ($portlist as $ifn => $ifinfo)
                        if (is_interface_wireless($ifn)) {
                            echo "<option value=\"{$ifn}\"";
                            if ($ifn == $pconfig['if'])
                                echo "selected";
                            echo ">";
                            echo htmlspecialchars($ifn . " (" . $ifinfo['mac'] . ")");
                            echo "</option>";
                        }
                      ?>
                    </select></td>
                </tr>
                <tr>
                  <td valign="top" class="vncellreq">Mode</td>
                  <td class="vtable">
                    <select name="mode" class="formselect">
                      <option <? if ($pconfig['mode'] == 'bss') echo "selected";?> value="bss">Infrastructure (BSS)</option>
                      <option <? if ($pconfig['mode'] == 'adhoc') echo "selected";?> value="adhoc">Ad-hoc (IBSS)</option>
                      <option <? if ($pconfig['mode'] == 'hostap') echo "selected";?> value="hostap">Access Point</option>
                    </select></td>
                </tr>
                <tr>
                  <td width="22%" valign="top" class="vncell">Description</td>
                  <td width="78%" class="vtable">
                    <input name="descr" type="text" class="formfld unknown" id="descr" size="40" value="<?=htmlspecialchars($pconfig['descr']);?>">
                    <br> <span class="vexpl">You may enter a description here
                    for your reference (not parsed).</span></td>
                </tr>
                <tr>
                  <td width="22%" valign="top">&nbsp;</td>
                  <td width="78%">
                    <input type="hidden" name="cloneif" value="<?=$pconfig['cloneif']; ?>">
                    <input name="Submit" type="submit" class="formbtn" value="Save"> <input type="button" value="Cancel" onclick="history.back()">
                    <?php if (isset($id) && $a_clones[$id]): ?>
                    <input name="id" type="hidden" value="<?=$id;?>">
                    <?php endif; ?>
                  </td>
                </tr>
              </table>
</form>
<?php include("fend.inc"); ?>
</body>
</html>
