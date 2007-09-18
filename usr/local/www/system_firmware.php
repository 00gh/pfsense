<?php
/* $Id$ */
/*
	system_firmware.php
	part of m0n0wall (http://m0n0.ch/wall)

	Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
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

$d_isfwfile = 1;
require_once("guiconfig.inc");
require_once("xmlrpc_client.inc");

/* if upgrade in progress, alert user */
if(file_exists($d_firmwarelock_path)) {
	$pgtitle = "System: Firmware: Manual Update";
	include("head.inc");
	echo "<body link=\"#0000CC\" vlink=\"#0000CC\" alink=\"#0000CC\">\n";
	include("fbegin.inc");
	echo "<p class=\"pgtitle\"><?=$pgtitle?></p>\n";
	echo "<div>\n";
	print_info_box("An upgrade is currently in progress.<p>The firewall will reboot when the operation is complete.<p><center><img src='/themes/{$g['theme']}/images/icons/icon_fw-update.gif'>");
	echo "</div>\n";
	include("fend.inc");
	echo "</body>";
	echo "</html>";
	exit;
}

/* Handle manual upgrade */
if ($_POST && !file_exists($d_firmwarelock_path)) {

	unset($input_errors);
	unset($sig_warning);

	if($_POST['kerneltype']) 
		system("echo {$_POST['kerneltype']} > /boot/kernel/pfsense_kernel.txt");

	if (stristr($_POST['Submit'], "Enable"))
		$mode = "enable";
	else if (stristr($_POST['Submit'], "Disable"))
		$mode = "disable";
	else if (stristr($_POST['Submit'], "Upgrade") || $_POST['sig_override'])
		$mode = "upgrade";
	else if ($_POST['sig_no']) {
		if(file_exists("{$g['upload_path']}/firmware.tgz"))
				unlink("{$g['upload_path']}/firmware.tgz");
	}
	if ($mode) {
		if ($mode == "enable") {
			exec_rc_script("/etc/rc.firmware enable");
			conf_mount_rw();
			touch($d_fwupenabled_path);
		} else if ($mode == "disable") {
			exec_rc_script("/etc/rc.firmware disable");
			conf_mount_ro();
			if (file_exists($d_fwupenabled_path))
				unlink($d_fwupenabled_path);
		} else if ($mode == "upgrade") {
			if (is_uploaded_file($_FILES['ulfile']['tmp_name'])) {
				/* verify firmware image(s) */
				if (!stristr($_FILES['ulfile']['name'], $g['platform']) && !$_POST['sig_override'])
					$input_errors[] = "The uploaded image file is not for this platform ({$g['platform']}).";
				else if (!file_exists($_FILES['ulfile']['tmp_name'])) {
					/* probably out of memory for the MFS */
					$input_errors[] = "Image upload failed (out of memory?)";
					exec_rc_script("/etc/rc.firmware disable");
					if (file_exists($d_fwupenabled_path))
						unlink($d_fwupenabled_path);
				} else {
					/* move the image so PHP won't delete it */
					rename($_FILES['ulfile']['tmp_name'], "{$g['upload_path']}/firmware.tgz");

					/* check digital signature */
					$sigchk = verify_digital_signature("{$g['upload_path']}/firmware.tgz");

					if ($sigchk == 1)
						$sig_warning = "The digital signature on this image is invalid.";
					else if ($sigchk == 2)
						$sig_warning = "This image is not digitally signed.";
					else if (($sigchk == 3) || ($sigchk == 4))
						$sig_warning = "There has been an error verifying the signature on this image.";

					if (!verify_gzip_file("{$g['upload_path']}/firmware.tgz")) {
						$input_errors[] = "The image file is corrupt.";
						unlink("{$g['upload_path']}/firmware.tgz");
					}
				}
			}

			if(!file_exists("/boot/kernel/pfsense_kernel.txt")) { 
				
			}

            /* Check for input errors, firmware locks, warnings, then check for firmware if sig_override is set */
            if (!$input_errors && !file_exists($d_firmwarelock_path) && (!$sig_warning || $_POST['sig_override'])) {
                    if (file_exists("{$g['upload_path']}/firmware.tgz")) {
                            /* fire up the update script in the background */
                            touch($d_firmwarelock_path);
                            $savemsg = "The firmware is now being updated. The firewall will reboot automatically.";
                            mwexec_bg("/etc/rc.firmware pfSenseupgrade {$g['upload_path']}/firmware.tgz");
                    } else {
                            $savemsg = "Firmware image missing or other error, please try again.";
                    }
            }
		}
	}
}

$pgtitle = "System: Firmware: Manual Update";
include("head.inc");

?>
<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<p class="pgtitle"><?=$pgtitle?></p>
<?php if ($input_errors) print_input_errors($input_errors); ?>
<?php if ($savemsg) print_info_box($savemsg); ?>
<?php if ($fwinfo <> "") print_info_box($fwinfo); ?>
<?php if ($sig_warning && !$input_errors): ?>
<form action="system_firmware.php" method="post">
<?php
$sig_warning = "<strong>" . $sig_warning . "</strong><br>This means that the image you uploaded " .
	"is not an official/supported image and may lead to unexpected behavior or security " .
	"compromises. Only install images that come from sources that you trust, and make sure ".
	"that the image has not been tampered with.<br><br>".
	"Do you want to install this image anyway (on your own risk)?";
print_info_box($sig_warning);
?>
<input name="sig_override" type="submit" class="formbtn" id="sig_override" value=" Yes ">
<input name="sig_no" type="submit" class="formbtn" id="sig_no" value=" No ">
</form>
<?php else: ?>
            <?php if (!file_exists($d_firmwarelock_path)): ?>
<form action="system_firmware.php" method="post" enctype="multipart/form-data">
	<table width="100%" border="0" cellpadding="0" cellspacing="0">
	<tr>
		<td>
<?php
	$tab_array = array();
	$tab_array[0] = array("Manual Update", true, "system_firmware.php");
	//$tab_array[1] = array("Auto Update", false, "system_firmware_check.php");
	//$tab_array[2] = array("Updater Settings", false, "system_firmware_settings.php");
	display_top_tabs($tab_array);
?>
		</td>
	</tr>
  <tr>
    <td>
	<div id="mainarea">
              <table class="tabcont" width="100%" border="0" cellpadding="6" cellspacing="0">
                <tr>
		 <td colspan="2" class="listtopic">Invoke pfSense Manual Upgrade</td>
		</tr>
		  <td width="22%" valign="baseline" class="vncell">&nbsp;</td>
                  <td width="78%" class="vtable">
            <p>Click &quot;Enable firmware
              upload&quot; below, then choose the image file (<?=$g['platform'];?>-*.tgz)
			  to be uploaded.<br>Click &quot;Upgrade firmware&quot;
              to start the upgrade process.</p>
                    <?php if (!file_exists($d_sysrebootreqd_path)): ?>
                    <?php if (!file_exists($d_fwupenabled_path)): ?>
                    <input name="Submit" type="submit" class="formbtn" value="Enable firmware upload">
				  <?php else: ?>
				   <input name="Submit" type="submit" class="formbtn" value="Disable firmware upload">
                    <br><br>
					<strong>Firmware image file: </strong>&nbsp;
					<input name="ulfile" type="file" class="formfld">
                    <br><br>
					  <?php
				  		if(!file_exists("/boot/kernel/pfsense_kernel.txt")) {
				  			if($g['platform'] == "pfSense") { 
								echo "<select name='kerneltype'>'";
								echo "<option value=''>Uniprocessor kernel</option>";
								echo "<option value='SMP'>Multiprocessor kernel</option>";
								echo "<option value='wrap'>Embedded kernel</option>";
								echo "<option value='wrap'>Developers kernel</option>";
								echo "</select>";
							}
						}
					  ?>
		    <input name="Submit" type="submit" class="formbtn" value="Upgrade firmware">
				  <?php endif; else: ?>
				    <strong>You must reboot the system before you can upgrade the firmware.</strong>
				  <?php endif; ?>
                  </td>
		</td>
                </tr>
                <tr>
                  <td width="22%" valign="top">&nbsp;</td>
                  <td width="78%"><span class="vexpl"><span class="red"><strong>Warning:<br>
                    </strong></span>DO NOT abort the firmware upgrade once it
                    has started. The firewall will reboot automatically after
                    storing the new firmware. The configuration will be maintained.</span></td>
              </table>
		</div>
		</tr>
		</td>
</table>

</form>
<?php endif; endif; ?>
<?php include("fend.inc"); ?>
</body>
</html>
