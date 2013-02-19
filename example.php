<?php 

require_once('EZQ.php');

$ezq = new EZQ(4); //Init EZQ with four forked workers

$ezq->addJob(function() {
    sleep(1);
    return "Job 1";
});

$ezq->addJob(function() {
    sleep(1);
    return "Job 2";
});

$ezq->addJob(function() {
    sleep(1);
    return "Job 3";
});

$ezq->addJob(function() {
    sleep(1);
    return "Job 4";
});

$ezq->on('job.complete', function($result) {
    echo "Job result: " . $result . "\n";
});

$ezq->on('q.complete', function($results, $process_time) {
    echo "It took {$process_time} seconds to run: ";
    print_r($results) . "\n"; //$results is an array of all results
});

$ezq->run();

