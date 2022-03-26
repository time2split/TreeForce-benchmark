#!/bin/bash

scriptName=$(basename -s '.sh' "$0")

export PARAMS="Psummary.filter.types=y"

com=$(sh/benchmark.sh 'DBLP[simplified]' "outputs/$scriptName" $*)
eval "php $com"