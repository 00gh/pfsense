<?php
/* $Id$ */
/*
	vpn_openvpn_certs_create.php
	part of pfSense

	Copyright (C) 2004 Scott Ullrich
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

$pgtitle = array("VPN", "OpenVPN", "Create Certs");
$ovpncapath = $g['varetc_path']."/openvpn/certificates";
/* XXX: hardcoded path; worth making it a global?! */
$easyrsapath = "/usr/local/share/openvpn/certificates";

if($_GET['add'])
	$edit_mode = false;
else 
	$edit_mode = true;

if ($_GET['ca']) {
	if ($config['openvpn']['keys'][$_GET['ca']]) {
		$data = &$config['openvpn']['keys'][$_GET['ca']];
		$caname = trim($_GET['ca']);
		$cakeysize = $data['keysize'];
		$caexpire = $data['caexpire'];
		$cakeyexpire = $data['keyexpire'];
   		$countrycode= $data['keycountry'];
   	 	$stateorprovince= $data['keyprovince'];
    	$cityname= $data['keycity'];
    	$orginizationname= $data['keyorg'];
    	$email = $data['keyemail'];
		$authmode = $data['auth_method'];
		$edit_mode = true;
	} else
		$input_errors[] = "Certificate does not exist.";

} else 
	$disabled = "disabled=\"yes\"";

if ($_POST) {
	$cakeysize = $_POST['cakeysize'];
	$caexpire = $_POST['caexpire'];
	$cakeyexpire = $_POST['cakeyexpire'];
	$countrycode=$_POST['countrycode'];
    	$stateorprovince=$_POST['stateorprovince'];
    	$cityname=$_POST['cityname'];
    	$orginizationname=$_POST['orginizationname'];
    	$email = $_POST['email'];
	$authmode = $_POST['auth_method'];

	if (!$_POST['canme']) {
		$caname = $_POST['caname'];

	/* XXX: do more input validation */

	/* Create sane environment for easyrsa scripts */
	conf_mount_rw();
	if (!is_dir($g['varetc_path']."/openvpn"))
		safe_mkdir($g['varetc_path']."/openvpn");
	if (!is_dir($ovpncapath)) 
		safe_mkdir($ovpncapath);
	else
	    mwexec("rm -rf $ovpncapath/$caname");
	safe_mkdir("$ovpncapath/$caname", 0755);

	mwexec("cp -r $easyrsapath ".$g['varetc_path']."/openvpn/");
	if (!is_dir($ovpncapath)) {
		$input_errors[] = "Failed to create environment for creating certificates. ";
		header("Location: vpn_openvpn_certs.php");
	}

if ($authmode == 'pki') {
	$fd = fopen($ovpncapath . "/$caname/vars", "w");
	fwrite($fd, "#!/bin/tcsh\n");
	fwrite($fd, "setenv EASY_RSA \"$easyrsapath\" \n");
	fwrite($fd, "setenv OPENSSL \"`which openssl`\"\n");
	fwrite($fd, "setenv PKCS11TOOL \"pkcs11-tool\" \n");
	fwrite($fd, "setenv GREP \"grep\" \n");
	fwrite($fd, "setenv KEY_CONFIG `$ovpncapath/whichopensslcnf $ovpncapath` \n");
	fwrite($fd, "setenv KEY_DIR \"$ovpncapath/$caname\" \n");
	fwrite($fd, "setenv KEY_SIZE $cakeysize \n");
	fwrite($fd, "setenv CA_EXPIRE $caexpire \n");
	fwrite($fd, "setenv KEY_EXPIRE $cakeyexpire \n");
	fwrite($fd, "setenv KEY_COUNTRY $countrycode \n");
	fwrite($fd, "setenv KEY_PROVINCE $stateorprovince \n");
	fwrite($fd, "setenv KEY_CITY $cityname \n");
	fwrite($fd, "setenv KEY_ORG $orginizationname \n");
	fwrite($fd, "setenv KEY_EMAIL $email \n");
	fwrite($fd, "setenv CA_OK $ovpncapath/$caname/finished_ok\n");
	fwrite($fd, "\n\n");
	fclose($fd);

	$fd = fopen($ovpncapath . "/RUNME_FIRST", "w");
	fwrite($fd, "cd $ovpncapath \n");
	fwrite($fd, "touch $ovpncapath/$caname/index.txt \n");
	fwrite($fd, "echo \"01\" > $ovpncapath/$caname/serial \n");
	fwrite($fd, "source $ovpncapath/$caname/vars \n");
	//fwrite($fd, "echo \"Creating Shared Key...\" \n");
	//fwrite($fd, "openvpn --genkey --secret $ovpncapath/$caname/shared.key \n");
	fwrite($fd, "echo \"Creating CA...\" \n");
	fwrite($fd, "$easyrsapath/pkitool --batch --initca $ovpncapath/$caname/ca.crt \n");
	fwrite($fd, "echo \"Creating Server Certificate...\" \n");
	fwrite($fd, "$easyrsapath/pkitool --batch --server server \n");
	fwrite($fd, "echo \"Creating DH Parms...\" \n"); 
	fwrite($fd, "openssl dhparam -out $ovpncapath/$caname/dh_params.dh  $cakeysize \n");
	fwrite($fd, "echo \"Done!\" \n");
	fclose($fd);
}
	} else 
		$input_errors[] = "You should specify a name.";

        if (!is_array($config['openvpn']['keys']))
                $config['openvpn']['keys'] = array();
        $ovpnkeys =& $config['openvpn']['keys'];
        if (!is_array($ovpnkeys[$caname]))
                $ovpnkeys[$caname] = array();

if ($caname && $authmode == 'shared_key') {
        execute_command_return_output("openvpn --genkey --secret $ovpncapath/$caname/shared.key");
        $ovpnkeys[$caname]['existing'] = "yes";
        $ovpnkeys[$caname]['shared.key'] = file_get_contents("$ovpncapath/$caname/shared.key");
        write_config();

        header("Content-Type: application/octet-stream");
        header("Content-Disposition: attachment; filename=\"shared.key\";");
        header("Content-Transfer-Encoding: binary");
        header("Content-Length: ".filesize($filename));
        readfile("$ovpncapath/$caname/shared.key");
}

}

	include("head.inc");
?>

    <body link="#0000CC" vlink="#0000CC" alink="#0000CC">

<script type="text/javascript">
function edit_mode() {
	document.iform.cakeysize.disabled = true;
	document.iform.caexpire.disabled = true;
	document.iform.cakeyexpire.disabled = true;
	document.iform.countrycode.disabled = true;
	document.iform.stateorprovince.disabled = true;
	document.iform.cityname.disabled = true;
	document.iform.orginizationname.disabled = true;
	document.iform.email.disabled = true;
	document.iform.caname.disabled = true;
}
</script>

    <?php include("fbegin.inc"); ?>

<?php if ($input_errors) print_input_errors($input_errors); ?>

<form action="vpn_openvpn_certs_create.php" method="post" name="iform" id="iform">
<?php if ($savemsg) print_info_box($savemsg); ?>

	  <table width="100%" border="0" cellpadding="6" cellspacing="0">
	<tr><td colspan="2">
<?php
        $tab_array = array();
        $tab_array[0] = array("Server", false, "pkg.php?xml=openvpn.xml");
        $tab_array[1] = array("Client", false, "pkg.php?xml=openvpn_cli.xml");
        $tab_array[2] = array("Client-specific configuration", false, "pkg.php?xml=openvpn_csc.xml");
        $tab_array[3] = array("Certificate generation", true, "vpn_openvpn_certs.php");
        display_top_tabs($tab_array);
?>
	</td></tr>
<?php
      if ($_POST && $caname && $authmode == 'pki') { 
?>
<tr><td>
        <table class="tabcont" width="100%" border="0" cellpadding="6" cellspacing="0">
	<tr>
	<td>
	    <textarea cols="80" rows="35" name="output" id="output" wrap="hard"></textarea>
	</td>
	</tr>
	<tr>
	<td>
		<a href="vpn_openvpn_certs.php"><inpput name="OK" type="button" value="Return"></a>
	</td>
	</tr>
	</table></td></tr>
	</table>
<?php
	execute_command_return_output("/bin/tcsh $ovpncapath/RUNME_FIRST", "r");
	if (!file_exists("$ovpncapath/$caname/finished_ok")) {
		mwexec("rm -rf $ovpncapath/$caname");
		$input_errors = "An error occured while createing certificates\n";
	} else {
		conf_mount_ro();
		/* vars */
		$ovpnkeys[$caname]['existing'] = "no";
		$ovpnleys[$caname]['auth_method'] = $auth_method;
		$ovpnkeys[$caname]['KEYSIZE'] = $cakeysize;
		$ovpnkeys[$caname]['KEYEXPIRE'] = $cakeyexpire;
		$ovpnkeys[$caname]['CAEXPIRE'] = $caexpire;
		$ovpnkeys[$caname]['KEYCOUNTRY'] = $countrycode;
		$ovpnkeys[$caname]['KEYPROVINCE'] = $stateorprovince;
		$ovpnkeys[$caname]['KEYCITY'] = $cityname;
		$ovpnkeys[$caname]['KEYORG'] = $orginizationname;
		$ovpnkeys[$caname]['KEYEMAIL'] = $email;
		/* ciphers */
		$ovpnkeys[$caname]['ca.key'] = file_get_contents("$ovpncapath/$caname/ca.key");
		$ovpnkeys[$caname]['ca.crt'] = file_get_contents("$ovpncapath/$caname/ca.crt");
		$ovpnkeys[$caname]['server.key'] = file_get_contents("$ovpncapath/$caname/server.key");
		$ovpnkeys[$caname]['server.crt'] = file_get_contents("$ovpncapath/$caname/server.crt");
		$ovpnkeys[$caname]['dh_params.dh'] = file_get_contents("$ovpncapath/$caname/dh_params.dh");
	
		/* save it */
		write_config();
	}
} else { ?>
<tr><td>
        <table class="tabcont" width="100%" border="0" cellpadding="0" cellspacing="0">
	<tr>
            <td width="35%"  class="vncell"><B>Certificate Name</td>
            <td width="78%" class="vtable">
            <input name="caname" class="formfld" value="<?=$caname?>">
            </span></td>
        </tr>
        <tr>
            <td width="35%"  class="vncell"><B>Certificate Key Size</td>
            <td width="78%" class="vtable">
		<select <?=$disabled;?> name="cakeysize" >
<?php
			$strength = array("512", "1024", "2048");
			foreach ($strength as $key) {
				echo "<option value=\"{$key}\" ";
				if ($cakeysize == intval($key))		
					echo " selected=\"true\" ";
				echo ">{$key}</option>";	
			}
?>
		</select>
                <br/><span>Higher you set this value the slower TLS negotiation and DH key creation performance gets.</span></td>
            </tr>
            <tr>
                <td width="35%"  class="vncell"><B>Certificate Expire</td>
                <td width="78%" class="vtable">
                <input <?=$disabled;?> name="caexpire" class="formfld" value="<?=$caexpire?>"/>
                <br/><span>In how many days should the root CA key expire?</span></td>
            </tr>
            <tr>
                <td width="35%"  class="vncell"><B>Certificate Key Expire</td>
                <td width="78%" class="vtable">
                <input <?=$disabled;?> name="cakeyexpire" class="formfld" value="<?=$cakeyexpire?>">
                <br/><span>In how many days should certificates expire?</span></td>
            </tr>
	    <tr>
	      <td width="35%"  class="vncell"><B>Country Code (2 Letters)</td>
	      <td width="78%" class="vtable">
		<input <?=$disabled;?> size="2" maxlength="2" name="countrycode" class="formfld" value="<?=$countrycode?>">
		<br/></span></td>
	    </tr>
	    <tr>
	      <td width="35%"  class="vncell"><B>State or Province name</td>
	      <td width="78%" class="vtable">
		<input <?=$disabled;?> name="stateorprovince" class="formfld" value="<?=$stateorprovince?>">
		<br/></span></td>
	    </tr>
	    <tr>
	      <td width="35%"  class="vncell"><B>City name</td>
	      <td width="78%" class="vtable">
		<input <?=$disabled;?> name="cityname" class="formfld" value="<?=$cityname?>">
		<br/></span></td>
	    </tr>
	    <tr>
	      <td width="35%"  class="vncell"><B>Organization name</td>
	      <td width="78%" class="vtable">
		<input <?=$disabled;?> name="orginizationname" class="formfld" value="<?=$orginizationname?>">
		<br/></span></td>
	    </tr>
	    <tr>
	      <td width="35%"  class="vncell"><B>E-Mail address</td>
	      <td width="78%" class="vtable">
		<input <?=$disabled;?> name="email" class="formfld" value="<?=$email?>">
		<br/></span></td>
	    </tr>
	    <tr>
	      <td width="35%" >&nbsp;</td>
	      <td width="78%">
		<input name="Submit" type="submit" class="formbtn" value="Save">
		<a href="vpn_openvpn_certs.php?reset=<?=$caname;?>"><input name="Cancel" type="button" class="formbtn" value="Cancel"></a>
	      </td>
	    </tr>
	</table>
	</td></tr>
	</table>
	<?php
		if($edit_mode) {
			echo "<script language='javascript'>\n";
			echo "edit_mode();\n";
			echo "</script>\n";
		}
	?>
    <?php include("fend.inc"); ?>
    </body>
    </html>
<? } ?>
