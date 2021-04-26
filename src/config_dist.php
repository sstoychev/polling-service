<?php
$config  = new stdClass();

// the name of the redis channel
$config->leaderboard = 'leaderboard';
// how many rounds to simulate maximum
$config->maxRounds = 10;
// time to sleep between rounds
$config->timeout = 3;


//////////// random org settings//////////////
// the key for random.org api
$config->apiKey = '';
// how many players to simulate at once
$config->length = 10;
// how many are all the players in the tournament
$config->max = 100;
