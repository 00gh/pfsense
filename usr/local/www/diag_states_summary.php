<?php
/*
	diag_states_summary.php
	Copyright (C) 2010 Jim Pingle

	Portions borrowed from diag_dump_states.php:
	Copyright (C) 2005-2009 Scott Ullrich
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
	pfSense_BUILDER_BINARIES:	/sbin/pfctl
	pfSense_MODULE:	filter
*/

##|+PRIV
##|*IDENT=page-diagnostics-statessummary
##|*NAME=Diagnostics: States Summary page
##|*DESCR=Allow access to the 'Diagnostics: States Summary' page.
##|*MATCH=diag_states_summary.php*
##|-PRIV

exec("/sbin/pfctl -s state", $states);

$srcipinfo = array();
$dstipinfo = array();
$allipinfo = array();
$pairipinfo = array();

function addipinfo(&$iparr, $ip, $proto, $srcport, $dstport) {
	$iparr[$ip]['seen']++;
	$iparr[$ip]['protos'][$proto]['seen']++;
	if (!empty($srcport)) {
		$iparr[$ip]['protos'][$proto]['srcports'][$srcport]++;
	}
	if (!empty($dstport)) {
		$iparr[$ip]['protos'][$proto]['dstports'][$dstport]++;
	}
}

$row = 0;
if(count($states) > 0) {
	foreach($states as $line) {
		$line_split = preg_split("/\s+/", $line);
		$type  = array_shift($line_split);
		$proto = array_shift($line_split);
		$state = array_pop($line_split);
		$info  = implode(" ", $line_split);

		/* break up info and extract $srcip and $dstip */
		$ends = preg_split("/\<?-\>?/", $info);

		if (strpos($info, '->') === FALSE) {
			$srcinfo = $ends[count($ends) - 1];
			$dstinfo = $ends[0];
		} else {
			$srcinfo = $ends[0];
			$dstinfo = $ends[count($ends) - 1];
		}

		$parts = split(":", $srcinfo);
		$srcip = trim($parts[0]);
		$srcport = trim($parts[1]);

		$parts = split(":", $dstinfo);
		$dstip = trim($parts[0]);
		$dstport = trim($parts[1]);

		addipinfo($srcipinfo, $srcip, $proto, $srcport, $dstport);
		addipinfo($dstipinfo, $dstip, $proto, $srcport, $dstport);
		addipinfo($pairipinfo, "{$srcip} -> {$dstip}", $proto, $srcport, $dstport);

		addipinfo($allipinfo, $srcip, $proto, $srcport, $dstport);
		addipinfo($allipinfo, $dstip, $proto, $srcport, $dstport);

	}
}

function sort_by_ip($a, $b) {
	return sprintf("%u", ip2long($a)) < sprintf("%u", ip2long($b)) ? -1 : 1;
}

function print_summary_table($label, $iparr, $sort = TRUE) { ?>

<h3><?php echo $label; ?></h3>
<table class="tabcont" width="100%" border="0" cellspacing="0" cellpadding="0">
	<tr>
		<td class="listhdrr">IP</td>
		<td class="listhdrr"># States</td>
		<td class="listhdrr">Proto</td>
		<td class="listhdrr"># States</td>
		<td class="listhdrr">Src Ports</td>
		<td class="listhdrr">Dst Ports</td>
	</tr>
<?php   if ($sort)
		uksort($iparr, "sort_by_ip");
	foreach($iparr as $ip => $ipinfo) { ?>
	<tr>
		<td class='vncell'><?php echo $ip; ?></td>
		<td class='vncell'><?php echo $ipinfo['seen']; ?></td>
		<td class='vncell'>&nbsp;</td>
		<td class='vncell'>&nbsp;</td>
		<td class='vncell'>&nbsp;</td>
		<td class='vncell'>&nbsp;</td>
	</tr>
	<?php foreach($ipinfo['protos'] as $proto => $protoinfo) { ?>
	<tr>
		<td class='list'>&nbsp;</td>
		<td class='list'>&nbsp;</td>
		<td class='listlr'><?php echo $proto; ?></td>
		<td class='listr' align="center"><?php echo $protoinfo['seen']; ?></td>
		<td class='listr' align="center"><?php echo count($protoinfo['srcports']); ?></td>
		<td class='listr' align="center"><?php echo count($protoinfo['dstports']); ?></td>
	</tr>
	<?php } ?>
<?php } ?>

</table>

<?
}

$pgtitle = array("Diagnostics", "State Table Summary");
require_once("guiconfig.inc");
include("head.inc");
include("fbegin.inc");


print_summary_table("By Source IP", $srcipinfo);
print_summary_table("By Destination IP", $dstipinfo);
print_summary_table("Total per IP", $allipinfo);
print_summary_table("By IP Pair", $pairipinfo, FALSE);
?>

<?php include("fend.inc"); ?>
