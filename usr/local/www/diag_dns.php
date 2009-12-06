<?php
/*
	diag_dns.php

	Copyright (C) 2009 Jim Pingle (jpingle@gmail.com)
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
	pfSense_MODULE:	dns
*/

$pgtitle = array("Diagnostics","DNS Lookup");
require("guiconfig.inc");

/* Cheap hack to support both $_GET and $_POST */
if ($_GET['host'])
	$_POST = $_GET;

if ($_POST) {
	unset($input_errors);

	$reqdfields = explode(" ", "host");
	$reqdfieldsn = explode(",", "Host");

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors);
	$host = trim($_POST['host']);

	if (!is_hostname($host) || is_ipaddr($host)) 
		$input_errors[] = "Host must be a valid hostname or IP address.";

	// Test resolution speed of each DNS server.
	if ((is_hostname($host) || is_ipaddr($host))) {
		$dns_speeds = array();
		list($pconfig['dns1'],$pconfig['dns2'],$pconfig['dns3'],$pconfig['dns4']) = $config['system']['dnsserver'];
		for ($dnscounter=1; $dnscounter<5; $dnscounter++) {
			$dns_server = $pconfig['dns' . $dnscounter];
			$query_time = `dig google.com @{$dns_server} | grep Query | cut -d':' -f2`;
			if($query_time == "")
				$query_time = "No response";
			$new_qt = array();
			$new_qt['dns_server'] = $dns_server;
			$new_qt['query_time'] = $query_time;			
			$dns_speeds[] = $new_qt;
			unset($new_qt);
		}
	}

	$type = "unknown";
	$resolved = "";
	$ipaddr = "";
	$hostname = "";
	if (!$input_errors) {
		if (is_ipaddr($host)) {
			$type = "ip";
			$resolved = gethostbyaddr($host);
			$ipaddr = $host;
			if ($host != $resolved)
				$hostname = $resolved;
		} elseif (is_hostname($host)) {
			$type = "hostname";
			$resolved = gethostbyname($host);
			$hostname = $host;
			if ($host != $resolved)
				$ipaddr = $resolved;
		}

		if ($host == $resolved) {
			$resolved = "No record found";
		}
	}
}

include("head.inc"); ?>
<body link="#000000" vlink="#000000" alink="#000000">
<? include("fbegin.inc"); ?>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
        <tr>
                <td>
<?php if ($input_errors) print_input_errors($input_errors); ?>
	<form action="diag_dns.php" method="post" name="iform" id="iform">
	  <table width="100%" border="0" cellpadding="6" cellspacing="0">
		<tr>
			<td colspan="2" valign="top" class="listtopic">Resolve DNS hostname or IP</td>
		</tr>
        <tr>
		  <td width="22%" valign="top" class="vncellreq">Hostname or IP</td>
		  <td width="78%" class="vtable">
            <?=$mandfldhtml;?><input name="host" type="text" class="formfld" id="host" size="20" value="<?=htmlspecialchars($host);?>">
			<? if ($resolved && $type) { ?>
			=  <font size="+1"><?php echo $resolved; ?><font size="-1>">
			<?	} ?>
		  </td>
		</tr>
		<tr>
		  <td width="22%" valign="top" class="vncellreq">Resolution time per server</td>
		  <td width="78%" class="vtable">
				<table border="1" cellpadding="2" style="border-width: 1px 1px 1px 1px; border-collapse: collapse;">
					<tr>
						<td>
							<b>Server</b>
						</td>
						<td>
							<b>Query time</b>
						</td>
					</tr>
<?php
					if(is_array($dns_speeds)) 
						foreach($dns_speeds as $qt):
?>
					<tr>
						<td>
							<?=$qt['dns_server']?>
						</td>
						<td>
							<?=$qt['query_time']?>
						</td>
					</tr>
<?php
					endforeach;
?>
				</table>
		  </td>
		</tr>
		<?php if (!$input_errors && $ipaddr) { ?>
		<tr>
			<td width="22%" valign="top"  class="vncell">More Information:</td>
			<td width="78%" class="vtable">
				NOTE: These links are to external services, so their reliability cannot be guaranteed.<br/><br/>
				<a target="_new" href="http://private.dnsstuff.com/tools/whois.ch?ip=<?php echo $ipaddr; ?>">IP WHOIS @ DNS Stuff</a><br />
				<a target="_new" href="http://private.dnsstuff.com/tools/ipall.ch?ip=<?php echo $ipaddr; ?>">IP Info @ DNS Stuff</a>
			</td>
		</tr>
		<?php } ?>
		<tr><td>&nbsp;</td></tr>
		<tr>
		  <td width="22%" valign="top">&nbsp;</td>
		  <td width="78%">
            <input name="Submit" type="submit" class="formbtn" value="DNS Lookup">
		</td>
		</tr>
	</table>
</form>
</td></tr></table>
<?php include("fend.inc"); ?>
