<?php


if ($_SERVER['REQUEST_URI'] == '/leaderboard/start') {
    require __DIR__ . '/../src/start.php';
} elseif ($_SERVER['REQUEST_URI'] == '/leaderboard/stop') {
    require __DIR__ . '/../src/stop.php';
} else {
    echo 'Unknown request';
}
