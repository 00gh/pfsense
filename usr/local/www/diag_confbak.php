<?php
/* $Id$ */
/*
    diag_confbak.php
    Copyright (C) 2005 Colin Smith
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
	pfSense_MODULE:	config
*/

##|+PRIV
##|*IDENT=page-diagnostics-configurationhistory
##|*NAME=Diagnostics: Configuration History page
##|*DESCR=Allow access to the 'Diagnostics: Configuration History' page.
##|*MATCH=diag_confbak.php*
##|-PRIV

require("guiconfig.inc");

if($_GET['newver'] != "") {
	conf_mount_rw();
	$confvers = unserialize(file_get_contents($g['cf_conf_path'] . '/backup/backup.cache'));
	if(config_restore($g['conf_path'] . '/backup/config-' . $_GET['newver'] . '.xml') == 0)
		$savemsg = "Successfully reverted to timestamp " . date("n/j/y H:i:s", $_GET['newver']) . " with description \"" . $confvers[$_GET['newver']]['description'] . "\".";
	else
		$savemsg = "Unable to revert to the selected configuration.";
	conf_mount_ro();
}

if($_GET['rmver'] != "") {
	conf_mount_rw();
	$confvers = unserialize(file_get_contents($g['cf_conf_path'] . '/backup/backup.cache'));
	unlink_if_exists($g['conf_path'] . '/backup/config-' . $_GET['rmver'] . '.xml');
	$savemsg = "Deleted backup with timestamp " . date("n/j/y H:i:s", $_GET['rmver']) . " and description \"" . $confvers[$_GET['rmver']]['description'] . "\".";
	conf_mount_ro();
}

if($_GET['getcfg'] != "") {
	$file = $g['conf_path'] . '/backup/config-' . $_GET['getcfg'] . '.xml';

	$exp_name = urlencode("config-{$config['system']['hostname']}.{$config['system']['domain']}-{$_GET['getcfg']}.xml");
	$exp_data = file_get_contents($file);
	$exp_size = strlen($exp_data);

	header("Content-Type: application/octet-stream");
	header("Content-Disposition: attachment; filename={$exp_name}");
	header("Content-Length: $exp_size");
	echo $exp_data;
	exit;
}

cleanup_backupcache();
$confvers = get_backups();
unset($confvers['versions']);

$pgtitle = array("Diagnostics","Configuration History");
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
	<?php
		include("fbegin.inc");
		if($savemsg)
			print_info_box($savemsg);
	?>
	<table width="100%" border="0" cellpadding="0" cellspacing="0">
		<tr>
			<td>
			<?php
				$tab_array = array();
				$tab_array[0] = array("Config History", true, "diag_confbak.php");
				$tab_array[1] = array("Backup/Restore", false, "diag_backup.php");
				display_top_tabs($tab_array);
			?>			
			</td>
		</tr>
		<tr>
			<td>
				<div id="mainarea">
					<table class="tabcont" align="center" width="100%" border="0" cellpadding="6" cellspacing="0">
						<?php if (is_array($confvers)): ?>
						<tr>
							<td width="30%" class="listhdrr">Date</td>
							<td width="70%" class="listhdrr">Configuration Change</td>
						</tr>
						<tr valign="top">
							<td class="listlr"> <?= date("n/j/y H:i:s", $config['revision']['time']) ?></td>
							<td class="listr"> <?= $config['revision']['description'] ?></td>
							<td colspan="2" valign="middle" class="list" nowrap><b>Current</b></td>
						</tr>
						<?php
							foreach($confvers as $version):
								if($version['time'] != 0)
									$date = date("n/j/y H:i:s", $version['time']);
								else
									$date = "Unknown";
								$desc = $version['description'];
						?>
						<tr valign="top">
							<td class="listlr"> <?= $date ?></td>
							<td class="listr"> <?= $desc ?></td>
							<td valign="middle" class="list" nowrap>
								<a href="diag_confbak.php?newver=<?=$version['time'];?>" onclick="return confirm('Revert to this configuration?')">
									<img src="/themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0" alt="Revert to this configuration" title="Revert to this configuration">
								</a>
							</td>
							<td valign="middle" class="list" nowrap>
								<a href="diag_confbak.php?rmver=<?=$version['time'];?>" onclick="return confirm('Delete this configuration backup?')">
									<img src="/themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" width="17" height="17" border="0" alt="Remove this backup" title="Remove this backup">
								</a>
							</td>
							<td valign="middle" class="list" nowrap>
								<a href="diag_confbak.php?getcfg=<?=$version['time'];?>">
									<img src="/themes/<?= $g['theme']; ?>/images/icons/icon_down.gif" width="17" height="17" border="0" alt="Download this backup" title="Download this backup">
								</a>
							</td>
						</tr>
						<?php endforeach; ?>
						<?php else: ?>
						<tr>
							<td>
								<?php print_info_box("No backups found."); ?>
							</td>
						</tr>
						<?php endif; ?>
					</table>
				</div>
			</td>
		</tr>
	</table>
</form>
<?php include("fend.inc"); ?>
</body>
</html>
