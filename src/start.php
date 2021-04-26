<?php
require __DIR__ . '/../vendor/autoload.php';
require 'config.php';

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('Access-Control-Allow-Origin: http://client.dtl.name');
// header('Access-Control-Allow-Credentials: true');
header('X-Accel-Buffering: no'); // Nginx: unbuffered responses suitable for Comet and HTTP streaming applications

$RANDOM_ORG_API_URL = 'https://api.random.org/json-rpc/4/invoke';

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
$data = [
    "jsonrpc" => "2.0",
    "method" => "generateIntegerSequences",
    "params" => [
        "apiKey" => $config->apiKey,
        "n" => 2,
        "length" => $config->length,
        "min" => 1,
        "max" => $config->max,
        "replacement" => true,
        "base" => 10,
        "pregeneratedRandomization" => null
    ],
    "id" => rand(10000, 10000)
];

// We get two lists - ids of players and their scores
$predis = new Predis\Client();
$startPolling = $predis->get('startPolling');

// don't allow two clients to start polling. Wait timeout* 2 bofore next poll
if (is_null($startPolling) || $startPolling < (time() - ($config->timeout*2)) ) {
    $stopped = false;
    for($i = 1; $i <= $config->maxRounds; $i++) {
        //check if we want to stop
        $stop = $predis->get('stopPolling');
        if (!is_null($stop)) {
            $stopped = true;
            break;
        }
        // set the start time
        $predis->set('startPolling', time());
        $response = Requests::post($RANDOM_ORG_API_URL, $headers, json_encode($data));
        if (!isset($response->status_code) || $response->status_code != 200) {
            send_message($i, $response->status_code .' - ' . $response->body . ' ERROR on iteration ' . $i . ' of ' .$config->maxRounds, $i*10);
        } else {
            $return_data = json_decode($response->body);
            $seeded_players = array_combine($return_data->result->random->data[0], $return_data->result->random->data[1]);
            foreach ($seeded_players as $playerId => $playerScore) {
                $predis->zincrby($config->leaderboard, $playerScore, 'Player' . $playerId);
            }
            $predis->publish($config->leaderboard, 'updated scores');
            send_message($i, print_r($seeded_players, true) . ' on iteration ' . $i . ' of ' .$config->maxRounds, round((100/$config->maxRounds)*$i, 2));
        }

        sleep($config->timeout);
    }

    if ($stopped) {
        send_message('CLOSE', 'Stopped', 100);
        $predis->del('stopPolling');
    } else {
        send_message('CLOSE', 'Process complete', 100);
    }

} else {
    send_message('CLOSE', 'Already polling', 0);
}
