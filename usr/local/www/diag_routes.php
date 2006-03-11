<?php

/* $Id$ */
/*
	diag_routes.php
	Copyright (C) 2006 Fernando Lamos
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

include('guiconfig.inc');

$pgtitle = 'Diagnostics: Routing tables';

include('head.inc');

?>
<body link="#000000" vlink="#000000" alink="#000000">
<?php include("fbegin.inc"); ?>
<p class="pgtitle"><?=$pgtitle?></p>

<div id="mainarea">
<form action="diag_routes.php" method="post">
<table class="tabcont" width="100%" border="0" cellspacing="0" cellpadding="6">

<tr>
<td class="vncellreq" width="22%">Name resolution</td>
<td class="listr" width="78%">
<input type="checkbox" class="formfld" name="resolve" value="yes" <?php if ($_POST['resolve'] == 'yes') echo 'checked'; ?>>Enable</input>
<br />
<span class="expl">Enable this to attempt to resolve names when displaying the tables.</span>
</tr>

<tr>
<td class="vncellreq" width="22%">&nbsp;</td>
<td class="listr" width="78%">
<input type="submit" class="formbtn" name="submit" value="Show" />
<br />
<br />
<span class="vexpl"><span class="red"><strong>Note:</strong></span> By enabling name resolution, the query should take a bit longer. You can stop it at any time by clicking the Stop button in your browser.</span>
</td>
</tr>

</table>
</form>

<?php

	$netstat = ($_POST['resolve'] == 'yes' ? 'netstat -rW' : 'netstat -nrW');
	list($dummy, $internet, $internet6) = explode("\n\n", shell_exec($netstat));

	foreach (array(&$internet, &$internet6) as $tabindex => $table) {
		$elements = ($tabindex == 0 ? 8 : 8);
		$name = ($tabindex == 0 ? 'IPv4' : 'IPv6');
?>
<table class="tabcont" width="100%" cellspacing="0" cellpadding="6" border="0">
<tr><td class="listbg" colspan="<?=$elements?>"><font color="white"><strong><?=$name;?></strong></font></td></tr>
<? 
		foreach (explode("\n", $table) as $i => $line) {
			if ($i == 0) continue;

			if ($i == 1)
				$class = 'listhdrr';
			else
				$class = 'listr';

			print("<tr>\n");
			$j = 0;
			foreach (explode(' ', $line) as $entry) {
				if ($entry == '') continue;
				print("<td class=\"$class\">$entry</td>\n");
				$j++;
			}
			// The 'Expire' field might be blank
			if ($j == $elements - 1)
				print('<td class="listr">&nbsp;</td>' . "\n");
			print("</tr>\n");
		}
		print("</table>\n");
	} 

?>
</table>

</div>

<?php
include('fend.inc');
?>
