<?php
/* $Id$ */
/*
	interfaces_lagg_edit.php

	Copyright (C) 2008 Ermal Lu�i
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

require("guiconfig.inc");

if (!is_array($config['laggs']['lagg']))
	$config['laggs']['lagg'] = array();

$a_laggs = &$config['laggs']['lagg'];

$portlist = get_interface_list();

$checklist = get_configured_interface_list(false, true);
$realifchecklist = array();
foreach ($checklist as $tmpif)
	$realifchecklist[get_real_interface($tmpif)] = $tmpif;

$id = $_GET['id'];
if (isset($_POST['id']))
	$id = $_POST['id'];

if (isset($id) && $a_laggs[$id]) {
	$pconfig['laggif'] = $a_laggs[$id]['laggif'];
	$pconfig['members'] = $a_laggs[$id]['members'];
	$pconfig['proto'] = $a_laggs[$id]['proto'];
	$pconfig['descr'] = $a_laggs[$id]['descr'];
}

if ($_POST) {

	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "members proto");
	$reqdfieldsn = explode(",", "Member interfaces, Lagg protocol");

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);

	if (!$input_errors) {
		$lagg = array();
		$lagg['members'] = implode(',', $_POST['members']);
		$lagg['descr'] = $_POST['descr'];
		$lagg['laggif'] = $_POST['laggif'];

                $lagg['laggif'] = interface_lagg_configure($lagg);
                if ($lagg['laggif'] == "" || !stristr($lagg['laggif'], "lagg"))
                        $input_errors[] = "Error occured creating interface, please retry.";
                else {
                        if (isset($id) && $a_laggs[$id])
                                $a_laggs[$id] = $lagg;
                        else
                                $a_laggs[] = $lagg;

                        write_config();

			$confif = convert_real_interface_to_friendly_interface_name($lagg['laggif']);
                        if ($confif <> "")
                                interface_configure($confif);

			header("Location: interfaces_lagg.php");
			exit;
		}
	}
}

$pgtitle = array("Firewall","LAGG","Edit");
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<?php if ($input_errors) print_input_errors($input_errors); ?>
            <form action="interfaces_lagg_edit.php" method="post" name="iform" id="iform">
              <table width="100%" border="0" cellpadding="6" cellspacing="0">
				<tr>
					<td colspan="2" valign="top" class="listtopic">LAGG configuration</td>
				</tr>
				<tr>
                  <td width="22%" valign="top" class="vncellreq">Parent interface</td>
                  <td width="78%" class="vtable">
                    <select name="members[]" multiple="true" size="4" class="formselect">
                      <?php
					  	foreach ($portlist as $ifn => $ifinfo) {
							if (array_key_exists($ifn, $realifchecklist))
								continue;
							echo "<option value=\"{$ifn}\"";
							if (stristr($pconfig['members'], $ifn))
								echo "selected";
							echo ">". $ifn ."(".$ifinfo['mac'] .")</option>";
						}
		      		?>
                    </select>
			<br/>
			<span class="vexpl">Choose the members that will be used for the link aggregation.</span></td>
                </tr>
		<tr>
                  <td valign="top" class="vncellreq">Lag proto</td>
                  <td class="vtable">
                    <select name="proto" class="formselect" id="proto">
		<?php
		 foreach (array("none", "lacp", "failover", "fec", "loadbalance", "roundrobin") as $proto) {
			echo "<option value=\"{$proto}\"";
			if ($proto == $pconfig['proto'])
				echo "selected";
			echo ">".strtoupper($proto)."</option>";
		}
		?>
                    </select>					
                    <br/>
                    <span class="vexpl">
		   <ul>
		<li>
		    <b>failover</b><br/>      
			Sends and receives traffic only through the master port.  If
                  the master port becomes unavailable, the next active port is
                  used.  The first interface added is the master port; any
                  interfaces added after that are used as failover devices.
		</li><li>
     <b>fec</b><br/>          Supports Cisco EtherChannel.  This is a static setup and
                  does not negotiate aggregation with the peer or exchange
                  frames to monitor the link.
		</li><li>
     <b>lacp</b><br/>         Supports the IEEE 802.3ad Link Aggregation Control Protocol
                  (LACP) and the Marker Protocol.  LACP will negotiate a set
                  of aggregable links with the peer in to one or more Link
                  Aggregated Groups.  Each LAG is composed of ports of the
                  same speed, set to full-duplex operation.  The traffic will
                  be balanced across the ports in the LAG with the greatest
                  total speed, in most cases there will only be one LAG which
                  contains all ports.  In the event of changes in physical
                  connectivity, Link Aggregation will quickly converge to a
                  new configuration.
		</li><li>
     <b>loadbalance</b><br/>  Balances outgoing traffic across the active ports based on
                  hashed protocol header information and accepts incoming
                  traffic from any active port.  This is a static setup and
                  does not negotiate aggregation with the peer or exchange
                  frames to monitor the link.  The hash includes the Ethernet
                  source and destination address, and, if available, the VLAN
                  tag, and the IP source and destination address.
		</li><li>
     <b>roundrobin</b><br/>   Distributes outgoing traffic using a round-robin scheduler
                  through all active ports and accepts incoming traffic from
                  any active port.
		</li><li>
     <b>none</b><br/>         This protocol is intended to do nothing: it disables any
                  traffic without disabling the lagg interface itself.
		</li>
	</ul>
	          </span></td>
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
		    <input type="hidden" name="laggif" value="<?=$pconfig['laggif']; ?>">
                    <input name="Submit" type="submit" class="formbtn" value="Save"> <input type="button" value="Cancel" onclick="history.back()">
                    <?php if (isset($id) && $a_laggs[$id]): ?>
                    <input name="id" type="hidden" value="<?=$id;?>">
                    <?php endif; ?>
                  </td>
                </tr>
              </table>
</form>
<?php include("fend.inc"); ?>
</body>
</html>
