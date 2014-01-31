<?php

function findMateLine ( $movesUci, $ply, $adv ) {

	$movesUci = explode( ' ', $movesUci );
	$startMoveString = '';
	for ( $x = 0; $x <= $ply + 1; $x++ ){
		$startMoveString .= $movesUci[$x].' ';
	}

	//now parse this string to stockfish!
	//echo "\n".$startMoveString."\n";
	//$uciOutput = getLines( $startMoveString );
	if ( abs( $adv ) === 1 ) {
		$isMate = TRUE;
	} else {
		$isMate = FALSE;
	}

	$solutionMap = buildMateTree( $startMoveString, $isMate );
}

function buildMateTree ( $moveString, $isMate ) {
	global $MAX_MATE_LINES, $MAJOR_MOVE_THRESHOLD;

	$movesList = getMateMovesFromPosition( $moveString, $MAX_MATE_LINES, TRUE, $isMate );
	$output = FALSE;
	
	print_r($movesList);

	return $output;
}

function getMateMovesFromPosition ( $moveString, $maxLines, $player, $findMate ) {
	//global $FIRST_PASS_TIME;
	//echo "hello,\n";
	global $FIRST_PASS_TIME, $SECOND_PASS_TIME, $MAX_MATE_LINES;

	$uciOutput = getUci( $moveString, $FIRST_PASS_TIME );
/*
	if ( $findMate === TRUE ) {
		print_r( $uciOutput );
	}
	*/
	//print_r($uciOutput);

	preg_match_all( "/info.*?mate (-?[0-9]+).*?([a-h][1-8][a-h][1-8][qrnb]?)/", $uciOutput, $matches );

	//print_r($matches);

	$candidateMoves = array();
	$candidateMovesEval = array();

	$lastMove = explode( ' ', $moveString );
	array_pop( $lastMove );
	$lastMove = array_pop( $lastMove );

	foreach ( $matches[2] as $key => $match ) {
		if ( !in_array( $match , $candidateMoves ) ) {
			//echo "In Mate: $match\n";
			$candidateMoves[] = $match;
		}
	}
	//print_r( $candidateMoves );

	$checkmate = FALSE;
	if ( $findMate === TRUE ) {
		$checkmate = TRUE;
		foreach ( $candidateMoves as $key => $value ) {
			//echo "is mate: $value\n";
			$candidateMovesEval[$key] = 0;
		}
	} else {
		foreach ( $candidateMoves as $key => $move ) {
			//echo "$SECOND_PASS_TIME - $moveString$move \n";
			$candidateMovesEval[] = getPositionMate( "$moveString$move ", $SECOND_PASS_TIME );
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
	}
	
	

	if ( isset( $candidateMovesEval[0] ) ) {
		$topEval = $candidateMovesEval[0];
	} else {
		echo "no top move\n";
	}
	
	$moveArray = array();

	foreach ( $candidateMoves as $key => $move ) {
		if ( $key < $maxLines ) {
			if ( $player === TRUE ) {
				echo "P: $lastMove -> $move: ".$candidateMovesEval[$key]."\n";
			} else {
				echo "C: $lastMove -> $move: ".$candidateMovesEval[$key]."\n";
			}

			if ( $candidateMovesEval[$key] == 1 ) {
				$mateInOne = TRUE;
			} else {
				$mateInOne = FALSE;
			}
			
			if ( $player === TRUE && $checkmate === FALSE ) {
				//echo "player = true\n";
				$moveArray[$move] = getMateMovesFromPosition ( $moveString.$move.' ', 1, FALSE, FALSE );
			} else if ( $player === FALSE ) {
				//echo "player = false, but there's more to be done\n";
				$moveArray[$move] = getMateMovesFromPosition ( $moveString.$move.' ', $MAX_MATE_LINES, TRUE, $mateInOne );
			} else {
				//echo "end\n";
				$moveArray[$move] = 'end';
			}
		}
	}
/*
	if ( count( $moveArray ) == 0 ) {
		echo " something went awfully wrong\n";
	}
*/
	return $moveArray;
}