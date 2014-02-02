<?php


function findCaptureLine ( $movesUci, $ply ) {
	//Input: A string of moves and the starting move for the tactical line
	//Output: The actual tactical line

	//The ply played is the starting position. The ply after that is the blunder, 
	//and the ply after that is what the user has to find.

	$movesUci = explode( ' ', $movesUci );
	$startMoveString = '';

	for ( $x = 0; $x <= $ply + 1; $x++ ){

		$startMoveString .= $movesUci[$x].' ';

	}

	$solutionMap = buildCaptureTree( $startMoveString );

	if ( count( explode( ' ', $startMoveString ) ) % 2 == 0 ) {

		$color = 'black';

	} else {

		$color = 'white';

	}

	$output = FALSE;

	if ( $solutionMap !== FALSE ) {

		$output = array( 'tags' => array('material advantage'), 'color' => $color, 'position' => $startMoveString, 'solution' => $solutionMap );

	}

	return $output;
}

function buildCaptureTree ( $moveString ) {
	//Input: List of moves in UCI format
	//Output: Map of the correct tactical line

	global $MAX_CAPTURE_LINES, $MAJOR_MOVE_THRESHOLD;

	$movesList = getMovesListFromPosition( $moveString, $MAX_CAPTURE_LINES, TRUE, TRUE, $MAJOR_MOVE_THRESHOLD );
	$output = FALSE;

	foreach ( $movesList as $key => $moveArray ) {

		if ( $moveArray !== 'end' ) {

			$empty = FALSE;

		} else {

			unset( $movesList[$key] );

		}

	}

	if ( !empty( $movesList ) ) {

		$output = $movesList;

	}

	return $output;
}


function getMovesListFromPosition ( $moveString, $maxLines, $allowForcedInclusion, $player, $timeSinceMajorMove ) {

	global $FIRST_PASS_TIME, $SECOND_PASS_TIME, $ALT_THRESHOLD, $MAJOR_MOVE_THRESHOLD;
	global $MINOR_MOVE_THRESHOLD, $MAX_CAPTURE_LINES;

	$uciOutput = getUci( $moveString, $FIRST_PASS_TIME, $maxLines );

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

	foreach ( $candidateMoves as $key => $move ) {

		$candidateMovesEval[] = getPositionEval( "$moveString$move ", $SECOND_PASS_TIME );

	}

	array_multisort( $candidateMovesEval, SORT_ASC, SORT_NUMERIC, $candidateMoves );
	
	if ( !empty( $candidateMovesEval ) ) {

		while ( $candidateMovesEval[0] === FALSE ) {

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

		if ( abs( $candidateMovesEval[$key] - $topEval ) <= abs( $topEval * $ALT_THRESHOLD )
			&& $key < $maxLines ) {

			echo "$lastMove -> $move: Adv ".$candidateMovesEval[$key]." $timeSinceMajorMove\n";

			$captureThisTurn = FALSE;

			if( significantMove( $moveString.$move ) == TRUE ) {

				$parsedTimeSinceMajorMove = $MINOR_MOVE_THRESHOLD;
				$captureThisTurn = TRUE;

			} else {

				$parsedTimeSinceMajorMove = $timeSinceMajorMove - 1;

			}
			
			if ( $player == TRUE && $parsedTimeSinceMajorMove > 0 ) {

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
		}
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
	$output = FALSE;

	preg_match_all( "/cp (-?[0-9]+) /", $uciOutput, $matches );

	$end = end( $matches[1] );

	if ( isset( $end ) ) {

		$output = $end;

	}

	return $output;
}

function isCapture ( $moveString, $major = FALSE ) {
	//Input: A string of moves in coordinate notation (e2e4)
	//	And if to limit results to major cpatures (i.e. not pawn captures)
	//Output: If the last move is a capture

	$moves = explode( ' ', $moveString );

	$position = 
		array(
				// a,  b,  c,  d,  e,  f,  g,  h
			array('r','n','b','q','k','b','n','r'),	// 8
			array('p','p','p','p','p','p','p','p'),	// 7
			array(0,0,0,0,0,0,0,0), 				// 6
			array(0,0,0,0,0,0,0,0),					// 5
			array(0,0,0,0,0,0,0,0),					// 4
			array(0,0,0,0,0,0,0,0),					// 3
			array('P','P','P','P','P','P','P','P'),	// 2
			array('R','N','B','Q','K','B','N','R'),	// 1
			); // indexed [number][letter]

	$reference = array(
			'a' => 0,
			'b' => 1,
			'c' => 2,
			'd' => 3,
			'e' => 4,
			'f' => 5,
			'g' => 6,
			'h' => 7,
			'1' => 7,
			'2' => 6,
			'3' => 5,
			'4' => 4,
			'5' => 3,
			'6' => 2,
			'7' => 1,
			'8' => 0
		);
	$captureArray = array();
	$oldPieceCount = 48;

	foreach ( $moves as $key => $move ) {

		$moveSplit = str_split( $move );

		if ( $move == 'e8c8' && $position[$reference['8']][$reference['e']] === 'k' ) {
			//black long castle

			$position[$reference['8']][$reference['c']] = 'k';
			$position[$reference['8']][$reference['d']] = 'r';
			$position[$reference['8']][$reference['e']] = 0;
			$position[$reference['8']][$reference['a']] = 0;

		} else if ( $move == 'e8g8' &&  $position[$reference['8']][$reference['e']] === 'k' ) {
			//black short castle

			$position[$reference['8']][$reference['g']] = 'k';
			$position[$reference['8']][$reference['f']] = 'r';
			$position[$reference['8']][$reference['e']] = 0;
			$position[$reference['8']][$reference['h']] = 0;

		} else if ( $move == 'e1c1' &&  $position[$reference['1']][$reference['e']] === 'K' ) {
			//white long castle

			$position[$reference['1']][$reference['c']] = 'K';
			$position[$reference['1']][$reference['d']] = 'R';
			$position[$reference['1']][$reference['e']] = 0;
			$position[$reference['1']][$reference['a']] = 0;

		} else if ( $move == 'e1g1' &&  $position[$reference['1']][$reference['e']] === 'K' ) {
			//white short castle

			$position[$reference['1']][$reference['g']] = 'K';
			$position[$reference['1']][$reference['f']] = 'R';
			$position[$reference['1']][$reference['e']] = 0;
			$position[$reference['1']][$reference['h']] = 0;

		} else if ( $position[$reference[$moveSplit[1]]][$reference[$moveSplit[0]]] === 'P' 
			&& $moveSplit[0] !== $moveSplit[2]
			&& $position[$reference[$moveSplit[3]]][$reference[$moveSplit[2]]] === 0 ) {
			//White en passant

			$position[$reference[$moveSplit[3]]][$reference[$moveSplit[2]]] = 'P';
			$position[$reference[$moveSplit[3]]+1][$reference[$moveSplit[2]]] = 0;
			$position[$reference[$moveSplit[1]]][$reference[$moveSplit[0]]] = 0;

		} else if ( $position[$reference[$moveSplit[1]]][$reference[$moveSplit[0]]] == 'p' 
			&& $moveSplit[0] !== $moveSplit[2]
			&& $position[$reference[$moveSplit[3]]][$reference[$moveSplit[2]]] === 0 ) {
			//Black en passant

			$position[$reference[$moveSplit[3]]][$reference[$moveSplit[2]]] = 'p';
			$position[$reference[$moveSplit[3]]-1][$reference[$moveSplit[2]]] = 0;
			$position[$reference[$moveSplit[1]]][$reference[$moveSplit[0]]] = 0;
			
		} else if ( count( $moveSplit ) == 5 ) {
			//promotion
			if ( $position[$reference[$moveSplit[1]]][$reference[$moveSplit[0]]] === 'P' ) {

				$position[$reference[$moveSplit[3]]][$reference[$moveSplit[2]]] = strtoupper( $moveSplit[4] );

			} else {

				$position[$reference[$moveSplit[3]]][$reference[$moveSplit[2]]] = strtolower( $moveSplit[4] );

			}

			$position[$reference[$moveSplit[1]]][$reference[$moveSplit[0]]] = 0;

		} else if ( count( $moveSplit ) == 4 ) {
			//Normal move
			$position[$reference[$moveSplit[3]]][$reference[$moveSplit[2]]] = $position[$reference[$moveSplit[1]]][$reference[$moveSplit[0]]];
			$position[$reference[$moveSplit[1]]][$reference[$moveSplit[0]]] = 0;
		}

		$pieceCount = 0;

		foreach ( $position as $rowKey => $row ) {

			foreach ( $row as $squareKey => $square ) {

				if ( $square === 'p' || $square === 'P' ) {

					$pieceCount += 1;

				} else if ( $square !== 0 ) {

					$pieceCount += 2;

				}

			}

		}

		if ( $oldPieceCount - $pieceCount >= 1 ) {

			if ( $major == TRUE ) {

				if ( $oldPieceCount - $pieceCount == 1 ) {

					$output = FALSE;

				} else if ( $oldPieceCount - $pieceCount == 2 ) {

					$output = TRUE;

				}

			} else {

				$output = TRUE;

			}

		} else {

			$output = FALSE;

		}

		$oldPieceCount = $pieceCount;

	}

	return $output;
}