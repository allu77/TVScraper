#!/bin/bash
for l in $(ls -rt "$1"/api.*.log); do
	cat "$l" >> "$1/api.log"
	rm -f "$l"
done
