<?php


function findCaptureLine ( $movesUci, $ply, $targetAdv ) {
	//Input: A string of moves and the starting move for the tactical line
	//Output: The actual tactical line

	//The ply played is the starting position. The ply after that is the blunder, 
	//and the ply after that is what the user has to find.

	$movesUci = explode( ' ', $movesUci );
	$startMoveString = '';

	for ( $x = 0; $x <= $ply + 1; $x++ ){

		$startMoveString .= $movesUci[$x].' ';

	}

	$solutionMap = buildCaptureTree( $startMoveString, $targetAdv );

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

function buildCaptureTree ( $moveString, $targetAdv ) {
	//Input: List of moves in UCI format
	//Output: Map of the correct tactical line

	global $MAX_CAPTURE_LINES, $MAJOR_MOVE_THRESHOLD;

	//function getMovesListFromPosition ( $moveString, $player, $tally, $pliesLeft ) {
	$movesList = getMovesListFromPosition( $moveString, TRUE, 0, $MAJOR_MOVE_THRESHOLD, $targetAdv );
	$output = FALSE;

	if ( !empty( $movesList ) && $movesList !== 'retry' ) {

		$output = $movesList;

	}

	return $output;
}


function getMovesListFromPosition ( $moveString, $player, $tally, $pliesLeft, $targetAdv ) {

	global $FIRST_PASS_TIME, $SECOND_PASS_TIME, $ALT_THRESHOLD, $RETRY_THRESHOLD, $MAJOR_MOVE_THRESHOLD;
	global $MAX_CAPTURE_LINES;

	if ( $player == TRUE ) {
		$maxLines = $MAX_CAPTURE_LINES;
	} else {
		$maxLines = 1;
	}

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

			$parsedTally = $tally;

			$changeThisTurn = abs( materialChange( $moveString.$move ) );
			$isCheck = isCheck( $moveString.$move );
			$isTension = isTension( $moveString.$move );

			if ( $player == TRUE ) {
				if ( $changeThisTurn > 1 ) {
					$parsedTally += $changeThisTurn;	
				}
			} else {
				$changeThisTurn = - $changeThisTurn;
				if ( $changeThisTurn < -1 ) {
					$parsedTally += $changeThisTurn;	
				}
			}
			//echo "  Parent -> Child | CP Adv | Plies | Adv | Var | + | T\n";
			if ( $player == TRUE ) {
				printf("P: %5s -> %5s | %+6d | %5d | %+3d | %+3d | %1s | %1s\n", $lastMove, $move, -1 * $candidateMovesEval[$key], $pliesLeft, $parsedTally, $changeThisTurn, $isCheck? '+' : '-', $isTension? '+' : '-' );
			} else {
				printf("C: %5s -> %5s | %+6d | %5d | %+3d | %+3d | %1s | %1s\n", $lastMove, $move, -1 * $candidateMovesEval[$key], $pliesLeft, $parsedTally, $changeThisTurn, $isCheck? '+' : '-', $isTension? '+' : '-' );
			}

			if ( $player == TRUE ) {

				if ( $parsedTally > 2 && $pliesLeft == 1 ) {
					$moveArray[$move] = 'win';
					echo "$move -> WIN\n";
				} else {
					if ( $changeThisTurn > 0 ) {
						//Something happened
						$moveArray[$move] = getMovesListFromPosition ( $moveString.$move.' ', FALSE, $parsedTally, $pliesLeft + 1, $targetAdv );
						if ( $moveArray[$move] === 'retry' && $parsedTally > 0 ) {
							$moveArray[$move] = 'win';
						}
					} else if ( $pliesLeft - 1 > 0 ) {
						//Nothing happened
						$moveArray[$move] = getMovesListFromPosition ( $moveString.$move.' ', FALSE, $parsedTally, $pliesLeft - 1, $targetAdv );
					} else {
						$moveArray[$move] = 'retry';
						echo "$move -> RETRY\n";
					}
				}

			} else {

				if ( $parsedTally <= 2 && $changeThisTurn === 0 && $pliesLeft - 1 > 0 ) {
					//Nothing has happened
					$moveArray[$move] = getMovesListFromPosition ( $moveString.$move.' ', TRUE, $parsedTally, $pliesLeft - 1, $targetAdv );
				} else if ( ( $parsedTally <= 2 && $changeThisTurn < 0 || $isCheck === TRUE ) &&  $pliesLeft - 1 > 0 ) {
					//Somthing has happened
					$moveArray[$move] = getMovesListFromPosition ( $moveString.$move.' ', TRUE, $parsedTally, $pliesLeft + 1, $targetAdv );
				} else if ( $parsedTally > 2 ) {
					$moveArray[$move] = 'win';
					echo "$move -> WIN\n";
				} else {
					$moveArray[$move] = 'retry';
					echo "$move -> RETRY\n";
				}
			}
		} else if ( abs( $candidateMovesEval[$key] - $topEval ) <= abs( $topEval * $RETRY_THRESHOLD ) && 
			abs( $candidateMovesEval[$key] - $topEval ) > abs( $topEval * $ALT_THRESHOLD ) ) {
			$moveArray[$move] = 'retry';
			echo "$move -> RETRY\n";
		}
	}

	$empty = TRUE;

	foreach ( $moveArray as $key => $value ) {

		if ( $value !== 'retry' ) {

			$empty = FALSE;

		}

	}

	if ( $empty == TRUE ) {
		$moveArray = 'retry';
		echo "$lastMove -> NO WIN\n";
	}


	return $moveArray;
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

function materialChange ( $moveString ) {
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

	$oldPieceCount = 48;

	foreach ( $moves as $key => $move ) {

		$position = updatePosition( $position, $move );

		$pieceCount = 0;

		foreach ( $position as $rowKey => $row ) {

			foreach ( $row as $squareKey => $square ) {

				if ( $square === 'p' || $square === 'P' ) {

					$pieceCount += 1;

				} else if ( $square === 'q' || $square === 'Q' ) {

					$pieceCount += 9;

				} else if ( $square === 'r' || $square === 'R' ) {

					$pieceCount += 5;

				} else if ( $square === 'b' || $square === 'B' || $square === 'n' || $square === 'N' ) {

					$pieceCount += 3;

				}

			}

		}

		if ( $oldPieceCount - $pieceCount !== 0 ) {

			$output = abs ( $pieceCount - $oldPieceCount );

		} else {

			$output = 0;

		}

		$oldPieceCount = $pieceCount;

	}

	if ( $output == 8 ) {
		$output = 9;
	} else if ( $output == 4 ) {
		$output = 5;
	} else if ( $output == 2 ) {
		$output = 3;
	}

	return $output;
}

function isCheck ( $moveString ) {
	//Input: A string of moves in coordinate notation (e2e4)
	//	And if to limit results to major cpatures (i.e. not pawn captures)
	//Output: If the last move is a capture
	//echo "help! I'm stuck!\n";
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

	foreach ( $moves as $key => $move ) {

		$position = updatePosition ( $position, $move );

		$isCheck = FALSE;

		foreach ( $position as $number => $column ) {
			//echo "1\n";
			foreach ( $column as $letter => $square ) {
				//echo "2\n";
				if ( $square !== 0 ) {
					// Now to work out if the locus of each piece hits an opposite side king
					if ( $square === 'q' ) {

						$collided = array( '+x' => FALSE, '+y' => FALSE, '-x' => FALSE, '-y' => FALSE,
							'+x+y' => FALSE, '+x-y' => FALSE, '-x+y' => FALSE, '-x-y' => FALSE );

						// straights

						// +x
						for ( $x = $number + 1; $x < 8; $x++ ) {
							//echo "$x 3\n";
							if ( $collided['+x'] === FALSE && $position[$x][$letter] === 'K' ) {
								$isCheck = TRUE;
							} else if ( $collided['+x'] === FALSE && $position[$x][$letter] !== 0 ) {
								$collided['+x'] = TRUE;
							}
						}

						// -x
						for ( $x = $number - 1; $x >= 0; $x-- ) {
							//echo "4\n";
							if ( $collided['-x'] === FALSE && $position[$x][$letter] === 'K' ) {
								$isCheck = TRUE;
							} else if ( $collided['-x'] === FALSE && $position[$x][$letter] !== 0 ) {
								$collided['-x'] = TRUE;
							}
						}

						// +y
						for ( $x = $number + 1; $x < 8; $x++ ) {
							//echo "5\n";
							if ( $collided['+y'] === FALSE && $position[$number][$x] === 'K' ) {
								$isCheck = TRUE;
							} else if ( $collided['+y'] === FALSE && $position[$number][$x] !== 0 ) {
								$collided['+y'] = TRUE;
							}
						}

						// -y
						for ( $x = $number - 1; $x >= 0; $x-- ) {
							//echo "6\n";
							if ( $collided['-y'] === FALSE && $position[$number][$x] === 'K' ) {
								$isCheck = TRUE;
							} else if ( $collided['-y'] === FALSE && $position[$number][$x] !== 0 ) {
								$collided['-y'] = TRUE;
							}
						}

						// diagonals

						// +x +y
						$x = $letter + 1;
						$y = $number + 1;
						while ( $x < 8 && $y < 8 && $x >= 0 && $y >= 0 ) {
							//echo "7\n";
							if ( $collided['+x+y'] == FALSE && $position[$y][$x] === 'K' ) {
								$isCheck = TRUE;
							} else if ( $collided['+x+y'] == FALSE && $position[$y][$x] !== 0 ) {
								$collided['+x+y'] = TRUE;
							}
							$x++;
							$y++;
						}

						// +x -y
						$x = $letter + 1;
						$y = $number - 1;
						while ( $x < 8 && $y < 8 && $x >= 0 && $y >= 0 ) {
							//echo "8\n";
							if ( $collided['+x-y'] == FALSE && $position[$y][$x] === 'K' ) {
								$isCheck = TRUE;
							} else if ( $collided['+x-y'] == FALSE && $position[$y][$x] !== 0 ) {
								$collided['+x-y'] = TRUE;
							}
							$x++;
							$y--;
						}

						// -x +y
						$x = $letter - 1;
						$y = $number + 1;
						while ( $x < 8 && $y < 8 && $x >= 0 && $y >= 0 ) {
							//echo "9\n";
							if ( $collided['-x+y'] == FALSE && $position[$y][$x] === 'K' ) {
								$isCheck = TRUE;
							} else if ( $collided['-x+y'] == FALSE && $position[$y][$x] !== 0 ) {
								$collided['-x+y'] = TRUE;
							}
							$x--;
							$y++;
						}

						// -x -y
						$x = $letter - 1;
						$y = $number - 1;
						while ( $x < 8 && $y < 8 && $x >= 0 && $y >= 0 ) {
							//echo "10\n";
							if ( $collided['-x-y'] == FALSE && $position[$y][$x] === 'K' ) {
								$isCheck = TRUE;
							} else if ( $collided['-x-y'] == FALSE && $position[$y][$x] !== 0 ) {
								$collided['-x-y'] = TRUE;
							}
							$x--;
							$y--;
						}


					} else if ( $square === 'r' ) {

						$collided = array( '+x' => FALSE, '+y' => FALSE, '-x' => FALSE, '-y' => FALSE );

						for ( $x = 0; $x++; $x < 8 ) {
							// straights

							// +x
							for ( $x = $number + 1; $x < 8; $x++ ) {
								//echo "11\n";
								if ( $collided['+x'] === FALSE && $position[$x][$letter] === 'K' ) {
									$isCheck = TRUE;
								} else if ( $collided['+x'] === FALSE && $position[$x][$letter] !== 0 ) {
									$collided['+x'] = TRUE;
								}
							}

							// -x
							for ( $x = $number - 1; $x >= 0; $x-- ) {
								//echo "12\n";
								if ( $collided['-x'] === FALSE && $position[$x][$letter] === 'K' ) {
									$isCheck = TRUE;
								} else if ( $collided['-x'] === FALSE && $position[$x][$letter] !== 0 ) {
									$collided['-x'] = TRUE;
								}
							}

							// +y
							for ( $x = $number + 1; $x < 8; $x++ ) {
								//echo "13\n";
								if ( $collided['+y'] === FALSE && $position[$number][$x] === 'K' ) {
									$isCheck = TRUE;
								} else if ( $collided['+y'] === FALSE && $position[$number][$x] !== 0 ) {
									$collided['+y'] = TRUE;
								}
							}

							// -y
							for ( $x = $number - 1; $x >= 0; $x-- ) {
								//echo "14\n";
								if ( $collided['-y'] === FALSE && $position[$number][$x] === 'K' ) {
									$isCheck = TRUE;
								} else if ( $collided['-y'] === FALSE && $position[$number][$x] !== 0 ) {
									$collided['-y'] = TRUE;
								}
							}

						}

					} else if ( $square === 'b' ) {

						$collided = array( '+x+y' => FALSE, '+x-y' => FALSE, '-x+y' => FALSE, '-x-y' => FALSE );

						// diagonals

						// +x +y
						$x = $letter + 1;
						$y = $number + 1;
						while ( $x < 8 && $y < 8 && $x >= 0 && $y >= 0 ) {
							//echo "15\n";
							if ( $collided['+x+y'] == FALSE && $position[$y][$x] === 'K' ) {
								$isCheck = TRUE;
							} else if ( $collided['+x+y'] == FALSE && $position[$y][$x] !== 0 ) {
								$collided['+x+y'] = TRUE;
							}
							$x++;
							$y++;
						}

						// +x -y
						$x = $letter + 1;
						$y = $number - 1;
						while ( $x < 8 && $y < 8 && $x >= 0 && $y >= 0 ) {
							//echo "16\n";
							if ( $collided['+x-y'] == FALSE && $position[$y][$x] === 'K' ) {
								$isCheck = TRUE;
							} else if ( $collided['+x-y'] == FALSE && $position[$y][$x] !== 0 ) {
								$collided['+x-y'] = TRUE;
							}
							$x++;
							$y--;
						}

						// -x +y
						$x = $letter - 1;
						$y = $number + 1;
						while ( $x < 8 && $y < 8 && $x >= 0 && $y >= 0 ) {
							//echo "17\n";
							if ( $collided['-x+y'] == FALSE && $position[$y][$x] === 'K' ) {
								$isCheck = TRUE;
							} else if ( $collided['-x+y'] == FALSE && $position[$y][$x] !== 0 ) {
								$collided['-x+y'] = TRUE;
							}
							$x--;
							$y++;
						}

						// -x -y
						$x = $letter - 1;
						$y = $number - 1;
						while ( $x < 8 && $y < 8 && $x >= 0 && $y >= 0 ) {
							//echo "18\n";
							if ( $collided['-x-y'] == FALSE && $position[$y][$x] === 'K' ) {
								$isCheck = TRUE;
							} else if ( $collided['-x-y'] == FALSE && $position[$y][$x] !== 0 ) {
								$collided['-x-y'] = TRUE;
							}
							$x--;
							$y--;
						}

					} else if ( $square === 'n' ) {
						//L shapes

						// ++x +y
						if ( $letter + 2 < 8 && $number + 1 < 8 ) {
							if ( $position[$number + 1][$letter + 2] === 'K' ) {
								$isCheck = TRUE;
							}
						}

						// ++x -y
						if ( $letter + 2 < 8 && $number - 1 >= 0 ) {
							if ( $position[$number - 1][$letter + 2] === 'K' ) {
								$isCheck = TRUE;
							}
						}

						// --x +y
						if ( $letter - 2 >= 0 && $number + 1 < 8 ) {
							if ( $position[$number + 1][$letter - 2] === 'K' ) {
								$isCheck = TRUE;
							}
						}

						// --x -y
						if ( $letter - 2 >= 0 && $number - 1 >= 0 ) {
							if ( $position[$number - 1][$letter - 2] === 'K' ) {
								$isCheck = TRUE;
							}
						}

						// ++y +x
						if ( $letter + 1 < 8 && $number + 2 < 8 ) {
							if ( $position[$number + 2][$letter + 1] === 'K' ) {
								$isCheck = TRUE;
							}
						}

						// ++y -x
						if ( $letter - 1 >= 0 && $number + 2 < 8 ) {
							if ( $position[$number + 2][$letter - 1] === 'K' ) {
								$isCheck = TRUE;
							}
						}

						// --y +x
						if ( $letter + 1 < 8 && $number - 2 >= 0 ) {
							if ( $position[$number - 2][$letter + 1] === 'K' ) {
								$isCheck = TRUE;
							}
						}

						// --y -x
						if ( $letter - 1 >= 0 && $number - 2 >= 0 ) {
							if ( $position[$number - 2][$letter - 1] === 'K' ) {
								$isCheck = TRUE;
							}
						}

					} else if ( $square === 'p' ) {
						// +y -x
						if ( $letter - 1 >= 0 && $number + 1 < 8 ) {
							if ( $position[$number + 1][$letter - 1] === 'K' ) {
								$isCheck = TRUE;
							}
						}

						// +y +x
						if ( $letter + 1 < 8 && $number + 1 < 8 ) {
							if ( $position[$number + 1][$letter + 1] === 'K' ) {
								$isCheck = TRUE;
							}
						}

					} else if ( $square === 'Q' ) {

						$collided = array( '+x' => FALSE, '+y' => FALSE, '-x' => FALSE, '-y' => FALSE,
							'+x+y' => FALSE, '+x-y' => FALSE, '-x+y' => FALSE, '-x-y' => FALSE );

						// straights

						// +x
						for ( $x = $number + 1; $x < 8; $x++ ) {
							//echo "19\n";
							if ( $collided['+x'] === FALSE && $position[$x][$letter] === 'k' ) {
								$isCheck = TRUE;
							} else if ( $collided['+x'] === FALSE && $position[$x][$letter] !== 0 ) {
								$collided['+x'] = TRUE;
							}
						}

						// -x
						for ( $x = $number - 1; $x >= 0; $x-- ) {
							//echo "20\n";
							if ( $collided['-x'] === FALSE && $position[$x][$letter] === 'k' ) {
								$isCheck = TRUE;
							} else if ( $collided['-x'] === FALSE && $position[$x][$letter] !== 0 ) {
								$collided['-x'] = TRUE;
							}
						}

						// +y
						for ( $x = $number + 1; $x < 8; $x++ ) {
							//echo "21\n";
							if ( $collided['+y'] === FALSE && $position[$number][$x] === 'k' ) {
								$isCheck = TRUE;
							} else if ( $collided['+y'] === FALSE && $position[$number][$x] !== 0 ) {
								$collided['+y'] = TRUE;
							}
						}

						// -y
						for ( $x = $number - 1; $x >= 0 ;$x-- ) {
							//echo "22\n";
							if ( $collided['-y'] === FALSE && $position[$number][$x] === 'k' ) {
								$isCheck = TRUE;
							} else if ( $collided['-y'] === FALSE && $position[$number][$x] !== 0 ) {
								$collided['-y'] = TRUE;
							}
						}

						// diagonals

						// +x +y
						$x = $letter + 1;
						$y = $number + 1;
						while ( $x < 8 && $y < 8 && $x >= 0 && $y >= 0 ) {
							//echo "23\n";
							if ( $collided['+x+y'] == FALSE && $position[$y][$x] === 'k' ) {
								$isCheck = TRUE;
							} else if ( $collided['+x+y'] == FALSE && $position[$y][$x] !== 0 ) {
								$collided['+x+y'] = TRUE;
							}
							$x++;
							$y++;
						}

						// +x -y
						$x = $letter + 1;
						$y = $number - 1;
						while ( $x < 8 && $y < 8 && $x >= 0 && $y >= 0 ) {
							//echo "24\n";
							if ( $collided['+x-y'] == FALSE && $position[$y][$x] === 'k' ) {
								$isCheck = TRUE;
							} else if ( $collided['+x-y'] == FALSE && $position[$y][$x] !== 0 ) {
								$collided['+x-y'] = TRUE;
							}
							$x++;
							$y--;
						}

						// -x +y
						$x = $letter - 1;
						$y = $number + 1;
						while ( $x < 8 && $y < 8 && $x >= 0 && $y >= 0 ) {
							//echo "25\n";
							if ( $collided['-x+y'] == FALSE && $position[$y][$x] === 'k' ) {
								$isCheck = TRUE;
							} else if ( $collided['-x+y'] == FALSE && $position[$y][$x] !== 0 ) {
								$collided['-x+y'] = TRUE;
							}
							$x--;
							$y++;
						}

						// -x -y
						$x = $letter - 1;
						$y = $number - 1;
						while ( $x < 8 && $y < 8 && $x >= 0 && $y >= 0 ) {
							//echo "26\n";
							if ( $collided['-x-y'] == FALSE && $position[$y][$x] === 'k' ) {
								$isCheck = TRUE;
							} else if ( $collided['-x-y'] == FALSE && $position[$y][$x] !== 0 ) {
								$collided['-x-y'] = TRUE;
							}
							$x--;
							$y--;
						}

					} else if ( $square === 'R' ) {

						$collided = array( '+x' => FALSE, '+y' => FALSE, '-x' => FALSE, '-y' => FALSE );

						for ( $x = 0; $x++; $x < 8 ) {
							// straights

							// +x
							for ( $x = $number + 1; $x < 8; $x++ ) {
								//echo "27\n";
								if ( $collided['+x'] === FALSE && $position[$x][$letter] === 'k' ) {
									$isCheck = TRUE;
								} else if ( $collided['+x'] === FALSE && $position[$x][$letter] !== 0 ) {
									$collided['+x'] = TRUE;
								}
							}

							// -x
							for ( $x = $number - 1; $x >= 0; $x-- ) {
								//echo "28\n";
								if ( $collided['-x'] === FALSE && $position[$x][$letter] === 'k' ) {
									$isCheck = TRUE;
								} else if ( $collided['-x'] === FALSE && $position[$x][$letter] !== 0 ) {
									$collided['-x'] = TRUE;
								}
							}

							// +y
							for ( $x = $number + 1; $x < 8; $x++ ) {
								//echo "29\n";
								if ( $collided['+y'] === FALSE && $position[$number][$x] === 'k' ) {
									$isCheck = TRUE;
								} else if ( $collided['+y'] === FALSE && $position[$number][$x] !== 0 ) {
									$collided['+y'] = TRUE;
								}
							}

							// -y
							for ( $x = $number - 1; $x >= 0; $x-- ) {
								//echo "30\n";
								if ( $collided['-y'] === FALSE && $position[$number][$x] === 'k' ) {
									$isCheck = TRUE;
								} else if ( $collided['-y'] === FALSE && $position[$number][$x] !== 0 ) {
									$collided['-y'] = TRUE;
								}
							}

						}

					} else if ( $square === 'B' ) {
						$collided = array( '+x+y' => FALSE, '+x-y' => FALSE, '-x+y' => FALSE, '-x-y' => FALSE );

						// diagonals

						// +x +y
						$x = $letter + 1;
						$y = $number + 1;
						while ( $x < 8 && $y < 8 && $x >= 0 && $y >= 0 ) {
							//echo "31\n";
							if ( $collided['+x+y'] == FALSE && $position[$y][$x] === 'k' ) {
								$isCheck = TRUE;
							} else if ( $collided['+x+y'] == FALSE && $position[$y][$x] !== 0 ) {
								$collided['+x+y'] = TRUE;
							}
							$x++;
							$y++;
						}

						// +x -y
						$x = $letter + 1;
						$y = $number - 1;
						while ( $x < 8 && $y < 8 && $x >= 0 && $y >= 0 ) {
							//echo "32\n";
							if ( $collided['+x-y'] == FALSE && $position[$y][$x] === 'k' ) {
								$isCheck = TRUE;
							} else if ( $collided['+x-y'] == FALSE && $position[$y][$x] !== 0 ) {
								$collided['+x-y'] = TRUE;
							}
							$x++;
							$y--;
						}

						// -x +y
						$x = $letter - 1;
						$y = $number + 1;
						while ( $x < 8 && $y < 8 && $x >= 0 && $y >= 0 ) {
							//echo "33\n";
							if ( $collided['-x+y'] == FALSE && $position[$y][$x] === 'k' ) {
								$isCheck = TRUE;
							} else if ( $collided['-x+y'] == FALSE && $position[$y][$x] !== 0 ) {
								$collided['-x+y'] = TRUE;
							}
							$x--;
							$y++;
						}

						// -x -y
						$x = $letter - 1;
						$y = $number - 1;
						while ( $x < 8 && $y < 8 && $x >= 0 && $y >= 0 ) {
							//echo "34\n";
							if ( $collided['-x-y'] == FALSE && $position[$y][$x] === 'k' ) {
								$isCheck = TRUE;
							} else if ( $collided['-x-y'] == FALSE && $position[$y][$x] !== 0 ) {
								$collided['-x-y'] = TRUE;
							}
							$x--;
							$y--;
						}

					} else if ( $square === 'N' ) {
						//L shapes

						// ++x +y
						if ( $letter + 2 < 8 && $number + 1 < 8 ) {
							if ( $position[$number + 1][$letter + 2] === 'k' ) {
								$isCheck = TRUE;
							}
						}

						// ++x -y
						if ( $letter + 2 < 8 && $number - 1 >= 0 ) {
							if ( $position[$number - 1][$letter + 2] === 'k' ) {
								$isCheck = TRUE;
							}
						}

						// --x +y
						if ( $letter - 2 >= 0 && $number + 1 < 8 ) {
							if ( $position[$number + 1][$letter - 2] === 'k' ) {
								$isCheck = TRUE;
							}
						}

						// --x -y
						if ( $letter - 2 >= 0 && $number - 1 >= 0 ) {
							if ( $position[$number - 1][$letter - 2] === 'k' ) {
								$isCheck = TRUE;
							}
						}

						// ++y +x
						if ( $letter + 1 < 8 && $number + 2 < 8 ) {
							if ( $position[$number + 2][$letter + 1] === 'k' ) {
								$isCheck = TRUE;
							}
						}

						// ++y -x
						if ( $letter - 1 >= 0 && $number + 2 < 8 ) {
							if ( $position[$number + 2][$letter - 1] === 'k' ) {
								$isCheck = TRUE;
							}
						}

						// --y +x
						if ( $letter + 1 < 8 && $number - 2 >= 0 ) {
							if ( $position[$number - 2][$letter + 1] === 'k' ) {
								$isCheck = TRUE;
							}
						}

						// --y -x
						if ( $letter - 1 >= 0 && $number - 2 >= 0 ) {
							if ( $position[$number - 2][$letter - 1] === 'k' ) {
								$isCheck = TRUE;
							}
						}

					} else if ( $square === 'P' ) {

						// -y -x
						if ( $letter - 1 >= 0 && $number - 1 >= 0 ) {
							if ( $position[$number - 1][$letter - 1] === 'k' ) {
								$isCheck = TRUE;
							}
						}

						// -y +x
						if ( $letter + 1 < 8 && $number - 1 >= 0 ) {
							if ( $position[$number - 1][$letter + 1] === 'k' ) {
								$isCheck = TRUE;
							}
						}
					}
				}
			}
		}
	}
	//echo "Just kidding!\n";
	return $isCheck;
}

function isTension ( $moveString ) {
	//detect is a major piece is under attack
		//Input: A string of moves in coordinate notation (e2e4)
	//	And if to limit results to major cpatures (i.e. not pawn captures)

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

	foreach ( $moves as $key => $move ) {

		$position = updatePosition ( $position, $move );

		$isCheck = FALSE;

		foreach ( $position as $number => $column ) {
			//echo "1\n";
			foreach ( $column as $letter => $square ) {
				//echo "2\n";
				if ( $square !== 0 ) {
					// Now to work out if the locus of each piece hits an opposite side king
					if ( $square === 'q' ) {

						$collided = array( '+x' => FALSE, '+y' => FALSE, '-x' => FALSE, '-y' => FALSE,
							'+x+y' => FALSE, '+x-y' => FALSE, '-x+y' => FALSE, '-x-y' => FALSE );

						// straights

						// +x
						for ( $x = $number + 1; $x < 8; $x++ ) {
							//echo "$x 3\n";
							if ( $collided['+x'] === FALSE && $position[$x][$letter] === 'Q' ) {
								$isCheck = TRUE;
								$collided['+x'] = TRUE;
							} else if ( $collided['+x'] === FALSE && $position[$x][$letter] !== 0 ) {
								$collided['+x'] = TRUE;
							}
						}

						// -x
						for ( $x = $number - 1; $x >= 0; $x-- ) {
							//echo "4\n";
							if ( $collided['-x'] === FALSE && $position[$x][$letter] === 'Q' ) {
								$isCheck = TRUE;
								$collided['-x'] = TRUE;
							} else if ( $collided['-x'] === FALSE && $position[$x][$letter] !== 0 ) {
								$collided['-x'] = TRUE;
							}
						}

						// +y
						for ( $x = $number + 1; $x < 8; $x++ ) {
							//echo "5\n";
							if ( $collided['+y'] === FALSE && $position[$number][$x] === 'Q' ) {
								$isCheck = TRUE;
								$collided['+y'] = TRUE;
							} else if ( $collided['+y'] === FALSE && $position[$number][$x] !== 0 ) {
								$collided['+y'] = TRUE;
							}
						}

						// -y
						for ( $x = $number - 1; $x >= 0; $x-- ) {
							//echo "6\n";
							if ( $collided['-y'] === FALSE && $position[$number][$x] === 'Q' ) {
								$isCheck = TRUE;
								$collided['-y'] = TRUE;
							} else if ( $collided['-y'] === FALSE && $position[$number][$x] !== 0 ) {
								$collided['-y'] = TRUE;
							}
						}

						// diagonals

						// +x +y
						$x = $letter + 1;
						$y = $number + 1;
						while ( $x < 8 && $y < 8 && $x >= 0 && $y >= 0 ) {
							//echo "7\n";
							if ( $collided['+x+y'] == FALSE && $position[$y][$x] === 'Q' ) {
								$isCheck = TRUE;
								$collided['+x+y'] = TRUE;
							} else if ( $collided['+x+y'] == FALSE && $position[$y][$x] !== 0 ) {
								$collided['+x+y'] = TRUE;
							}
							$x++;
							$y++;
						}

						// +x -y
						$x = $letter + 1;
						$y = $number - 1;
						while ( $x < 8 && $y < 8 && $x >= 0 && $y >= 0 ) {
							//echo "8\n";
							if ( $collided['+x-y'] == FALSE && $position[$y][$x] === 'Q' ) {
								$isCheck = TRUE;
								$collided['+x-y'] = TRUE;
							} else if ( $collided['+x-y'] == FALSE && $position[$y][$x] !== 0 ) {
								$collided['+x-y'] = TRUE;
							}
							$x++;
							$y--;
						}

						// -x +y
						$x = $letter - 1;
						$y = $number + 1;
						while ( $x < 8 && $y < 8 && $x >= 0 && $y >= 0 ) {
							//echo "9\n";
							if ( $collided['-x+y'] == FALSE && $position[$y][$x] === 'Q' ) {
								$isCheck = TRUE;
								$collided['-x+y'] = TRUE;
							} else if ( $collided['-x+y'] == FALSE && $position[$y][$x] !== 0 ) {
								$collided['-x+y'] = TRUE;
							}
							$x--;
							$y++;
						}

						// -x -y
						$x = $letter - 1;
						$y = $number - 1;
						while ( $x < 8 && $y < 8 && $x >= 0 && $y >= 0 ) {
							//echo "10\n";
							if ( $collided['-x-y'] == FALSE && $position[$y][$x] === 'Q' ) {
								$isCheck = TRUE;
								$collided['-x-y'] = TRUE;
							} else if ( $collided['-x-y'] == FALSE && $position[$y][$x] !== 0 ) {
								$collided['-x-y'] = TRUE;
							}
							$x--;
							$y--;
						}


					} else if ( $square === 'r' ) {

						$collided = array( '+x' => FALSE, '+y' => FALSE, '-x' => FALSE, '-y' => FALSE );

						for ( $x = 0; $x++; $x < 8 ) {
							// straights

							// +x
							for ( $x = $number + 1; $x < 8; $x++ ) {
								//echo "11\n";
								if ( $collided['+x'] === FALSE && $position[$x][$letter] === 'Q' ) {
									$isCheck = TRUE;
									$collided['+x'] = TRUE;
								} else if ( $collided['+x'] === FALSE && $position[$x][$letter] !== 0 ) {
									$collided['+x'] = TRUE;
								}
							}

							// -x
							for ( $x = $number - 1; $x >= 0; $x-- ) {
								//echo "12\n";
								if ( $collided['-x'] === FALSE && $position[$x][$letter] === 'Q' ) {
									$isCheck = TRUE;
									$collided['-x'] = TRUE;
								} else if ( $collided['-x'] === FALSE && $position[$x][$letter] !== 0 ) {
									$collided['-x'] = TRUE;
								}
							}

							// +y
							for ( $x = $number + 1; $x < 8; $x++ ) {
								//echo "13\n";
								if ( $collided['+y'] === FALSE && $position[$number][$x] === 'Q' ) {
									$isCheck = TRUE;
									$collided['+y'] = TRUE;
								} else if ( $collided['+y'] === FALSE && $position[$number][$x] !== 0 ) {
									$collided['+y'] = TRUE;
								}
							}

							// -y
							for ( $x = $number - 1; $x >= 0; $x-- ) {
								//echo "14\n";
								if ( $collided['-y'] === FALSE && $position[$number][$x] === 'Q' ) {
									$isCheck = TRUE;
									$collided['-y'] = TRUE;
								} else if ( $collided['-y'] === FALSE && $position[$number][$x] !== 0 ) {
									$collided['-y'] = TRUE;
								}
							}

						}

					} else if ( $square === 'b' ) {

						$collided = array( '+x+y' => FALSE, '+x-y' => FALSE, '-x+y' => FALSE, '-x-y' => FALSE );

						// diagonals

						// +x +y
						$x = $letter + 1;
						$y = $number + 1;
						while ( $x < 8 && $y < 8 && $x >= 0 && $y >= 0 ) {
							//echo "15\n";
							if ( $collided['+x+y'] == FALSE && ( $position[$y][$x] === 'Q' || $position[$y][$x] === 'R' ) ) {
								$isCheck = TRUE;
								$collided['+x+y'] = TRUE;
							} else if ( $collided['+x+y'] == FALSE && $position[$y][$x] !== 0 ) {
								$collided['+x+y'] = TRUE;
							}
							$x++;
							$y++;
						}

						// +x -y
						$x = $letter + 1;
						$y = $number - 1;
						while ( $x < 8 && $y < 8 && $x >= 0 && $y >= 0 ) {
							//echo "16\n";
							if ( $collided['+x-y'] == FALSE && ( $position[$y][$x] === 'Q' || $position[$y][$x] === 'R' ) ) {
								$isCheck = TRUE;
								$collided['+x-y'] = TRUE;
							} else if ( $collided['+x-y'] == FALSE && $position[$y][$x] !== 0 ) {
								$collided['+x-y'] = TRUE;
							}
							$x++;
							$y--;
						}

						// -x +y
						$x = $letter - 1;
						$y = $number + 1;
						while ( $x < 8 && $y < 8 && $x >= 0 && $y >= 0 ) {
							//echo "17\n";
							if ( $collided['-x+y'] == FALSE && ( $position[$y][$x] === 'Q' || $position[$y][$x] === 'R' ) ) {
								$isCheck = TRUE;
								$collided['-x+y'] = TRUE;
							} else if ( $collided['-x+y'] == FALSE && $position[$y][$x] !== 0 ) {
								$collided['-x+y'] = TRUE;
							}
							$x--;
							$y++;
						}

						// -x -y
						$x = $letter - 1;
						$y = $number - 1;
						while ( $x < 8 && $y < 8 && $x >= 0 && $y >= 0 ) {
							//echo "18\n";
							if ( $collided['-x-y'] == FALSE && ( $position[$y][$x] === 'Q' || $position[$y][$x] === 'R' ) ) {
								$isCheck = TRUE;
								$collided['-x-y'] = TRUE;
							} else if ( $collided['-x-y'] == FALSE && $position[$y][$x] !== 0 ) {
								$collided['-x-y'] = TRUE;
							}
							$x--;
							$y--;
						}

					} else if ( $square === 'n' ) {
						//L shapes

						// ++x +y
						if ( $letter + 2 < 8 && $number + 1 < 8 ) {
							if ( $position[$number + 1][$letter + 2] === 'Q' || $position[$number + 1][$letter + 2] === 'R' ) {
								$isCheck = TRUE;
							}
						}

						// ++x -y
						if ( $letter + 2 < 8 && $number - 1 >= 0 ) {
							if ( $position[$number - 1][$letter + 2] === 'Q' || $position[$number - 1][$letter + 2] === 'R' ) {
								$isCheck = TRUE;
							}
						}

						// --x +y
						if ( $letter - 2 >= 0 && $number + 1 < 8 ) {
							if ( $position[$number + 1][$letter - 2] === 'Q' || $position[$number + 1][$letter - 2] === 'R' ) {
								$isCheck = TRUE;
							}
						}

						// --x -y
						if ( $letter - 2 >= 0 && $number - 1 >= 0 ) {
							if ( $position[$number - 1][$letter - 2] === 'Q' || $position[$number - 1][$letter - 2] === 'R' ) {
								$isCheck = TRUE;
							}
						}

						// ++y +x
						if ( $letter + 1 < 8 && $number + 2 < 8 ) {
							if ( $position[$number + 2][$letter + 1] === 'Q' || $position[$number + 2][$letter + 1] === 'R' ) {
								$isCheck = TRUE;
							}
						}

						// ++y -x
						if ( $letter - 1 >= 0 && $number + 2 < 8 ) {
							if ( $position[$number + 2][$letter - 1] === 'Q' || $position[$number + 2][$letter - 1] === 'R' ) {
								$isCheck = TRUE;
							}
						}

						// --y +x
						if ( $letter + 1 < 8 && $number - 2 >= 0 ) {
							if ( $position[$number - 2][$letter + 1] === 'Q' || $position[$number - 2][$letter + 1] === 'R' ) {
								$isCheck = TRUE;
							}
						}

						// --y -x
						if ( $letter - 1 >= 0 && $number - 2 >= 0 ) {
							if ( $position[$number - 2][$letter - 1] === 'Q' || $position[$number - 2][$letter - 1] === 'R' ) {
								$isCheck = TRUE;
							}
						}

					} else if ( $square === 'p' ) {
						// +y -x
						if ( $letter - 1 >= 0 && $number + 1 < 8 ) {
							if ( $position[$number + 1][$letter - 1] === 'Q' 
								|| $position[$number + 1][$letter - 1] === 'R' 
								|| $position[$number + 1][$letter - 1] === 'N' 
								|| $position[$number + 1][$letter - 1] === 'B' ) {
								$isCheck = TRUE;
							}
						}

						// +y +x
						if ( $letter + 1 < 8 && $number + 1 < 8 ) {
							if ( $position[$number + 1][$letter + 1] === 'Q' 
								|| $position[$number + 1][$letter + 1] === 'R' 
								|| $position[$number + 1][$letter + 1] === 'N' 
								|| $position[$number + 1][$letter + 1] === 'B' ) {
								$isCheck = TRUE;
							}
						}

					} else if ( $square === 'Q' ) {

						$collided = array( '+x' => FALSE, '+y' => FALSE, '-x' => FALSE, '-y' => FALSE,
							'+x+y' => FALSE, '+x-y' => FALSE, '-x+y' => FALSE, '-x-y' => FALSE );

						// straights

						// +x
						for ( $x = $number + 1; $x < 8; $x++ ) {
							//echo "19\n";
							if ( $collided['+x'] === FALSE && $position[$x][$letter] === 'q' ) {
								$isCheck = TRUE;
								$collided['+x'] = TRUE;
							} else if ( $collided['+x'] === FALSE && $position[$x][$letter] !== 0 ) {
								$collided['+x'] = TRUE;
							}
						}

						// -x
						for ( $x = $number - 1; $x >= 0; $x-- ) {
							//echo "20\n";
							if ( $collided['-x'] === FALSE && $position[$x][$letter] === 'q' ) {
								$isCheck = TRUE;
								$collided['-x'] = TRUE;
							} else if ( $collided['-x'] === FALSE && $position[$x][$letter] !== 0 ) {
								$collided['-x'] = TRUE;
							}
						}

						// +y
						for ( $x = $number + 1; $x < 8; $x++ ) {
							//echo "21\n";
							if ( $collided['+y'] === FALSE && $position[$number][$x] === 'q' ) {
								$isCheck = TRUE;
								$collided['+y'] = TRUE;
							} else if ( $collided['+y'] === FALSE && $position[$number][$x] !== 0 ) {
								$collided['+y'] = TRUE;
							}
						}

						// -y
						for ( $x = $number - 1; $x >= 0 ;$x-- ) {
							//echo "22\n";
							if ( $collided['-y'] === FALSE && $position[$number][$x] === 'q' ) {
								$isCheck = TRUE;
								$collided['-y'] = TRUE;
							} else if ( $collided['-y'] === FALSE && $position[$number][$x] !== 0 ) {
								$collided['-y'] = TRUE;
							}
						}

						// diagonals

						// +x +y
						$x = $letter + 1;
						$y = $number + 1;
						while ( $x < 8 && $y < 8 && $x >= 0 && $y >= 0 ) {
							//echo "23\n";
							if ( $collided['+x+y'] == FALSE && $position[$y][$x] === 'q' ) {
								$isCheck = TRUE;
								$collided['+x+y'] = TRUE;
							} else if ( $collided['+x+y'] == FALSE && $position[$y][$x] !== 0 ) {
								$collided['+x+y'] = TRUE;
							}
							$x++;
							$y++;
						}

						// +x -y
						$x = $letter + 1;
						$y = $number - 1;
						while ( $x < 8 && $y < 8 && $x >= 0 && $y >= 0 ) {
							//echo "24\n";
							if ( $collided['+x-y'] == FALSE && $position[$y][$x] === 'q' ) {
								$isCheck = TRUE;
								$collided['+x-y'] = TRUE;
							} else if ( $collided['+x-y'] == FALSE && $position[$y][$x] !== 0 ) {
								$collided['+x-y'] = TRUE;
							}
							$x++;
							$y--;
						}

						// -x +y
						$x = $letter - 1;
						$y = $number + 1;
						while ( $x < 8 && $y < 8 && $x >= 0 && $y >= 0 ) {
							//echo "25\n";
							if ( $collided['-x+y'] == FALSE && $position[$y][$x] === 'q' ) {
								$isCheck = TRUE;
								$collided['-x+y'] = TRUE;
							} else if ( $collided['-x+y'] == FALSE && $position[$y][$x] !== 0 ) {
								$collided['-x+y'] = TRUE;
							}
							$x--;
							$y++;
						}

						// -x -y
						$x = $letter - 1;
						$y = $number - 1;
						while ( $x < 8 && $y < 8 && $x >= 0 && $y >= 0 ) {
							//echo "26\n";
							if ( $collided['-x-y'] == FALSE && $position[$y][$x] === 'q' ) {
								$isCheck = TRUE;
								$collided['-x-y'] = TRUE;
							} else if ( $collided['-x-y'] == FALSE && $position[$y][$x] !== 0 ) {
								$collided['-x-y'] = TRUE;
							}
							$x--;
							$y--;
						}

					} else if ( $square === 'R' ) {

						$collided = array( '+x' => FALSE, '+y' => FALSE, '-x' => FALSE, '-y' => FALSE );

						for ( $x = 0; $x++; $x < 8 ) {
							// straights

							// +x
							for ( $x = $number + 1; $x < 8; $x++ ) {
								//echo "27\n";
								if ( $collided['+x'] === FALSE && $position[$x][$letter] === 'q' ) {
									$isCheck = TRUE;
									$collided['+x'] = TRUE;
								} else if ( $collided['+x'] === FALSE && $position[$x][$letter] !== 0 ) {
									$collided['+x'] = TRUE;
								}
							}

							// -x
							for ( $x = $number - 1; $x >= 0; $x-- ) {
								//echo "28\n";
								if ( $collided['-x'] === FALSE && $position[$x][$letter] === 'q' ) {
									$isCheck = TRUE;
									$collided['-x'] = TRUE;
								} else if ( $collided['-x'] === FALSE && $position[$x][$letter] !== 0 ) {
									$collided['-x'] = TRUE;
								}
							}

							// +y
							for ( $x = $number + 1; $x < 8; $x++ ) {
								//echo "29\n";
								if ( $collided['+y'] === FALSE && $position[$number][$x] === 'q' ) {
									$isCheck = TRUE;
									$collided['+y'] = TRUE;
								} else if ( $collided['+y'] === FALSE && $position[$number][$x] !== 0 ) {
									$collided['+y'] = TRUE;
								}
							}

							// -y
							for ( $x = $number - 1; $x >= 0; $x-- ) {
								//echo "30\n";
								if ( $collided['-y'] === FALSE && $position[$number][$x] === 'q' ) {
									$isCheck = TRUE;
									$collided['-y'] = TRUE;
								} else if ( $collided['-y'] === FALSE && $position[$number][$x] !== 0 ) {
									$collided['-y'] = TRUE;
								}
							}

						}

					} else if ( $square === 'B' ) {
						$collided = array( '+x+y' => FALSE, '+x-y' => FALSE, '-x+y' => FALSE, '-x-y' => FALSE );

						// diagonals

						// +x +y
						$x = $letter + 1;
						$y = $number + 1;
						while ( $x < 8 && $y < 8 && $x >= 0 && $y >= 0 ) {
							//echo "31\n";
							if ( $collided['+x+y'] == FALSE && ( $position[$y][$x] === 'q' || $position[$y][$x] === 'r' ) ) {
								$isCheck = TRUE;
								$collided['+x+y'] = TRUE;
							} else if ( $collided['+x+y'] == FALSE && $position[$y][$x] !== 0 ) {
								$collided['+x+y'] = TRUE;
							}
							$x++;
							$y++;
						}

						// +x -y
						$x = $letter + 1;
						$y = $number - 1;
						while ( $x < 8 && $y < 8 && $x >= 0 && $y >= 0 ) {
							//echo "32\n";
							if ( $collided['+x-y'] == FALSE && ( $position[$y][$x] === 'q' || $position[$y][$x] === 'r' ) ) {
								$isCheck = TRUE;
								$collided['+x-y'] = TRUE;
							} else if ( $collided['+x-y'] == FALSE && $position[$y][$x] !== 0 ) {
								$collided['+x-y'] = TRUE;
							}
							$x++;
							$y--;
						}

						// -x +y
						$x = $letter - 1;
						$y = $number + 1;
						while ( $x < 8 && $y < 8 && $x >= 0 && $y >= 0 ) {
							//echo "33\n";
							if ( $collided['-x+y'] == FALSE && ( $position[$y][$x] === 'q' || $position[$y][$x] === 'r' ) ) {
								$isCheck = TRUE;
								$collided['-x+y'] = TRUE;
							} else if ( $collided['-x+y'] == FALSE && $position[$y][$x] !== 0 ) {
								$collided['-x+y'] = TRUE;
							}
							$x--;
							$y++;
						}

						// -x -y
						$x = $letter - 1;
						$y = $number - 1;
						while ( $x < 8 && $y < 8 && $x >= 0 && $y >= 0 ) {
							//echo "34\n";
							if ( $collided['-x-y'] == FALSE && ( $position[$y][$x] === 'q' || $position[$y][$x] === 'r' ) ) {
								$isCheck = TRUE;
								$collided['-x-y'] = TRUE;
							} else if ( $collided['-x-y'] == FALSE && $position[$y][$x] !== 0 ) {
								$collided['-x-y'] = TRUE;
							}
							$x--;
							$y--;
						}

					} else if ( $square === 'N' ) {
						//L shapes

						// ++x +y
						if ( $letter + 2 < 8 && $number + 1 < 8 ) {
							if ( $position[$number + 1][$letter + 2] === 'q' || $position[$number + 1][$letter + 2] === 'r' ) {
								$isCheck = TRUE;
							}
						}

						// ++x -y
						if ( $letter + 2 < 8 && $number - 1 >= 0 ) {
							if ( $position[$number - 1][$letter + 2] === 'q' || $position[$number - 1][$letter + 2] === 'r' ) {
								$isCheck = TRUE;
							}
						}

						// --x +y
						if ( $letter - 2 >= 0 && $number + 1 < 8 ) {
							if ( $position[$number + 1][$letter - 2] === 'q' || $position[$number + 1][$letter - 2] === 'r' ) {
								$isCheck = TRUE;
							}
						}

						// --x -y
						if ( $letter - 2 >= 0 && $number - 1 >= 0 ) {
							if ( $position[$number - 1][$letter - 2] === 'q' || $position[$number - 1][$letter - 2] === 'r' ) {
								$isCheck = TRUE;
							}
						}

						// ++y +x
						if ( $letter + 1 < 8 && $number + 2 < 8 ) {
							if ( $position[$number + 2][$letter + 1] === 'q' || $position[$number + 2][$letter + 1] === 'r' ) {
								$isCheck = TRUE;
							}
						}

						// ++y -x
						if ( $letter - 1 >= 0 && $number + 2 < 8 ) {
							if ( $position[$number + 2][$letter - 1] === 'q' || $position[$number + 2][$letter - 1] === 'r' ) {
								$isCheck = TRUE;
							}
						}

						// --y +x
						if ( $letter + 1 < 8 && $number - 2 >= 0 ) {
							if ( $position[$number - 2][$letter + 1] === 'q' || $position[$number - 2][$letter + 1] === 'r' ) {
								$isCheck = TRUE;
							}
						}

						// --y -x
						if ( $letter - 1 >= 0 && $number - 2 >= 0 ) {
							if ( $position[$number - 2][$letter - 1] === 'q' || $position[$number - 2][$letter - 1] === 'r' ) {
								$isCheck = TRUE;
							}
						}

					} else if ( $square === 'P' ) {

						// -y -x
						if ( $letter - 1 >= 0 && $number - 1 >= 0 ) {
							if ( $position[$number - 1][$letter - 1] === 'q' 
								|| $position[$number - 1][$letter - 1] === 'r' 
								|| $position[$number - 1][$letter - 1] === 'n' 
								|| $position[$number - 1][$letter - 1] === 'b' ) {
								$isCheck = TRUE;
							}
						}

						// -y +x
						if ( $letter + 1 < 8 && $number - 1 >= 0 ) {
							if ( $position[$number - 1][$letter + 1] === 'q' 
								|| $position[$number - 1][$letter + 1] === 'r' 
								|| $position[$number - 1][$letter + 1] === 'n' 
								|| $position[$number - 1][$letter + 1] === 'b' ) {
								$isCheck = TRUE;
							}
						}
					}
				}
			}
		}
	}

	return $isCheck;
}

function updatePosition ( $position, $move ) {
	//Take a position and a move and return the new board position

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

	return $position;
}