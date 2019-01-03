#!/bin/bash
if [ -e "$1"/api.*.log ]; then
	for l in $(ls -rt "$1"/api.*.log); do
		echo Archiving "$l"...
		cat "$l" >> "$1/api.log"
		rm -f "$l"
	done
fi
