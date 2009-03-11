<?php
/*
        $Id$
        Copyright 2008 Seth Mos
        Part of pfSense widgets (www.pfsense.com)
        originally based on m0n0wall (http://m0n0.ch/wall)

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
require_once("guiconfig.inc");
require_once("pfsense-utils.inc");
require_once("functions.inc");

$a_gateways = return_gateways_array();

$gateways_status = array();
$gateways_status = return_gateways_status();

?>
         <table bgcolor="#990000" width="100%" border="0" cellspacing="0" cellpadding="0">
                <tr>
                  <td width="10%" class="listhdrr">Name</td>
                  <td width="10%" class="listhdrr">Gateway</td>
                  <td width="30%" class="listhdrr">RTT</td>
                  <td width="30%" class="listhdrr">Status</td>
                                </tr>
         <?php foreach ($a_gateways as $gateway) { ?>
                <tr>
                  <td class="listlr">
                                <?=$gateway['name'];?>
                  </td>
                  <td class="listr" align="center" >
                                <?=$gateway['gateway'];?>
                  </td>
                  <td class="listr" align="center" >
								<?=$gateways_status[$monitor]['delay'];?>
				  </td>
                  <td class="listr" >
                        <table border="0" cellpadding="0" cellspacing="2">
                        <?php
                                $monitor = $gateway['monitor'];
								if(empty($monitor)) {
									$monitor = $gateway['gateway'];
								}
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
                                                $online = "Warning, Latency";
                                                $bgcolor = "khaki";
                                                break;
                                        case "\"loss\"":
                                               $online = "Warning, Packetloss";
                                                $bgcolor = "khaki";
                                                break;
					default:
						$online = "No data";
                                }
                                echo "<tr><td bgcolor=\"$bgcolor\" > $online </td>";
                        ?>
                        </table>
                  </td>
                </tr>
        <?php
        	$i++;
       		}
        ?>
          </table>
