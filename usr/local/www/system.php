<?php
/* $Id$ */
/*
	system.php
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

require("guiconfig.inc");

$pconfig['hostname'] = $config['system']['hostname'];
$pconfig['domain'] = $config['system']['domain'];
list($pconfig['dns1'],$pconfig['dns2']) = $config['system']['dnsserver'];

$pconfig['dns1gwint'] = $config['system']['dns1gwint'];
$pconfig['dns2gwint'] = $config['system']['dns2gwint'];

$pconfig['dnsallowoverride'] = isset($config['system']['dnsallowoverride']);
$pconfig['webguiproto'] = $config['system']['webgui']['protocol'];
if (!$pconfig['webguiproto'])
	$pconfig['webguiproto'] = "http";
$pconfig['webguiport'] = $config['system']['webgui']['port'];
$pconfig['timezone'] = $config['system']['timezone'];
$pconfig['timeupdateinterval'] = $config['system']['time-update-interval'];
$pconfig['timeservers'] = $config['system']['timeservers'];
$pconfig['theme'] = $config['system']['theme'];

if (!isset($pconfig['timeupdateinterval']))
	$pconfig['timeupdateinterval'] = 300;
if (!$pconfig['timezone'])
	$pconfig['timezone'] = "Etc/UTC";
if (!$pconfig['timeservers'])
	$pconfig['timeservers'] = "pool.ntp.org";

$changedesc = "System: ";
$changecount = 0;

function is_timezone($elt) {
	return !preg_match("/\/$/", $elt);
}

if($pconfig['timezone'] <> $_POST['timezone']) {
	/* restart firewall log dumper helper */
	require_once("functions.inc");
	$pid = `ps awwwux | grep -v "grep" | grep "tcpdump -v -l -n -e -ttt -i pflog0"  | awk '{ print $2 }'`;
	if($pid) {
		mwexec("kill $pid");
		usleep(1000);
	}		
	filter_pflog_start();
}

exec('/usr/bin/tar -tzf /usr/share/zoneinfo.tgz', $timezonelist);
$timezonelist = array_filter($timezonelist, 'is_timezone');
sort($timezonelist);

if ($_POST) {

	$changecount++;
	
	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = split(" ", "hostname domain");
	$reqdfieldsn = split(",", "Hostname,Domain");

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);

	if ($_POST['hostname'] && !is_hostname($_POST['hostname'])) {
		$input_errors[] = "The hostname may only contain the characters a-z, 0-9 and '-'.";
	}
	if ($_POST['domain'] && !is_domain($_POST['domain'])) {
		$input_errors[] = "The domain may only contain the characters a-z, 0-9, '-' and '.'.";
	}
	if (($_POST['dns1'] && !is_ipaddr($_POST['dns1'])) || ($_POST['dns2'] && !is_ipaddr($_POST['dns2']))) {
		$input_errors[] = "A valid IP address must be specified for the primary/secondary DNS server.";
	}
	if ($_POST['webguiport'] && (!is_numericint($_POST['webguiport']) ||
			($_POST['webguiport'] < 1) || ($_POST['webguiport'] > 65535))) {
		$input_errors[] = "A valid TCP/IP port must be specified for the webConfigurator port.";
	}
	if (($_POST['password']) && ($_POST['password'] != $_POST['password2'])) {
		$input_errors[] = "The passwords do not match.";
	}

	$t = (int)$_POST['timeupdateinterval'];
	if (($t < 0) || (($t > 0) && ($t < 6)) || ($t > 1440)) {
		$input_errors[] = "The time update interval must be either 0 (disabled) or between 6 and 1440.";
	}
	foreach (explode(' ', $_POST['timeservers']) as $ts) {
		if (!is_domain($ts)) {
			$input_errors[] = "A NTP Time Server name may only contain the characters a-z, 0-9, '-' and '.'.";
		}
	}

	if (!$input_errors) {
		update_if_changed("hostname", $config['system']['hostname'], strtolower($_POST['hostname']));
		update_if_changed("domain", $config['system']['domain'], strtolower($_POST['domain']));

		if (update_if_changed("webgui protocol", $config['system']['webgui']['protocol'], $_POST['webguiproto']))
			$restart_webgui = true;
		if (update_if_changed("webgui port", $config['system']['webgui']['port'], $_POST['webguiport']))
			$restart_webgui = true;

		update_if_changed("timezone", $config['system']['timezone'], $_POST['timezone']);
		update_if_changed("NTP servers", $config['system']['timeservers'], strtolower($_POST['timeservers']));
		update_if_changed("NTP update interval", $config['system']['time-update-interval'], $_POST['timeupdateinterval']);

		/* pfSense themes */
		update_if_changed("System Theme", $config['theme'], $_POST['theme']);

		/* XXX - billm: these still need updating after figuring out how to check if they actually changed */
		unset($config['system']['dnsserver']);
		if ($_POST['dns1'])
			$config['system']['dnsserver'][] = $_POST['dns1'];
		if ($_POST['dns2'])
			$config['system']['dnsserver'][] = $_POST['dns2'];

		$olddnsallowoverride = $config['system']['dnsallowoverride'];

		unset($config['system']['dnsallowoverride']);
		$config['system']['dnsallowoverride'] = $_POST['dnsallowoverride'] ? true : false;

		if ($_POST['password']) {
			$config['system']['password'] = crypt($_POST['password']);
			update_changedesc("password changed via webConfigurator");
			sync_webgui_passwords();
		}

		/* which interface should the dns servers resolve through? */
		if($_POST['dns1gwint']) 
			$config['system']['dns1gwint'] = $pconfig['dns1gwint'];
		else 
			unset($config['system']['dns1gwint']);
		if($_POST['dns2gwint']) 
			$config['system']['dns2gwint'] = $pconfig['dns2gwint'];
		else
			unset($config['system']['dns2gwint']);

		if ($changecount > 0)
			write_config($changedesc);

		if ($restart_webgui) {
			global $_SERVER;
			list($host) = explode(":", $_SERVER['HTTP_HOST']);
			if ($config['system']['webgui']['port']) {
				$url="{$config['system']['webgui']['protocol']}://{$host}:{$config['system']['webgui']['port']}/system.php";
			} else {
				$url = "{$config['system']['webgui']['protocol']}://{$host}/system.php";
			}
		}

		$retval = 0;
		config_lock();
		$retval = system_hostname_configure();
		$retval |= system_hosts_generate();
		$retval |= system_resolvconf_generate();
		$retval |= system_password_configure();
		$retval |= services_dnsmasq_configure();
		$retval |= system_timezone_configure();
		$retval |= system_ntp_configure();

		if ($olddnsallowoverride != $config['system']['dnsallowoverride'])
			$retval |= interfaces_wan_configure();

		config_unlock();

		$savemsg = get_std_save_message($retval);
		if ($restart_webgui)
			$savemsg .= "<br />One moment...redirecting to {$url} in 10 seconds.";
	}
}

$pgtitle = array("System","General Setup");
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<?php if ($input_errors) print_input_errors($input_errors); ?>
<?php if ($savemsg) print_info_box($savemsg); ?>
<form action="system.php" method="post">
              <table width="100%" border="0" cellpadding="6" cellspacing="0">
                <tr>
                  <td width="22%" valign="top" class="vncellreq">Hostname</td>
                  <td width="78%" class="vtable"> <input name="hostname" type="text" class="formfld unknown" id="hostname" size="40" value="<?=htmlspecialchars($pconfig['hostname']);?>">
                    <br> <span class="vexpl">name of the firewall host, without
                    domain part<br>
                    e.g. <em>firewall</em></span></td>
                </tr>
                <tr>
                  <td width="22%" valign="top" class="vncellreq">Domain</td>
                  <td width="78%" class="vtable"> <input name="domain" type="text" class="formfld unknown" id="domain" size="40" value="<?=htmlspecialchars($pconfig['domain']);?>">
                    <br> <span class="vexpl">e.g. <em>mycorp.com</em> </span></td>
                </tr>
                <tr>
                  <td width="22%" valign="top" class="vncell">DNS servers</td>
                  <td width="78%" class="vtable"> <p>
                  <table>
                  	  <tr><td><b>DNS Server</td>
                      <?php
                      	$multiwan = false;
                      	foreach($config['interfaces'] as $int) 
                      		if($int['gateway']) 
                      			$multiwan = true;
                      	$ints = get_interface_list();
                      	if($multiwan) 
                      		echo "<td><b>Use gateway</td>";
                      ?>
                      </tr>
<?php for($dnscounter=1; $dnscounter<5; $dnscounter++): ?>
                      <tr>
                      <td>
                      <input name="dns<?php echo $dnscounter;?>" type="text" class="formfld unknown" id="dns<?php echo $dnscounter;?>" size="20" value="<?=htmlspecialchars($pconfig['dns' . $dnscounter]);?>">
                      </td>
                      <?php
                      	if($multiwan) {
                      		echo "<td><select name='dns{$dnscounter}gwint'>\n";
                      		echo "<option value=''>wan</option>";
                      		foreach($ints as $int) {
	                      		$friendly = $int['friendly'];
                      			if($config['interfaces'][$friendly]['gateway']) {
	                   				$selected = "";
	                   				if($pconfig['dns{$dnscounter}gwint'] == $int) 
	                   					$selected = " SELECTED";
	                   				echo "<option value='{$friendly}'{$selected}>{$friendly}</option>";
                      			}
                      		}
                      		echo "</select>";
                      	}
                      ?>
                      </td>
                      </tr>
<?php endfor; ?>
                      </table>
                      <br>
                      <span class="vexpl">IP addresses; these are also used for
                      the DHCP service, DNS forwarder and for PPTP VPN clients.
                      <br>
                      <?php
                      	if($multiwan) 
                      		echo "<br/>In addition, select the gateway for each DNS server.  You should have a unique DNS server per gateway.<br/>";
                      ?>
                      <br>
                      <input name="dnsallowoverride" type="checkbox" id="dnsallowoverride" value="yes" <?php if ($pconfig['dnsallowoverride']) echo "checked"; ?>>
                      <strong>Allow DNS server list to be overridden by DHCP/PPP
                      on WAN</strong><br>
                      If this option is set, {$g['product_name']} will use DNS servers assigned
                      by a DHCP/PPP server on WAN for its own purposes (including
                      the DNS forwarder). They will not be assigned to DHCP and
                      PPTP VPN clients, though.</span></p></td>
                </tr>
                <tr>
                  <td width="22%" valign="top" class="vncell">webConfigurator protocol</td>
                  <td width="78%" class="vtable"> <input name="webguiproto" type="radio" value="http" <?php if ($pconfig['webguiproto'] == "http") echo "checked"; ?>>
                    HTTP &nbsp;&nbsp;&nbsp; <input type="radio" name="webguiproto" value="https" <?php if ($pconfig['webguiproto'] == "https") echo "checked"; ?>>
                    HTTPS</td>
                </tr>
                <tr>
                  <td valign="top" class="vncell">webConfigurator port</td>
                  <td class="vtable"> <input name="webguiport" type="text" class="formfld unknown" id="webguiport" "size="5" value="<?=htmlspecialchars($config['system']['webgui']['port']);?>">
                    <br>
                    <span class="vexpl">Enter a custom port number for the webConfigurator
                    above if you want to override the default (80 for HTTP, 443
                    for HTTPS). Changes will take effect immediately after save.</span></td>
                </tr>
                <tr>
                  <td width="22%" valign="top" class="vncell">Time zone</td>
                  <td width="78%" class="vtable"> <select name="timezone" id="timezone">
                      <?php foreach ($timezonelist as $value): ?>
                      <option value="<?=htmlspecialchars($value);?>" <?php if ($value == $pconfig['timezone']) echo "selected"; ?>>
                      <?=htmlspecialchars($value);?>
                      </option>
                      <?php endforeach; ?>
                    </select> <br> <span class="vexpl">Select the location closest
                    to you</span></td>
                </tr>
                <!--
                <tr>
                  <td width="22%" valign="top" class="vncell">Time update interval</td>
                  <td width="78%" class="vtable"> <input name="timeupdateinterval" type="text" class="formfld unknown" id="timeupdateinterval" size="4" value="<?=htmlspecialchars($pconfig['timeupdateinterval']);?>">
                    <br> <span class="vexpl">Minutes between network time sync.;
                    300 recommended, or 0 to disable </span></td>
                </tr>
                -->
                <tr>
                  <td width="22%" valign="top" class="vncell">NTP time server</td>
                  <td width="78%" class="vtable"> <input name="timeservers" type="text" class="formfld unknown" id="timeservers" size="40" value="<?=htmlspecialchars($pconfig['timeservers']);?>">
                    <br> <span class="vexpl">Use a space to separate multiple
                    hosts (only one required). Remember to set up at least one
                    DNS server if you enter a host name here!</span></td>
                </tr>
				<tr>
					<td colspan="2" class="list" height="12">&nbsp;</td>
				</tr>
				<tr>
					<td colspan="2" valign="top" class="listtopic">Theme</td>
				</tr>
				<tr>
				<td width="22%" valign="top" class="vncell">&nbsp;</td>
				<td width="78%" class="vtable">
				    <select name="theme">
<?php
				$files = return_dir_as_array("/usr/local/www/themes/");
				foreach($files as $f) {
					if ( (substr($f, 0, 1) == "_") && !isset($config['system']['developer']) ) continue;
					if($f == "CVS") continue;
					$selected = "";
					if($f == $config['theme'])
						$selected = " SELECTED";
					if($config['theme'] == "" and $f == "pfsense")
						$selceted = " SELECTED";
					echo "\t\t\t\t\t"."<option{$selected}>{$f}</option>\n";
				}
?>
					</select>
					<strong>This will change the look and feel of {$g['product_name']}.</strong>
				</td>
				</tr>
				<tr>
                  <td width="22%" valign="top">&nbsp;</td>
                  <td width="78%"> <input name="Submit" type="submit" class="formbtn" value="Save">
                  </td>
                </tr>
              </table>
</form>
<?php include("fend.inc"); ?>
<?php
	// restart webgui if proto or port changed
	if ($restart_webgui) {
		echo "<meta http-equiv=\"refresh\" content=\"10;url={$url}\">";
	}
?>
</body>
</html>
<?php
if ($restart_webgui) {
	touch("/tmp/restart_webgui");
}
?>
