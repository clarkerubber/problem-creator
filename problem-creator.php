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

		$post = file_get_contents("http://en.lichess.org/api/problem?token=$LICHESS_API_TOKEN",null,stream_context_create(array(
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

	} else {

		exit( 1 );

	}
}

if ( isset( $argv[2] ) ) {

	for ( $x = 0; $x < intval( $argv[2] ); $x++ ) {
		echo problemGenerator( intval( $argv[1] ) )."\n";
	}

} else if ( isset( $argv[1] ) ) {

	echo problemGenerator( intval( $argv[1] ) );

} else {

	echo problemGenerator();

}