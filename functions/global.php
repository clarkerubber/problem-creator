<?php

function createProblems ( $game ) {
	//Input: A game that consists of a list of moves.
	//Output: A list of problems
	global $BALANCED, $UNBALANCED;

	foreach ( $game['analysis'] as $moveKey => $move ) {
		echo ceil( $move['ply'] / 2 ).( ($move['ply']%2 == 0)? "... " : ". " ).$move['move'];

		$lines = array();

		if ( isset( $move['eval'] ) && isset( $game['analysis'][$moveKey + 1]['eval'] ) ){

			if ( ( $move['eval'] <= $BALANCED && $game['analysis'][$moveKey + 1]['eval'] >= $UNBALANCED ) 
				|| ( $move['eval'] >= -$BALANCED && $game['analysis'][$moveKey + 1]['eval'] <= -$UNBALANCED ) ) {

				//pass the moves list and position of change of advantage to a subprocess

				$lines[] = findCaptureLine( $game['uci'], $moveKey );


				echo " Change of advantage detected: ".$move['eval']." -> ".$game['analysis'][$moveKey + 1]['eval'];
			}
		}
		echo "\n";
		
		//var_dump($move);
	}
}

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

	//$captureArray = captureArray( $movesUci );
}

function buildCaptureTree( $moveString ) {
	//Input: List of moves in UCI format
	//Output: Map of the correct tactical line
	global $MAX_CAPTURE_LINES;

	$movesList = getMovesListFromPosition( $moveString, $MAX_CAPTURE_LINES, TRUE );
}

function getMovesListFromPosition ( $moveString, $maxLines, $allowForcedInclusion ) {
	global $FIRST_PASS_TIME, $SECOND_PASS_TIME, $ALT_THRESHOLD, $FORCED_INCLUSION;

	$uciOutput = getUci( $moveString, $FIRST_PASS_TIME );

	preg_match_all( "/info.*?cp (-?[0-9]*).*?([a-h][1-8][a-h][1-8][qrnb]?)/", $uciOutput, $matches );

	$candidateMoves = array();
	$candidateMovesEval = array();

	foreach ( $matches[2] as $key => $match ) {
		if ( !in_array( $match , $candidateMoves) ) {
			$candidateMoves[] = $match;
		}
	}
	print_r( $candidateMoves );
	foreach ( $candidateMoves as $key => $move ) {
		echo "$SECOND_PASS_TIME - $moveString$move \n";
		$candidateMovesEval[] = getPositionEval( "$moveString$move ", $SECOND_PASS_TIME );
	}

	array_multisort($candidateMovesEval, SORT_DESC, SORT_NUMERIC, $candidateMovesEval);

	$topEval = $candidateMovesEval[0];
	foreach ( $candidateMoves as $key => $move ) {
		if ( ( abs( $topEval - $candidateMovesEval[$key] ) <= $ALT_THRESHOLD
		&& $key < $maxLines
		&& gmp_sign( $topEval ) == gmp_sign( $candidateMovesEval[$key] ) )
		|| ( gmp_sign( $topEval ) * $candidateMovesEval[$key] >= $FORCED_INCLUSION
		&& $key < $maxLines ) ) {
			echo "SELECTED!\n";
		}
		echo $candidateMovesEval[$key]." - ".$move."\n";
	}
}

function getPositionEval( $moveString, $moveTime ) {
	$uciOutput = getUci( $moveString, $moveTime );
	//print_r($uciOutput);
	preg_match_all( "/cp (-?[0-9].*?) /", $uciOutput, $matches );
	return end( $matches[1] );
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

function testParser ( $game ) {
	//Input: a game from the API
	//Output: the game played out.
	$captureArray = captureArray ( explode( ' ', $game['uci'] ), $game['analysis'] );
	foreach ( $game['analysis'] as $key => $move ) {
		echo $move['move'].( ( $captureArray[$key] == 1 )? ' - CAPTURE' : '' )."\n";
	}
}

function captureArray ( $moves ) {
	//Input: A list of moves in coordinate notation (e2e4)
	//Output: A list of when captures occur

	$moves = explode( ' ', $moves );

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
	$oldPieceCount = 32;

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
				if ( $square !== 0 ) {
					$pieceCount++;
					//echo $square." ";
				}// else {
					//echo "- ";
				//}
			}
			//echo "\n";
		}

		if ( $pieceCount !== $oldPieceCount ) {
		//	echo "CAPTURE OCCURED!\n\n";
			$captureArray[] = 1;
		} else {
		//	echo "NO CAPTURE\n\n";
			$captureArray[] = 0;
		}
		$oldPieceCount = $pieceCount;
	}
	return $captureArray;
}