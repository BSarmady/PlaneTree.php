<?php

$execution_time_laps = [];
$execution_time_previous = $_SERVER["REQUEST_TIME_FLOAT"];

#region function debug(...)
function debug($obj = '', $caption = null) {
    echo '<pre style="background-color:#DDEEFF;color:black;padding:5px">';
    if (isset($caption)) {
        echo '<b>' . $caption . '</b> ';
    }
    echo htmlentities(print_r($obj, true));
    echo '</pre>';
}

#endregion

#region function debug_json(...)
function debug_json($obj, $caption = null) {
    echo '<pre style="background-color:#DDEEFF;color:black;">';
    if (isset($caption)) {
        echo '<b>' . $caption . '</b> ';
    }
    echo htmlentities(json_encode($obj, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    echo '</pre>';
}

#endregion

#region function debug_execution_time_lap(...)
function debug_execution_time_lap($name, $exclude = false) {
    global $execution_time_laps, $execution_time_previous;
    $time = microtime(true);
    $lap = intval((microtime(true) - $execution_time_previous) * 1000000) / 1000;
    $execution_time_previous = $time;
    $execution_time_laps[] = [
        'name'    => $name,
        'time'    => $lap,
        'exclude' => $exclude
    ];
}

#endregion

#region function debug_execution_time(...)
function debug_execution_time(bool $withDetail = false) {
    global $execution_time_laps, $execution_time_previous;
    debug_execution_time_lap('final');
    $total = 0;
    foreach ($execution_time_laps as $lap) {
        $total += ($lap['exclude'] ? 0 : $lap['time']);
        if ($withDetail)
            echo $lap['name'] . ' : ' . $lap['time'] . ' ms<br>';
    }
    echo '<b>Total Execution Time:</b> ' . $total . ' ms<br>';
}
#endregion
