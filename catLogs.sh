#!/bin/bash
for l in $(ls -rt "$1"/api.*.log 2>/dev/null); do
	echo Archiving "$l"...
	cat "$l" >> "$1/api.log"
	rm -f "$l"
done
