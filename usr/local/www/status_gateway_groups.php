<?php
/* $Id$ */
/*
	status_gateway_groups.php
	part of pfSense (http://pfsense.com)

	Copyright (C) 2007 Seth Mos <seth.mos@xs4all.nl>.
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
##|*IDENT=page-status-gatewaygroups
##|*NAME=Status: Gateway Groups page
##|*DESCR=Allow access to the 'Status: Gateway Groups' page.
##|*MATCH=status_gateway_groups.php*
##|-PRIV


require("guiconfig.inc");

if (!is_array($config['gateways']['gateway_group']))
	$config['gateways']['gateway_group'] = array();

$a_gateway_groups = &$config['gateways']['gateway_group'];
$changedesc = "Gateway Groups: ";

$gateways_status = return_gateways_status();

$pgtitle = array("Status","Gateway Groups");
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
	<table width="100%" border="0" cellpadding="0" cellspacing="0">
		<tr>
		  <td>
<?php
			$tab_array = array();
			$tab_array[0] = array("Gateways", false, "status_gateways.php");
			$tab_array[1] = array("Groups", true, "status_gateway_groups.php");
			display_top_tabs($tab_array);
?>
</td></tr>
 <tr>
   <td>
	<div id="mainarea">
             <table class="tabcont" width="100%" border="0" cellpadding="0" cellspacing="0">
                <tr>
                  <td width="20%" class="listhdrr">Group Name</td>
                  <td width="50%" class="listhdrr">Gateways</td>
                  <td width="30%" class="listhdr">Description</td>
		</tr>
			  <?php $i = 0; foreach ($a_gateway_groups as $gateway_group): ?>
                <tr>
                  <td class="listlr">
                    <?php
			echo $gateway_group['name'];
			?>
			
                  </td>
                  <td class="listr">
			<table border='0'>
                <?php
			/* process which priorities we have */
			$priorities = array();
			foreach($gateway_group['item'] as $item) {
				$itemsplit = explode("|", $item);
				$priorities[$itemsplit[1]] = true;
			}
			$priority_count = count($priorities);
			ksort($priorities);

			echo "<tr>";
			foreach($priorities as $number => $tier) {
				echo "<td width='120'>Tier $number</td>";
			}
			echo "</tr>\n";

			/* inverse gateway group to gateway priority */
			$priority_arr = array();
			foreach($gateway_group['item'] as $item) {
				$itemsplit = explode("|", $item);
				$priority_arr[$itemsplit[1]][] = $itemsplit[0];
			}
			ksort($priority_arr);
			$p = 1;
			foreach($priority_arr as $number => $tier) {
				/* for each priority process the gateways */
				foreach($tier as $member) {
					/* we always have $priority_count fields */
					echo "<tr>";
					$c = 1;
					while($c <= $priority_count) {
						if($p == $c) {
							$monitor = lookup_gateway_monitor_ip_by_name($member);
							switch($gateways_status[$monitor]['status']) {
							        case "None":
							                $online = "Online";
							                $bgcolor = "lightgreen";
							                break;
							        case "\"down\"":
							                $online = "Offline";
							                $bgcolor = "lightcoral";
							                break;
							        case "\"delay\"":
							                $online = "Latency";
							                $bgcolor = "khaki";
							                break;
							        case "\"loss\"":
							                $online = "Packetloss";
							                $bgcolor = "khaki";
							                break;
								default:
							                $online = "Unknown";
							                $bgcolor = "lightblue";
							                break;
							}
							echo "<td bgcolor='$bgcolor'>". htmlspecialchars($member) .", $online</td>";
						} else {
							echo "<td>&nbsp;</td>";
						}
						$c++;
					}
					echo "</tr>\n";
				}
				$p++;
			}
		    ?>
			</table>
                  </td>
                  <td class="listbg">
                    <?=htmlspecialchars($gateway_group['descr']);?>&nbsp;
                  </td>
		</tr>
			  <?php $i++; endforeach; ?>

	</table>
     </div>
    </td>
  </tr>
</table>
<?php include("fend.inc"); ?>
</body>
</html>
