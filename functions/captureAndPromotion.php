<?php


function findCaptureLine ( $movesUci, $ply ) {
	//Input: A string of moves and the starting move for the tactical line
	//Output: The actual tactical line

	//The ply played is the starting position. The ply after that is the blunder, 
	//and the ply after that is what the user has to find.

	/*
	
	$start position <= FEN position from ply count
	$first player move <= next successive move

	*/
	$movesUci = explode( ' ', $movesUci );
	$startMoveString = '';
	for ( $x = 0; $x <= $ply + 1; $x++ ){
		$startMoveString .= $movesUci[$x].' ';
	}

	//now parse this string to stockfish!
	//echo "\n".$startMoveString."\n";
	//$uciOutput = getLines( $startMoveString );
	$solutionMap = buildCaptureTree( $startMoveString );

	if ( count( explode( ' ', $startMoveString ) ) % 2 == 0 ) {
		$color = 'black';
	} else {
		$color = 'white';
	}

	$output = FALSE;
	if ( $solutionMap !== FALSE ) {
		$output = array( 'tags' => array('hanging piece'), 'color' => $color, 'position' => $startMoveString, 'solution' => $solutionMap );
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
		//print_r($movesList);
		$output = $movesList;
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

		//if ( $gameMoves !== NULL ) {
		//	echo $gameMoves[$key]['move']." - ";
		//}

		if ( $move == 'e8c8' && $position[$reference['8']][$reference['e']] === 'k' ) {
			//black long castle
		//	echo "Black Long Castle - ";
			$position[$reference['8']][$reference['c']] = 'k';
			$position[$reference['8']][$reference['d']] = 'r';
			$position[$reference['8']][$reference['e']] = 0;
			$position[$reference['8']][$reference['a']] = 0;

		} else if ( $move == 'e8g8' &&  $position[$reference['8']][$reference['e']] === 'k' ) {
			//black short castle
		//	echo "Black Short Castle - ";
			$position[$reference['8']][$reference['g']] = 'k';
			$position[$reference['8']][$reference['f']] = 'r';
			$position[$reference['8']][$reference['e']] = 0;
			$position[$reference['8']][$reference['h']] = 0;

		} else if ( $move == 'e1c1' &&  $position[$reference['1']][$reference['e']] === 'K' ) {
			//white long castle
		//	echo "White Long Castle - ";
			$position[$reference['1']][$reference['c']] = 'K';
			$position[$reference['1']][$reference['d']] = 'R';
			$position[$reference['1']][$reference['e']] = 0;
			$position[$reference['1']][$reference['a']] = 0;

		} else if ( $move == 'e1g1' &&  $position[$reference['1']][$reference['e']] === 'K' ) {
			//white short castle
		//	echo "White Short Castle - ";
			$position[$reference['1']][$reference['g']] = 'K';
			$position[$reference['1']][$reference['f']] = 'R';
			$position[$reference['1']][$reference['e']] = 0;
			$position[$reference['1']][$reference['h']] = 0;

		} else if ( $position[$reference[$moveSplit[1]]][$reference[$moveSplit[0]]] === 'P' 
			&& $moveSplit[0] !== $moveSplit[2]
			&& $position[$reference[$moveSplit[3]]][$reference[$moveSplit[2]]] === 0 ) {
			//White en passant
		//	echo "White En Passant - ";
			$position[$reference[$moveSplit[3]]][$reference[$moveSplit[2]]] = 'P';
			$position[$reference[$moveSplit[3]]+1][$reference[$moveSplit[2]]] = 0;
			$position[$reference[$moveSplit[1]]][$reference[$moveSplit[0]]] = 0;

		} else if ( $position[$reference[$moveSplit[1]]][$reference[$moveSplit[0]]] == 'p' 
			&& $moveSplit[0] !== $moveSplit[2]
			&& $position[$reference[$moveSplit[3]]][$reference[$moveSplit[2]]] === 0 ) {
			//Black en passant
			//echo "Black En Passant - ";
			$position[$reference[$moveSplit[3]]][$reference[$moveSplit[2]]] = 'p';
			$position[$reference[$moveSplit[3]]-1][$reference[$moveSplit[2]]] = 0;
			$position[$reference[$moveSplit[1]]][$reference[$moveSplit[0]]] = 0;
			
		} else if ( count( $moveSplit ) == 5 ) {
			//promotion
			if ( $position[$reference[$moveSplit[1]]][$reference[$moveSplit[0]]] === 'P' ) {
				//echo "Promotion To ".strtoupper( $moveSplit[4] )." - ";
				$position[$reference[$moveSplit[3]]][$reference[$moveSplit[2]]] = strtoupper( $moveSplit[4] );
			} else {
				//echo "Promotion To ".strtolower( $moveSplit[4] )." - ";
				$position[$reference[$moveSplit[3]]][$reference[$moveSplit[2]]] = strtolower( $moveSplit[4] );
			}

			$position[$reference[$moveSplit[1]]][$reference[$moveSplit[0]]] = 0;

		} else if ( count( $moveSplit ) == 4 ) {
			//Normal move
			$position[$reference[$moveSplit[3]]][$reference[$moveSplit[2]]] = $position[$reference[$moveSplit[1]]][$reference[$moveSplit[0]]];
			$position[$reference[$moveSplit[1]]][$reference[$moveSplit[0]]] = 0;
		}

		$pieceCount = 0;

		//echo "$move\n";

		foreach ( $position as $rowKey => $row ) {
			foreach ( $row as $squareKey => $square ) {
				if ( $square === 'p' || $square === 'P' ) {
					$pieceCount += 1;
					//echo $square." ";
				} else if ( $square !== 0 ) {
					$pieceCount += 2;
				}// else {
					//echo "- ";
				//}
			}
			//echo "\n";
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

function testParser ( $game ) {
	//Input: a game from the API
	//Output: the game played out.
	$captureArray = captureArray ( explode( ' ', $game['uci'] ), $game['analysis'] );
	foreach ( $game['analysis'] as $key => $move ) {
		echo $move['move'].( ( $captureArray[$key] == 1 )? ' - CAPTURE' : '' )."\n";
	}
}