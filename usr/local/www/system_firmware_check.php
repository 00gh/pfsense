<?php
/* $Id$ */
/*
    system_firmware.php
    Copyright (C) 2004, 2005 Scott Ullrich and Colin Smith
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

Header("Location: system_firmware.php");
exit;

require_once("guiconfig.inc");
require_once("xmlrpc.inc");

if(isset($config['system']['disablefirmwarecheck']))
	Header("Location: system_firmware.php");

$versions = check_firmware_version();
$pgtitle = array("System","Firmware","Auto Update");
include("head.inc");

?>
<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
		<SCRIPT>
		<!--
			function toggleTable (table, img) {
				var table = document.getElementById(table);
				var img = document.getElementById(img);
				if (table.rows[0].style.display == 'none') {
					for (var r = 0; r < table.rows.length; r++)
						table.rows[r].style.display = '';
					img.src = "./themes/<?= $g['theme']; ?>/images/misc/tri_o_black.gif";
				} else {
					for (var r = 0; r < table.rows.length; r++)
						table.rows[r].style.display = 'none';
					img.src = "./themes/<?= $g['theme']; ?>/images/misc/tri_c_black.gif";
				}
			}
		//-->
		</SCRIPT>
		<table width="100%" border="0" cellpadding="0" cellspacing="0">
			<tr>
				<td>
<?php
	$tab_array = array();
	$tab_array[0] = array("Manual Update", false, "system_firmware.php");
	$tab_array[1] = array("Auto Update", true, "system_firmware_check.php");
	$tab_array[2] = array("Updater Settings", false, "system_firmware_settings.php");
	display_top_tabs($tab_array);
?>
				</td>
			</tr>
			<tr>
				<td>
<?php
if(is_array($versions)) {
?>
					<div id="mainarea">
					<table align="center" width="100%" border="0" cellpadding="0" cellspacing="0" class="tabcont">
						<tr>
							<td width="10%" class="listhdrr">Act</td>
							<td width="30%" class="listhdrr">Category</td>
							<td width="30%" class="listhdrr">Installed</td>
							<td width="30%" class="listhdrr">Current<td>
						</tr>
<?php
	$currentvers = $versions['current'];
	foreach($versions as $key => $version) {
		if($key == "current") continue;
		$currentver = array_shift(explode('-', $currentvers[$key]['version']));
		if($version == 1) {
			$img = "./themes/".$g['theme']."/images/icons/icon_pass.gif";
			$pastlatest = true;
		} elseif( strcmp($currentver , $version[count($version) - 1]['version']) ){
			$img = "./themes/".$g['theme']."/images/icons/icon_pass.gif";
			$pastlatest = true;
		} else {
			$allinstall = true;
			$img = "./themes/".$g['theme']."/images/icons/icon_block.gif";
		}
?>
						<tr valign="top">
							<td class="listlr" nowrap align="middle"><img src="<?=$img;?>" width="11" height="11" align="absmiddle"></td>
							<td class="listlr"><?= ucfirst($key) ?></td>
							<td class="listlr"><?= $currentver ?></td>
<?php
		if($version == 1) {
?>
							<td class="listlr"><?= $currentver ?></td>
<?php
		} elseif($pastlatest) {
			$newver = $version[count($version) - 1]['version'];
?>
							<td class="listbggrey"><font color="#FFFFFF"><?= $newver ?></td>
<?php
		} else {
			$newver = $version[count($version) - 1]['version'];
?>
							<td class="listbg"><font color="#FFFFFF"><?= $newver ?></td>
<?php
		} 
		if(!$pastlatest) {
?>
							<td valign="middle" class="list" nowrap>
								<a href="system_firmware_auto.php?category=<?=$key;?>"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0"></a>
							</td>
<?php
		}
?>
						</tr>
<?php
	}
?>
					</table>
<?php
	if($allinstall) {
?>
					<br>
					<br>
					<table align="center" width="80%" border="0" cellpadding="0" cellspacing="0">
						<tr>
							<td align="center"><a href="javascript:toggleTable('updates', 'tri_updates')"><img src="./themes/<?= $g['theme']; ?>/images/misc/tri_c_black.gif" id="tri_updates" width="14" height="10" border="0"></a><strong><a href="javascript:toggleTable('updates', 'tri_updates')">Needed Updates</a></strong></td>
						</tr>
						<tr>
							<td>
								<br>
								<br>
								<table id="updates" align="center" width="100%" border="0" cellpadding="0" cellspacing="0">
									<tr>
										<td width="20%" class="listhdrr">Released</td>
										<td width="20%" class="listhdrr">Category</td>  
										<td width="20%" class="listhdrr">Version</td>
										<td width="20%" class="listhdrr">Size</td>
										<td width="20%" class="listhdr">Type</td>
									</tr>
<?php
		if(is_array($versions)) {
			foreach($versions as $key => $value) {
				if(($key == "current") or ($value == 1)) continue;
				if(is_array($value)) {
					foreach($value as $version) {
						if(!$version['time']) $version['time'] = "Unknown";
						if(!$version['size']) $version['size'] = "Unknown";
						if(!$version['type']) $version['type'] = "Unknown";
						$version['category'] = $key;
						$times[$version['time']][] = $version;
					}
				}
			}
		}
		asort($times);
		if(is_array($times)) {
			foreach($times as $time) {
				foreach($time as $version) {
?>
									<tr>
										<td class="listlr">
<?php
				if($version['time'] != "Unknown") {
					echo date("D M j G:i:s", $version['time']);
				} else {
					echo $version['time'];
				}
?>
										</td>
										<td class="listlr"><?= ucfirst($version['category']) ?></td>
										<td class="listlr"><?= $version['version'] ?></td>
										<td class="listlr"><?= $version['size'] ?></td>
										<td class="listlr"><?= ucfirst($version['type']) ?></td>
									</tr>
<?php
				}
			}
		}
?>
								</table>
								<br>
								<br>
								<script language="javascript">toggleTable('updates', 'tri_updates');</script>
							</td>
						</tr>
					</table>
					<table align="center">
						<tr>
							<td>
								<form action="system_firmware_auto.php" method="post" enctype="multipart/form-data">
									<input name="full" type="submit" class="formbtn" value="Begin Full Update">
								</form>
							</td>
						</tr>
					</table>
		</div>
<?php
	}
} else {
	print_info_box("Unable to receive version information.");
}
?>
				</td>
			</tr>
		</table>
<?php
include("fend.inc");
$versions['cachetime'] = time();
$fout = fopen("/tmp/versioncheck.cache", "w");
fwrite($fout, serialize($versions));
fclose($fout);
?>
	</body>
</html>
