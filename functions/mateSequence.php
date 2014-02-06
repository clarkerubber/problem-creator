<?php

function findMateLine ( $movesUci, $ply, $adv ) {

	$movesUci = explode( ' ', $movesUci );
	$startMoveString = '';

	for ( $x = 0; $x <= $ply + 1; $x++ ){

		$startMoveString .= $movesUci[$x].' ';

	}

	if ( abs( $adv ) === 1 ) {

		$isMate = TRUE;

	} else {

		$isMate = FALSE;

	}

	$solutionMap = buildMateTree( $startMoveString, $isMate );

	if ( count( explode( ' ', $startMoveString ) ) % 2 == 0 ) {

		$color = 'black';

	} else {

		$color = 'white';

	}

	$output = FALSE;

	if ( $solutionMap !== FALSE ) {

		$output = array( 'tags' => array('forced mate'), 'color' => $color, 'position' => $startMoveString, 'solution' => $solutionMap );

	}

	return $output;
}

function buildMateTree ( $moveString, $isMate ) {

	$movesList = getMateMovesFromPosition( $moveString, TRUE, $isMate );
	$output = FALSE;

	$empty = TRUE;
	$abort = FALSE;

	if ( is_array( $movesList ) ) {
		foreach ( $movesList as $key => $value ) {

			if ( $value !== 'retry' ) {
				$empty = FALSE;
			}

			if ( $value === 'abort' ) {
				$abort = TRUE;
			}
		}
	}
	

	if ( $empty == FALSE && $abort == FALSE ) {

		$output = $movesList;

	}

	return $output;
}

function getMateMovesFromPosition ( $moveString, $player, $findMate ) {

	global $FIRST_PASS_TIME, $SECOND_PASS_TIME, $MAX_MATE_LINES;

	if ( $player == TRUE ) {
		$maxLines = $MAX_MATE_LINES;
	} else {
		$maxLines = 1;
	}

	$uciOutput = getUci( $moveString, $FIRST_PASS_TIME, $maxLines );

	preg_match_all( "/info.*?mate (-?[0-9]+).*?([a-h][1-8][a-h][1-8][qrnb]?)/", $uciOutput, $matches );

	$candidateMoves = array();
	$candidateMovesEval = array();

	$lastMove = explode( ' ', $moveString );
	array_pop( $lastMove );
	$lastMove = array_pop( $lastMove );

	foreach ( $matches[2] as $key => $match ) {

		if ( $findMate == TRUE ) {

			if ( $matches[1][$key] == 1 && !in_array( $match, $candidateMoves ) ) {

				$candidateMoves[] = $match;

			}

		} else if ( !in_array( $match, $candidateMoves ) ) {

			if ( ( $player == TRUE && $matches[1][$key] > 0 ) || $player == FALSE ) {

				$candidateMoves[] = $match;

			}

		}

	}

	$checkmate = FALSE;

	if ( $findMate === TRUE ) {

		$checkmate = TRUE;

		foreach ( $candidateMoves as $key => $value ) {

			$candidateMovesEval[$key] = 0;

		}

	} else {

		foreach ( $candidateMoves as $key => $move ) {

			$candidateMovesEval[] = getPositionMate( "$moveString$move ", $SECOND_PASS_TIME );

		}

		array_multisort( $candidateMovesEval, SORT_DESC, SORT_NUMERIC, $candidateMoves );
		
		if ( !empty( $candidateMovesEval ) ) {

			while ( $candidateMovesEval[0] === FALSE ) {

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

	}
	
	$moveArray = array();

	foreach ( $candidateMoves as $key => $move ) {

		if ( $key < $maxLines && $candidateMovesEval[$key] == $topEval ) {

			printf(" %5s -> %5s | %+3d \n", $lastMove, $move, - $candidateMovesEval[$key] );

			if ( $candidateMovesEval[$key] == 1 ) {

				$mateInOne = TRUE;

			} else {

				$mateInOne = FALSE;

			}
			
			if ( $player === TRUE && $checkmate === FALSE ) {

				$moveArray[$move] = getMateMovesFromPosition ( $moveString.$move.' ', FALSE, FALSE );

			} else if ( $player === FALSE ) {

				$moveArray[$move] = getMateMovesFromPosition ( $moveString.$move.' ', TRUE, $mateInOne );

			} else {

				$moveArray[$move] = 'win';

			}

		} else if ( $key < $maxLines && $candidateMovesEval[$key] = $topEval - 1 && $player == TRUE ) {
			$moveArray[$move] = 'retry';
		}
	}


	if ( !empty( $moveArray ) ) {

		$empty = TRUE;
		$abort = FALSE;

		foreach ( $moveArray as $key => $value ) {

			if ( $value !== 'retry' ) {

				$empty = FALSE;

			}

			if ( $value === 'abort' ) {
				$abort = TRUE;
			}

		}

		if ( $empty == TRUE || $abort == TRUE ) {
			$moveArray = 'abort';
			echo "$lastMove -> ABORT!\n";
		}

	} else {
		$moveArray = 'abort';
		echo "$lastMove -> ABORT!\n\n";
	}

	return $moveArray;
}

function getPositionMate ( $moveString, $moveTime ) {

	$uciOutput = getUci( $moveString, $moveTime );
	$output = FALSE;

	preg_match_all( "/mate (-?[0-9]+) /", $uciOutput, $matches );

	$end = end( $matches[1] );

	if ( isset( $end ) ) {

		$output = $end;

	}

	return $output;
}