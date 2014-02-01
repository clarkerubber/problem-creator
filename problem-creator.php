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
			//echo "\nURL: ".$game['game']['url']."\n";
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
		return json_encode($problems);
	} else {
		exit(1);
	}
}

$output = '';

if ( isset( $argv[1] ) ) {
	$output = problemGenerator( intval( $argv[1] ) );
} else {
	$output = problemGenerator();
}

echo $output;