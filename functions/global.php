<?php

function createProblems ( $game ) {
	//Input: A game that consists of a list of moves.
	//Output: A list of problems
	global $BALANCED, $UNBALANCED;
	$lines = array();

	foreach ( $game['analysis'] as $moveKey => $move ) {
		//echo ceil( $move['ply'] / 2 ).( ($move['ply']%2 == 0)? ' ' : '. ' ).$move['move'].' ';

		if ( isset( $move['eval'] ) && isset( $game['analysis'][$moveKey + 1]['eval'] ) ){

			if ( ( $move['eval'] <= $BALANCED && $game['analysis'][$moveKey + 1]['eval'] >= $UNBALANCED ) 
				|| ( $move['eval'] >= -$BALANCED && $game['analysis'][$moveKey + 1]['eval'] <= -$UNBALANCED ) ) {

				//pass the moves list and position of change of advantage to a subprocess

				//echo " Change of advantage detected: ".$move['eval']." -> ".$game['analysis'][$moveKey + 1]['eval']."\n";
				$temp = findCaptureLine( $game['uci'], $moveKey );

				if ( $temp !== FALSE ) {
					$temp['id'] = $game['game']['id'];
					$lines[] = $temp;
				}

			} 
		} else if( isset( $move['eval'] ) && isset( $game['analysis'][$moveKey + 1]['mate'] ) ) {

			//echo " Forced mate detected: ".$move['eval']." -> ".$game['analysis'][$moveKey + 1]['mate']."\n";
			$temp = findMateLine( $game['uci'], $moveKey, $game['analysis'][$moveKey + 1]['mate'] );

			if ( $temp !== FALSE ) {
				$temp['id'] = $game['game']['id'];
				$lines[] = $temp;
			}

			//$lines[] = findMateLine( $game['uci'], $moveKey );
		} else if ( isset( $move['mate'] ) && isset( $game['analysis'][$moveKey + 1]['mate'] ) ) {

			if ( sign( $move['mate'] ) !== sign( $game['analysis'][$moveKey + 1]['mate'] ) ) {

				//echo " Mate sequence given to opponent ".$move['mate']." -> ".$game['analysis'][$moveKey + 1]['mate']."\n";
				$temp = findMateLine( $game['uci'], $moveKey, $game['analysis'][$moveKey + 1]['mate'] );

				if ( $temp !== FALSE ) {
					$temp['id'] = $game['game']['id'];
					$lines[] = $temp;
				}
			}

		}
	}
	return $lines;
}


function getMovesListFromPosition ( $moveString, $maxLines, $allowForcedInclusion, $player, $timeSinceMajorMove ) {
	global $FIRST_PASS_TIME, $SECOND_PASS_TIME, $ALT_THRESHOLD, $FORCED_INCLUSION, $MAJOR_MOVE_THRESHOLD;
	global $MINOR_MOVE_THRESHOLD, $MAX_CAPTURE_LINES;

	$uciOutput = getUci( $moveString, $FIRST_PASS_TIME );

	preg_match_all( "/info.*?cp (-?[0-9]+).*?([a-h][1-8][a-h][1-8][qrnb]?)/", $uciOutput, $matches );

	$candidateMoves = array();
	$candidateMovesEval = array();

	$lastMove = explode( ' ', $moveString );
	array_pop( $lastMove );
	$lastMove = array_pop( $lastMove );

	foreach ( $matches[2] as $key => $match ) {
		if ( !in_array( $match , $candidateMoves) ) {
			$candidateMoves[] = $match;
		}
	}
	//print_r( $candidateMoves );
	foreach ( $candidateMoves as $key => $move ) {
		//echo "$SECOND_PASS_TIME - $moveString$move \n";
		$candidateMovesEval[] = getPositionEval( "$moveString$move ", $SECOND_PASS_TIME );
	}

	array_multisort( $candidateMovesEval, SORT_ASC, SORT_NUMERIC, $candidateMoves );
	
	if ( !empty( $candidateMovesEval ) ) {
		while ( $candidateMovesEval[0] === FALSE ) {
			//echo "FALSE!\n";
			array_shift( $candidateMovesEval );
			array_shift( $candidateMoves );
			if ( empty( $candidateMovesEval ) ) {
				break;
			}
		}
	}
	

	if ( isset( $candidateMovesEval[0] ) ) {
		$topEval = $candidateMovesEval[0];
	}
	
	$moveArray = array();

	foreach ( $candidateMoves as $key => $move ) {
		if ( ( abs( $topEval - $candidateMovesEval[$key] ) <= $ALT_THRESHOLD
		&& $key < $maxLines
		&& sign( $topEval ) == sign( $candidateMovesEval[$key] ) )
		|| ( sign( $topEval ) * $candidateMovesEval[$key] >= $FORCED_INCLUSION
		&& $key < $maxLines
		&& $allowForcedInclusion == TRUE ) ) {
			/*
			if ( $player == TRUE ) {
				echo "P: $lastMove -> $move: ".$candidateMovesEval[$key];
			} else {
				echo "C: $lastMove -> $move: ".$candidateMovesEval[$key];
			}
			*/

			$captureThisTurn = FALSE;
			if( significantMove( $moveString.$move ) === TRUE ) {
				$parsedTimeSinceMajorMove = $MINOR_MOVE_THRESHOLD;
				$captureThisTurn = TRUE;
				//echo " - capture\n";
			} else {
				$parsedTimeSinceMajorMove = $timeSinceMajorMove - 1;
				//echo " - $parsedTimeSinceMajorMove \n";
			}
			
			if ( $player === TRUE && $parsedTimeSinceMajorMove > 0 ) {
				$moveArray[$move] = getMovesListFromPosition ( $moveString.$move.' ', 1, FALSE, FALSE, $parsedTimeSinceMajorMove );
			} else if ( $parsedTimeSinceMajorMove > 0 ) {
				$moveArray[$move] = getMovesListFromPosition ( $moveString.$move.' ', $MAX_CAPTURE_LINES, TRUE, TRUE, $parsedTimeSinceMajorMove );
			} else {
				$moveArray[$move] = 'end';
			}

			if ( $moveArray[$move] !== 'end' && ( $captureThisTurn === FALSE || $player === FALSE ) ) {
				$empty = TRUE;
				foreach ( $moveArray[$move] as $moveArrayValue ) {
					if ( $moveArrayValue !== 'end' ) {
						$empty = FALSE;
					}
				}
				if ( $empty == TRUE ) {
					$moveArray[$move] = 'end';
				}
			}
			//getMovesListFromPosition ( $moveString.$move.' ', )
			//echo "SELECTED!\n";
		}
		//echo $candidateMovesEval[$key]." - ".$move."\n";
	}

	if ( count( $moveArray ) > 1 ) {
		foreach ($moveArray as $key => $value) {
			if ( $value === 'end' ) {
				unset( $moveArray[$key] );
			}
		}
	}

	return $moveArray;
}

function significantMove ( $moveString ) {
	//Input: a list of moves in e2e4
	//Output: If the last move is a capture or promotion
	$output = FALSE;

	$moveList = explode( ' ', $moveString );

	if ( strlen( array_pop( $moveList ) ) == 5 ) {
		$output = TRUE;
	} else if ( isCapture( $moveString, TRUE ) ) {
		$output = TRUE;
	}
	return $output;
}

function getPositionEval ( $moveString, $moveTime ) {
	$uciOutput = getUci( $moveString, $moveTime );
	//print_r($uciOutput);
	$output = FALSE;

	preg_match_all( "/cp (-?[0-9]+) /", $uciOutput, $matches );

	$end = end( $matches[1] );

	if ( isset( $end ) ) {
		$output = $end;
	}

	return $output;
}

function getPositionMate ( $moveString, $moveTime ) {
	$uciOutput = getUci( $moveString, $moveTime );
	//print_r($uciOutput);
	$output = FALSE;
	preg_match_all( "/mate (-?[0-9]+) /", $uciOutput, $matches );
	//print_r($uciOutput);

	//print_r( $matches );

	$end = end( $matches[1] );
	//echo "getPositionMate: $end\n";
	if ( isset( $end ) ) {
		$output = $end;
	}

	return $output;
}

function getUci ( $moveSequence, $moveTime ) {
	global $STOCKFISH_PATH;

	//echo "\n $moveTime - $moveSequence\n";
	$descriptorspec = array(
		0 => array( "pipe", "r" ),  // stdin is a pipe that the child will read from
		1 => array( "pipe", "w" ),  // stdout is a pipe that the child will write to
		2 => array( "file", "/tmp/error-output.txt", "a" ) // stderr is a file to write to
	);

	$cwd = '/tmp';
	$env = array( 'some_option' => 'aeiou' );

	$process = proc_open( "$STOCKFISH_PATH", $descriptorspec, $pipes, $cwd, $env );

	if (is_resource($process)) {

		fwrite( $pipes[0], "uci\n" );
		fwrite( $pipes[0], "ucinewgame\n" );
		fwrite( $pipes[0], "isready\n" );
		fwrite( $pipes[0], "position startpos moves $moveSequence\n" );
		fwrite( $pipes[0], "go movetime $moveTime\n" );
		usleep( 1000 * $moveTime );
		fclose( $pipes[0] );

		$output = stream_get_contents( $pipes[1] );

		fclose( $pipes[1] );
	}
	return $output;
}

function sign ( $number ) { 
    return ( $number > 0 ) ? 1 : ( ( $number < 0 ) ? -1 : 0 ); 
} 
