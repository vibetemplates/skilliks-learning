<?php
// Test script for timeout functionality

// Set execution time limit to 5 seconds
set_time_limit(5);

echo "Starting timeout test...\n";
echo "Script will attempt to run for 10 seconds but timeout is set to 5 seconds.\n\n";

// Record start time
$start_time = time();

// Loop that will run for 10 seconds
$i = 0;
while (time() - $start_time < 10) {
    $elapsed = time() - $start_time;
    echo "Running for $elapsed seconds (iteration $i)\n";
    flush(); // Ensure output is sent immediately
    sleep(1); // Sleep for 1 second
    $i++;
}

// This line should never be reached due to timeout
echo "Test completed without timeout - this shouldn't happen!\n";
?>