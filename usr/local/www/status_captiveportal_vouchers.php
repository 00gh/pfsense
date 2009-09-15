<?php
/*
    Copyright (C) 2007 Marcel Wiget <mwiget@mac.com>.
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
	pfSense_MODULE:	captiveportal
*/

##|+PRIV
##|*IDENT=page-status-captiveportal-vouchers
##|*NAME=Status: Captive portal Vouchers page
##|*DESCR=Allow access to the 'Status: Captive portal Vouchers' page.
##|*MATCH=status_captiveportal_vouchers.php*
##|-PRIV

$pgtitle = array("Status", "Captive portal", "Vouchers");
require("guiconfig.inc");
require_once("voucher.inc");

function clientcmp($a, $b) {
    global $order;
    return strcmp($a[$order], $b[$order]);
}

if (!is_array($config['voucher']['roll'])) {
    $config['voucher']['roll'] = array();
}
$a_roll = $config['voucher']['roll'];

$db = array();

foreach($a_roll as $rollent) {
    $roll = $rollent['number'];
    $minutes = $rollent['minutes'];
    $active_vouchers = file("{$g['vardb_path']}/voucher_active_$roll.db", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach($active_vouchers as $voucher => $line) {
        list($voucher,$timestamp, $minutes) = explode(",", $line);
        $remaining = (($timestamp + 60*$minutes) - time());
        if ($remaining > 0) {
            $dbent[0] = $voucher;
            $dbent[1] = $roll;  
            $dbent[2] = $timestamp;
            $dbent[3] = intval($remaining/60);
            $dbent[4] = $timestamp + 60*$minutes; // expires at 
            $db[] = $dbent;
        }
    }
}

if ($_GET['order']) { 
    $order = $_GET['order'];
    usort($db, "clientcmp");
}

include("head.inc");
include("fbegin.inc");
?>

<form action="status_captiveportal_vouchers.php" method="post" enctype="multipart/form-data" name="iform" id="iform">
<table width="100%" border="0" cellpadding="0" cellspacing="0" summary="tab pane">
<tr><td class="tabnavtbl">
<?php 
	$tab_array = array();
        $tab_array[] = array("Users", false, "status_captiveportal.php");
        $tab_array[] = array("Active Vouchers", true, "status_captiveportal_vouchers.php");
        $tab_array[] = array("Voucher Rolls", false, "status_captiveportal_voucher_rolls.php");
        $tab_array[] = array("Test Vouchers", false, "status_captiveportal_test.php");
        display_top_tabs($tab_array);
?> 
</td></tr>
<tr>
<td class="tabcont">

<table width="100%" border="0" cellpadding="0" cellspacing="0" summary="content pane">
  <tr>
    <td class="listhdrr"><a href="?order=0&showact=<?=$_GET['showact'];?>">Voucher</a></td>
    <td class="listhdrr"><a href="?order=1&showact=<?=$_GET['showact'];?>">Roll</a></td>
    <td class="listhdrr"><a href="?order=2&showact=<?=$_GET['showact'];?>">Activated at</a></td>
    <td class="listhdrr"><a href="?order=3&showact=<?=$_GET['showact'];?>">Expires in</a></td>
    <td class="listhdr"><a href="?order=4&showact=<?=$_GET['showact'];?>">Expires at</a></td>
    <td class="list"></td>
  </tr>
<?php foreach ($db as $dbent): ?>
  <tr>
    <td class="listlr"><?=$dbent[0];?></td>
    <td class="listr"><?=$dbent[1];?></td>
    <td class="listr"><?=htmlspecialchars(date("m/d/Y H:i:s", $dbent[2]));?></td>
    <td class="listr"><?=$dbent[3];?> min</td>
    <td class="listr"><?=htmlspecialchars(date("m/d/Y H:i:s", $dbent[4]));?></td>
    <td class="list"></td>
  </tr>
<?php endforeach; ?>
</table>
</td>
</tr>
</table>
</form>
<?php include("fend.inc"); ?>
