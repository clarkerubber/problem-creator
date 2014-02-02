<?php

include( "config.php" );
include( "functions/global.php" );
include( "functions/captureAndPromotion.php" );
include( "functions/mateSequence.php" );
include( "resources/keys.php" );

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

		$post = file_get_contents('http://en.lichess.org/api/problem?token=$LICHESS_API_TOKEN',null,stream_context_create(array(
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
		    echo $post;
		} else {
		    echo "POST failed";
		}

		return $json;

	} else {

		exit( 1 );

	}
}

$output = '';

if ( isset( $argv[2] ) ) {

	$output = problemGenerator( intval( $argv[1] ), $argv[2] );

} else if ( isset( $argv[1] ) ) {

	$output = problemGenerator( intval( $argv[1] ) );

} else {

	$output = problemGenerator();

}

echo "\n".$output;