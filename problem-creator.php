<?php

include( "config.php" );
include( "functions/global.php" );
include( "functions/captureAndPromotion.php" );
include( "functions/mateSequence.php" );

function problemGenerator ( $nb = 1, $url = "http://en.lichess.org/api/analysis" ) {
	/*
	Input: Amount of games to scan for tactical lines
	Output: Problems that can be played
	*/
	global $LICHESS_API_TOKEN;

	if ( ( $games = json_decode( file_get_contents( "$url?nb=$nb" ), TRUE ) ) !== FALSE ) {
		$problems = array();

		foreach ( $games['list'] as $gameKey => $game ) {

			if ( !isset( $game['game']['initialFen'] ) ) {

				$temp = createProblems( $game );

				if ( !empty( $temp ) ) {

					foreach ( $temp as $problem ) {

						$problems[] = $problem;

					}
				}
			}
		}
	}

	if ( !empty( $problems ) ) {

		$json = json_encode( $problems );

		$post = file_get_contents("http://en.lichess.org/api/puzzle?token=$LICHESS_API_TOKEN",null,stream_context_create(array(
		    	'http' => array(
			        'protocol_version' => 1.1,
			        'user_agent'       => 'PHPExample',
			        'method'           => 'POST',
			        'header'           => "Content-type: application/json\r\n".
			                              "Connection: close\r\n" .
			                              "Content-length: " . strlen($json) . "\r\n",
			        'content'          => $json,
			    ),
			)));
		 
		if ($post) {
		    echo "$post\n";
		} else {
		    echo "POST failed\n";
		}

		return $json;

	}
}

echo "+ = Check, T = Tension between lower and higher value piece, M = Mate threat, C = Next best move capture\n\n";

$nbBatches = isset($argv[1]) ? $argv[1] : 9999;

for ( $x = 0; $x < intval($nbBatches); $x++ ) {
    printf( "Batch %10d of %10d\n", $x + 1, intval($nbBatches) );
    echo problemGenerator( 1 )."\n";
}
