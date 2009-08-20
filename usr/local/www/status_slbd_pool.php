<?php
/* $Id$ */
/*
	status_slbd_pool.php
	part of pfSense (http://www.pfsense.com/)

	Copyright (C) 2006 Seth Mos <seth.mos@xs4all.nl>.
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
##|*IDENT=page-status-loadbalancer-pool
##|*NAME=Status: Load Balancer: Pool page
##|*DESCR=Allow access to the 'Status: Load Balancer: Pool' page.
##|*MATCH=status_slbd_pool.php*
##|-PRIV

require("guiconfig.inc");

if (!is_array($config['load_balancer']['lbpool'])) {
	$config['load_balancer']['lbpool'] = array();
}
$a_pool = &$config['load_balancer']['lbpool'];

$slbd_logfile = "{$g['varlog_path']}/slbd.log";

$nentries = $config['syslog']['nentries'];
if (!$nentries)
        $nentries = 50;

$now = time();
$year = date("Y");

$pgtitle = array("Status","Load Balancer","Pool");
include("head.inc");

?>
<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<script src="/javascript/sorttable.js"></script>
<?php include("fbegin.inc"); ?>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
  <tr><td class="tabnavtbl">
  <?php
        /* active tabs */
        $tab_array = array();
        $tab_array[] = array("Pools", true, "status_slbd_pool.php");
        $tab_array[] = array("Virtual Servers", false, "status_slbd_vs.php");
        display_top_tabs($tab_array);
  ?>
  </td></tr>
  <tr>
    <td>
	<div id="mainarea">
              <table width="100%" border="0" cellpadding="0" cellspacing="0" class="tabcont" name="sortabletable" id="sortabletable">
                <tr>
                  <td width="10%" class="listhdrr">Name</td>
				  <td width="10%" class="listhdrr">Type</td>
                  <td width="10%" class="listhdrr">Gateways</td>
                  <td width="30%" class="listhdrr">Status</td>
                  <td width="30%" class="listhdr">Description</td>
				</tr>
			  <?php $i = 0; foreach ($a_pool as $vipent):
				if ($vipent['type'] == "gateway") {
			  ?>
                <tr>
                  <td class="listlr">
				<?=$vipent['name'];?>
                  </td>
                  <td class="listr" align="center" >
                                <?=$vipent['type'];?>
                                <br />
                                (<?=$vipent['behaviour'];?>)
                  </td>
                  <td class="listr" align="center" >
			<table border="0" cellpadding="0" cellspacing="2">
                        <?php
                                foreach ((array) $vipent['servers'] as $server) {
                                        $svr = split("\|", $server);
					PRINT "<tr><td> {$svr[0]} </td></tr>";
                                }
                        ?>
			</table>
                  </td>
                  <td class="listr" >
			<table border="0" cellpadding="0" cellspacing="2">
                        <?php
				if ($vipent['type'] == "gateway") {
					$poolfile = "{$g['tmp_path']}/{$vipent['name']}.pool";
					if(file_exists("$poolfile")) {
						$poolstatus = file_get_contents("$poolfile");
					}
                                        foreach ((array) $vipent['servers'] as $server) {
						$lastchange = "";
                                                $svr = split("\|", $server);
						$monitorip = $svr[1];
						$logstates = return_clog($slbd_logfile, $nentries, true, array("$monitorip", "marking"), "", true);

						$logstates = $logstates[0];

						if(stristr($logstates, $monitorip)) {
							$date = preg_split("/[ ]+/" , $logstates);
							$lastchange = "$date[0] $date[1] $year $date[2]";
						}
						if(stristr($poolstatus, $monitorip)) {
							$online = "Online";
							$bgcolor = "lightgreen";
							$change = $now - strtotime("$lastchange");
							if($change < 300) {
								$bgcolor = "khaki";
							}
						} else {
							$online = "Offline";
							$bgcolor = "lightcoral";
						}
						PRINT "<tr><td bgcolor=\"$bgcolor\" > $online </td><td>";
						if($lastchange <> "") {
							PRINT "Last change $lastchange";
						} else {
							PRINT "No changes found in logfile";
						}
						PRINT "</td></tr>";
                                        }
                                } else {
					PRINT "<tr><td> {$vipent['monitor']} </td></tr>";
                                }
                        ?>
			</table>
                  </td>
                  <td class="listbg" >
					<?=$vipent['desc'];?>
                  </td>
                </tr>
		<?php
			}
			$i++;
		 endforeach;
		 ?>
              </table>
	   </div>
	</table>

<?php include("fend.inc"); ?>
</body>
</html>
