#!/bin/sh

NOTSYNCED="true"
SERVER=`cat /cf/conf/config.xml | grep timeservers | cut -d">" -f2 | cut -d"<" -f1`

while [ "$NOTSYNCED" = "true" ]; do
	ntpdate $SERVER
	if [ "$?" = "0" ]; then
		NOTSYNCED="false"
	fi
	sleep 5
done

# Launch -- we have net.
killall ntpd 2>/dev/null
sleep 1
/usr/local/sbin/ntpd -s -f /var/etc/ntpd.conf
