<?php
require __DIR__ . '/../vendor/autoload.php';

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('Access-Control-Allow-Origin: http://client.dtl.name');
// header('Access-Control-Allow-Credentials: true');
header('X-Accel-Buffering: no'); // Nginx: unbuffered responses suitable for Comet and HTTP streaming applications

$RANDOM_ORG_API_URL = 'https://api.random.org/json-rpc/4/invoke';
$maxIteration = 3;
function send_message($id, $message, $progress) {
    $d = array('message' => $message , 'progress' => $progress);

    echo "event: message" . PHP_EOL;
    echo "id: $id" . PHP_EOL;
    echo "data: " . json_encode($d) . PHP_EOL;
    echo PHP_EOL;

    ob_flush();
    flush();
}


$headers = array('Accept' => 'application/json', 'Content-Type' => 'application/json', );
$data = '{
    "jsonrpc": "2.0",
    "method": "generateIntegerSequences",
    "params": {
        "apiKey": "d35fd7f7-447f-411e-a9c2-5e21dd54dd09",
        "n": 2,
        "length": 10,
        "min": 1,
        "max": 100,
        "replacement": true,
        "base": 10,
        "pregeneratedRandomization": null
    },
    "id": 14088
}';

// We get two lists - ids of players and their scores
$predis = new Predis\Client();
for($i = 1; $i <= $maxIteration; $i++) {
    $response = Requests::post($RANDOM_ORG_API_URL, $headers, $data);

    if (!isset($response->status_code) || $response->status_code != 200) {
        send_message($i, $response->status_code .' - ' . $response->body . ' ERROR on iteration ' . $i . ' of ' .$maxIteration, $i*10);
    } else {
        $return_data = json_decode($response->body);
        $seeded_players = array_combine($return_data->result->random->data[0], $return_data->result->random->data[1]);
        foreach ($seeded_players as $playerId => $playerScore) {
            $predis->zincrby('leaderboard', $playerScore, 'Player' . $playerId);
        }
        $predis->publish('leaderboard', 'updated scores');
        send_message($i, print_r($seeded_players, true) . ' on iteration ' . $i . ' of ' .$maxIteration, round((100/$maxIteration)*$i, 2));
    }

    sleep(3);
}

send_message('CLOSE', 'Process complete', 100);
