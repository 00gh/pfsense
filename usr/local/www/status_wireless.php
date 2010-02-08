<?php
/*
	status_wireless.php
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
/*
	pfSense_MODULE:	interfaces
*/

##|+PRIV
##|*IDENT=page-diagnostics-wirelessstatus
##|*NAME=Diagnostics: Wireless Status page
##|*DESCR=Allow access to the 'Diagnostics: Wireless Status' page.
##|*MATCH=status_wireless.php*
##|-PRIV

require_once("guiconfig.inc");

$pgtitle = array("Diagnostics","Wireless Status");
include("head.inc");

$if = $_POST['if'];
if($_GET['if'] <> "")
	$if = $_GET['if'];

$ciflist = get_configured_interface_with_descr();
if(empty($if)) {
	/* Find the first interface
	   that is wireless */
	foreach($ciflist as $interface => $ifdescr) {
		if(is_interface_wireless($interface))
			$if = $interface;
	}
}
?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php
include("fbegin.inc");
?>
<form action="status_wireless.php" method="post">
<?php if ($savemsg) print_info_box($savemsg); ?>

<table width="100%" border="0" cellpadding="0" cellspacing="0">
<tr><td>
<?php
$tab_array = array();
foreach($ciflist as $interface => $ifdescr) {
	if (is_interface_wireless($interface)) {
		$enabled = false;
		if($if == get_real_interface($interface))
			$enabled = true;
		$tab_array[] = array("Status ($ifdescr)", $enabled, "status_wireless.php?if={$interface}");
	}
}
display_top_tabs($tab_array);
?>
</td></tr>
<tr><td>
<div id="mainarea">
<table class="tabcont" colspan="3" cellpadding="3" width="100%">
<?php


	/* table header */
	print "<tr><td colspan=7><b>Nearby access points or ad-hoc peers.<br/></td></tr>\n";
	print "\n<tr>";
	print "<tr bgcolor='#990000'>";
	print "<td><b><font color='#ffffff'>SSID</td>";
	print "<td><b><font color='#ffffff'>BSSID</td>";
	print "<td><b><font color='#ffffff'>CHAN</td>";
	print "<td><b><font color='#ffffff'>RATE</td>";
	print "<td><b><font color='#ffffff'>RSSI</td>";
	print "<td><b><font color='#ffffff'>INT</td>";
	print "<td><b><font color='#ffffff'>CAPS</td>";
	print "</tr>\n\n";

	$rwlif = get_real_interface($if);
	exec("/sbin/ifconfig {$rwlif} list scan 2>&1", $states, $ret);
	/* Skip Header */
	array_shift($states);

	$counter=0;
	foreach($states as $state) {
		/* Split by Mac address for the SSID Field */
		$split = preg_split("/([0-9a-f][[0-9a-f]\:[0-9a-f][[0-9a-f]\:[0-9a-f][[0-9a-f]\:[0-9a-f][[0-9a-f]\:[0-9a-f][[0-9a-f]\:[0-9a-f][[0-9a-f])/i", $state);
		preg_match("/([0-9a-f][[0-9a-f]\:[0-9a-f][[0-9a-f]\:[0-9a-f][[0-9a-f]\:[0-9a-f][[0-9a-f]\:[0-9a-f][[0-9a-f]\:[0-9a-f][[0-9a-f])/i", $state, $bssid);
		$ssid = $split[0];
		$bssid = $bssid[0];
		/* Split the rest by using spaces for this line using the 2nd part */
		$split = preg_split("/[ ]+/i", $split[1]);
		$bssid = $split[0];
		$channel = $split[1];
		$rate = $split[2];
		$rssi = $split[3];
		$int = $split[4];
		$caps = "$split[5] $split[6] $split[7] $split[8] $split[9] $split[10] $split[11] ";

		print "<tr>";
		print "<td>{$ssid}</td>";
		print "<td>{$bssid}</td>";
		print "<td>{$channel}</td>";
		print "<td>{$rate}</td>";
		print "<td>{$rssi}</td>";
		print "<td>{$int}</td>";
		print "<td>{$caps}</td>";
		print "</tr>\n";
	}

	print "</table><table class=\"tabcont\" colspan=\"3\" cellpadding=\"3\" width=\"100%\">";

	/* table header */
	print "\n<tr>";
	print "<tr><td colspan=7><b>Associated or ad-hoc peers.<br/></td></tr>\n";
	print "<tr bgcolor='#990000'>";
	print "<td><b><font color='#ffffff'>ADDR</td>";
	print "<td><b><font color='#ffffff'>AID</td>";
	print "<td><b><font color='#ffffff'>CHAN</td>";
	print "<td><b><font color='#ffffff'>RATE</td>";
	print "<td><b><font color='#ffffff'>RSSI</td>";
	print "<td><b><font color='#ffffff'>IDLE</td>";
	print "<td><b><font color='#ffffff'>TXSEQ</td>";
	print "<td><b><font color='#ffffff'>RXSEQ</td>";
	print "<td><b><font color='#ffffff'>CAPS</td>";
	print "<td><b><font color='#ffffff'>ERP</td>";
	print "</tr>\n\n";

	$states = array();
	exec("/sbin/ifconfig {$rwlif} list sta 2>&1", $states, $ret);
	array_shift($states);

	$counter=0;
	foreach($states as $state) {
		$split = preg_split("/[ ]+/i", $state);
		/* Split the rest by using spaces for this line using the 2nd part */
		print "<tr>";
		print "<td>{$split[0]}</td>";
		print "<td>{$split[1]}</td>";
		print "<td>{$split[2]}</td>";
		print "<td>{$split[3]}</td>";
		print "<td>{$split[4]}</td>";
		print "<td>{$split[5]}</td>";
		print "<td>{$split[6]}</td>";
		print "<td>{$split[7]}</td>";
		print "<td>{$split[8]}</td>";
		print "<td>{$split[9]}</td>";
		print "</tr>\n";
	}

/* XXX: what stats to we get for adhoc mode? */ 

?>
</table>
</div>
</td></tr>
</table>

<?php include("fend.inc"); ?>
</body>
</html>
