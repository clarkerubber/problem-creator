<?php

include( "config.php" );
include( "functions/global.php" );

function problemGenerator ( $nb = 1, $url = "http://en.lichess.org/api/analysis" ) {
	/*
	Input: Amount of games to scan for tactical lines
	Output: Problems that can be played
	*/
	if ( ( $games = json_decode( file_get_contents( "$url?nb=$nb" ), TRUE ) ) !== FALSE ) {
		$problems = array();

		foreach ( $games['list'] as $gameKey => $game ) {
			echo "URL: ".$game['game']['url']."\n";
			$problems[] = createProblems( $game );
		}

	}
}

problemGenerator(5);