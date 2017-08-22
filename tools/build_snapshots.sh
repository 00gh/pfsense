#!/bin/sh
#
# build_snapshots.sh
#
# part of pfSense (https://www.pfsense.org)
# Copyright (c) 2004-2016 Rubicon Communications, LLC (Netgate)
# All rights reserved.
#
# Redistribution and use in source and binary forms, with or without
# modification, are permitted provided that the following conditions are met:
#
# 1. Redistributions of source code must retain the above copyright notice,
#    this list of conditions and the following disclaimer.
#
# 2. Redistributions in binary form must reproduce the above copyright
#    notice, this list of conditions and the following disclaimer in
#    the documentation and/or other materials provided with the
#    distribution.
#
# 3. All advertising materials mentioning features or use of this software
#    must display the following acknowledgment:
#    "This product includes software developed by the pfSense Project
#    for use in the pfSense® software distribution. (http://www.pfsense.org/).
#
# 4. The names "pfSense" and "pfSense Project" must not be used to
#    endorse or promote products derived from this software without
#    prior written permission. For written permission, please contact
#    coreteam@pfsense.org.
#
# 5. Products derived from this software may not be called "pfSense"
#    nor may "pfSense" appear in their names without prior written
#    permission of the Electric Sheep Fencing, LLC.
#
# 6. Redistributions of any form whatsoever must retain the following
#    acknowledgment:
#
# "This product includes software developed by the pfSense Project
# for use in the pfSense software distribution (http://www.pfsense.org/).
#
# THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
# EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
# IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
# PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
# ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
# SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
# NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
# LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
# HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
# STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
# ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
# OF THE POSSIBILITY OF SUCH DAMAGE.

usage() {
	echo "Usage: $(basename $0) [-l] [-n] [-r] [-u] [-p]"
	echo "	-l: Build looped operations"
	echo "	-n: Do not build images, only core pkg repo"
	echo "	-p: Update poudriere repo"
	echo "	-r: Do not reset local changes"
	echo "	-u: Do not upload snapshots"
}

export BUILDER_TOOLS=$(realpath $(dirname ${0}))
export BUILDER_ROOT=$(realpath "${BUILDER_TOOLS}/..")

NO_IMAGES=""
NO_RESET=""
NO_UPLOAD=""
LOOPED_SNAPSHOTS=""
POUDRIERE_SNAPSHOTS=""

# Handle command line arguments
while getopts lnpru opt; do
	case ${opt} in
		n)
			NO_IMAGES="none"
			;;
		l)
			LOOPED_SNAPSHOTS=1
			;;
		p)
			POUDRIERE_SNAPSHOTS=--poudriere-snapshots
			;;
		r)
			NO_RESET=1
			;;
		u)
			NO_UPLOAD="-u"
			;;
		*)
			usage
			exit 1
			;;
	esac
done

if [ -n "${POUDRIERE_SNAPSHOTS}" ]; then
	export minsleepvalue=${minsleepvalue:-"360"}
else
	export minsleepvalue=${minsleepvalue:-"28800"}
fi
export maxsleepvalue=${maxsleepvalue:-"86400"}

# Keeps track of how many time builder has looped
export BUILDCOUNTER=0
export COUNTER=0

# Global variable used to control SIGINFO action
export _sleeping=0

snapshot_update_status() {
	${BUILDER_ROOT}/build.sh ${NO_UPLOAD} ${POUDRIERE_SNAPSHOTS} \
		--snapshot-update-status "$*"
}

exec_and_update_status() {
	local _cmd="${@}"

	[ -z "${_cmd}" ] \
		&& return 1

	# Ref. https://stackoverflow.com/a/30658405
	exec 4>&1
	local _result=$( \
	    { { ${_cmd} 2>&1 3>&-; printf $? 1>&3; } 4>&- \
	    | while read -r LINE; do \
	    snapshot_update_status "${LINE}"; done 1>&4; } 3>&1)
	exec 4>&-

	return ${_result}
}

git_last_commit() {
	[ -z "${NO_RESET}" ] \
		&& git -C "${BUILDER_ROOT}" reset --hard >/dev/null 2>&1
	git -C "${BUILDER_ROOT}" pull -q
	if [ -n "${POUDRIERE_SNAPSHOTS}" ]; then
		local _remote_repo=$(${BUILDER_ROOT}/build.sh -V POUDRIERE_PORTS_GIT_URL)
		local _remote_branch=$(${BUILDER_ROOT}/build.sh -V POUDRIERE_PORTS_GIT_BRANCH)
		export CURRENT_COMMIT=$(git ls-remote ${_remote_repo} ${_remote_branch} | cut -f1)
	else
		export CURRENT_COMMIT=$(git -C ${BUILDER_ROOT} log -1 --format='%H')
	fi
}

restart_build() {
	if [ ${_sleeping} -ne 0 ]; then
		snapshot_update_status ">>> SIGNINFO received, restarting build"
		COUNTER=$((maxsleepvalue + 60))
	fi
}

# This routine is called in between runs. We
# will sleep for a bit and check for new commits
# in between sleeping for short durations.
snapshots_sleep_between_runs() {
	# Handle SIGINFO (ctrl+T) and restart build
	trap restart_build SIGINFO

	# Initialize variables that keep track of last commit
	[ -z "${LAST_COMMIT}" ] \
		&& export LAST_COMMIT=${CURRENT_COMMIT}

	snapshot_update_status ">>> Sleeping for at least $minsleepvalue," \
		"at most $maxsleepvalue in between snapshot builder runs."
	snapshot_update_status ">>> Last known commit: ${LAST_COMMIT}"
	snapshot_update_status ">>> Freezing build process at $(date)"
	echo ">>> Press ctrl+T to start a new build"
	COUNTER=0
	_sleeping=1
	while [ ${COUNTER} -lt ${minsleepvalue} ]; do
		sleep 1
		COUNTER=$((COUNTER + 1))
	done

	if [ ${COUNTER} -lt ${maxsleepvalue} ]; then
		snapshot_update_status ">>> Thawing build process and" \
			"resuming checks for pending commits at $(date)."
		echo ">>> Press ctrl+T to start a new build"
	fi

	while [ $COUNTER -lt $maxsleepvalue ]; do
		sleep 1
		COUNTER=$(($COUNTER + 1))
		# Update this repo each 60 seconds
		if [ "$((${COUNTER} % 60))" != "0" ]; then
			continue
		fi
		git_last_commit
		if [ "${LAST_COMMIT}" != "${CURRENT_COMMIT}" ]; then
			snapshot_update_status ">>> New commit:" \
				"$CURRENT_COMMIT " \
				".. No longer sleepy."
			COUNTER=$(($maxsleepvalue + 60))
			export LAST_COMMIT="${CURRENT_COMMIT}"
		fi
	done
	_sleeping=0

	if [ $COUNTER -ge $maxsleepvalue ]; then
		snapshot_update_status ">>> Sleep timer expired." \
			"Restarting build."
		COUNTER=0
	fi

	trap "-" SIGINFO
}

# Main builder loop
while [ /bin/true ]; do
	BUILDCOUNTER=$((${BUILDCOUNTER}+1))

	git_last_commit

	OIFS=${IFS}
	IFS="
"
	if [ -n "${POUDRIERE_SNAPSHOTS}" ]; then
		exec_and_update_status \
		    ${BUILDER_ROOT}/build.sh --update-poudriere-ports \
		    || exit $?

		exec_and_update_status \
		    ${BUILDER_ROOT}/build.sh ${NO_UPLOAD} --update-pkg-repo \
		    || exit $?
	else
		exec_and_update_status \
		    ${BUILDER_ROOT}/build.sh --clean-builder \
		    || exit $?

		exec_and_update_status \
		    ${BUILDER_ROOT}/build.sh ${NO_UPLOAD} --flash-size '2g 4g' \
		    --snapshots ${NO_IMAGES} \
		    || exit $?
	fi
	IFS=${OIFS}

	if [ -z "${LOOPED_SNAPSHOTS}" ]; then
		# only one build required, exiting
		exit
	fi

	# Count some sheep or wait until a new commit turns up
	# for one days time.  We will wake up if a new commit
	# is detected during sleepy time.
	snapshots_sleep_between_runs
done
