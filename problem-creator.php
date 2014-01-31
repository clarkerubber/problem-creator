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
	if ( ( $games = json_decode( file_get_contents( "$url?nb=$nb" ), TRUE ) ) !== FALSE ) {
		$problems = array();

		foreach ( $games['list'] as $gameKey => $game ) {
			echo "URL: ".$game['game']['url']."\n";
			if ( !isset( $game['game']['initialFen'] ) ) {
				$problems[] = createProblems( $game );
				//testParser( $game );
			}
		}

	}
}

problemGenerator( 6 );