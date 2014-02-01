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
	global $MAX_MATE_LINES, $MAJOR_MOVE_THRESHOLD;

	$movesList = getMateMovesFromPosition( $moveString, $MAX_MATE_LINES, TRUE, $isMate );
	$output = FALSE;

	if ( !empty( $movesList ) ) {

		$output = $movesList;

	}

	return $output;
}

function getMateMovesFromPosition ( $moveString, $maxLines, $player, $findMate ) {
	global $FIRST_PASS_TIME, $SECOND_PASS_TIME, $MAX_MATE_LINES;

	$uciOutput = getUci( $moveString, $FIRST_PASS_TIME );

	preg_match_all( "/info.*?mate (-?[0-9]+).*?([a-h][1-8][a-h][1-8][qrnb]?)/", $uciOutput, $matches );

	$candidateMoves = array();
	$candidateMovesEval = array();

	$lastMove = explode( ' ', $moveString );
	array_pop( $lastMove );
	$lastMove = array_pop( $lastMove );

	foreach ( $matches[2] as $key => $match ) {

		if ( !in_array( $match , $candidateMoves ) ) {

			$candidateMoves[] = $match;

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

		if ( $key < $maxLines ) {

			if ( $candidateMovesEval[$key] == 1 ) {

				$mateInOne = TRUE;

			} else {

				$mateInOne = FALSE;

			}
			
			if ( $player === TRUE && $checkmate === FALSE ) {

				$moveArray[$move] = getMateMovesFromPosition ( $moveString.$move.' ', 1, FALSE, FALSE );

			} else if ( $player === FALSE ) {

				$moveArray[$move] = getMateMovesFromPosition ( $moveString.$move.' ', $MAX_MATE_LINES, TRUE, $mateInOne );

			} else {

				$moveArray[$move] = 'end';

			}
		}
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