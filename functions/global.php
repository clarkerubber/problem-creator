<?php

function createProblems ( $game ) {
	//Input: A game that consists of a list of moves.
	//Output: A list of problems

	global $BALANCED, $UNBALANCED, $DIFFERENCE, $SESSION_START;

	$lines = array();

	foreach ( $game['analysis'] as $moveKey => $move ) {

		$SESSION_START = time();

		unset( $prevMoveEval );
		unset( $nextMoveEval );
		unset( $nextMoveMate );

		if ( isset( $game['analysis'][$moveKey + 1]['eval'] ) 
			&& isset( $game['analysis'][$moveKey - 1]['eval'] ) ) {

			$prevMoveEval = $game['analysis'][$moveKey - 1]['eval'];
			$nextMoveEval = $game['analysis'][$moveKey + 1]['eval'];

		} else if ( isset( $game['analysis'][$moveKey + 1]['mate'] ) ) {

			$nextMoveMate = $game['analysis'][$moveKey + 1]['mate'];

		}

		if ( isset( $move['eval'] ) 
			&& isset( $nextMoveEval ) 
			&& isset( $prevMoveEval ) ){

			if ( ( ( $prevMoveEval <= $BALANCED && $move['eval'] <= $BALANCED && $nextMoveEval >= $UNBALANCED )
				|| ( $prevMoveEval >= -$BALANCED && $move['eval'] >= -$BALANCED && $nextMoveEval <= -$UNBALANCED ) )
				&& abs( $move['eval'] - $nextMoveEval ) >= $DIFFERENCE  ) {

				//printf(" %5s -> %5s | Mate In %+6d \n", $lastMove, $move, -1 * $candidateMovesEval[$key] );
				echo $move['eval']." - ".$nextMoveEval." = ".abs( $move['eval'] - $nextMoveEval )."\n";
				echo "  Parent -> Child | CP Adv | Plies | Adv | Var | + | T | M | C\n";
				echo "==============================================================\n";
				$temp = findCaptureLine( $game['uci'], $moveKey );
				//$temp = FALSE;

				if ( $temp !== FALSE ) {

					$temp['id'] = $game['game']['id'];
					$lines[] = $temp;
					print_r($temp);

				}

			}

		} else if( isset( $move['eval'] ) && isset( $nextMoveMate ) ) {

			echo "Parent -> Child | Mate In \n";
			echo "==========================\n";
			$temp = findMateLine( $game['uci'], $moveKey, $nextMoveMate );

			if ( $temp !== FALSE ) {

				$temp['id'] = $game['game']['id'];
				$lines[] = $temp;
				print_r($temp);

			}

		} else if ( isset( $move['mate'] ) && isset( $nextMoveMate ) ) {

			if ( sign( $move['mate'] ) !== sign( $nextMoveMate ) ) {

				echo "Parent -> Child | Mate In \n";
				echo "==========================\n";
				$temp = findMateLine( $game['uci'], $moveKey, $nextMoveMate );

				if ( $temp !== FALSE ) {

					$temp['id'] = $game['game']['id'];
					$lines[] = $temp;
					print_r($temp);

				}
			}
		}
	}

	return $lines;
}

function getUci ( $moveSequence, $moveTime, $multiPv = 1 ) {

	global $STOCKFISH_PATH, $STOCKFISH_THREADS;

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
		fwrite( $pipes[0], "setoption name MultiPV value $multiPv\n" );
		if ( is_int( $STOCKFISH_THREADS ) && isset( $STOCKFISH_THREADS ) ) {
			fwrite( $pipes[0], "setoption name Threads value $STOCKFISH_THREADS\n" );
		}
		fwrite( $pipes[0], "position startpos moves $moveSequence\n" );
		fwrite( $pipes[0], "go movetime $moveTime\n" );
		usleep( 1000 * $moveTime + 100 );
		fwrite( $pipes[0], "quit\n" );
		fclose( $pipes[0] );

		$output = stream_get_contents( $pipes[1] );

		fclose( $pipes[1] );
	}
	//print_r($output);
	return $output;
}

function sign ( $number ) {

    return ( $number > 0 ) ? 1 : ( ( $number < 0 ) ? -1 : 0 ); 
} 

function testParser ( $game ) {
	//Input: a game from the API
	//Output: the game played out.

	$captureArray = captureArray ( explode( ' ', $game['uci'] ), $game['analysis'] );

	foreach ( $game['analysis'] as $key => $move ) {

		echo $move['move'].( ( $captureArray[$key] == 1 )? ' - CAPTURE' : '' )."\n";

	}
}