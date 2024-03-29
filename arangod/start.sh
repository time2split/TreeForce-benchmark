#!/bin/bash

### BEGIN INIT INFO
# Provides: arangodb-memory-configuration
# Required-Start:
# Required-Stop:
# Default-Start: 2 3 4 5
# Default-Stop: 0 1 6
# Short-Description: Set arangodb kernel parameters
# Description: Set arangodb kernel parameters
### END INIT INFO


# 1 - Raise the vm map count value
sudo sysctl -w "vm.max_map_count=1536000"

# 2 - Disable Transparent Huge Pages
# sudo bash -c "echo madvise > /sys/kernel/mm/transparent_hugepage/enabled"
# sudo bash -c "echo madvise > /sys/kernel/mm/transparent_hugepage/defrag"
 
# 3 - Set the virtual memory accounting mode
# sudo bash -c "echo 0 > /proc/sys/vm/overcommit_memory"

arangod -c arangod/graal_server.conf
