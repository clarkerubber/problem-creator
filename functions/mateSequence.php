<?php

function findMateLine ( $moveUci, $ply ) {
	$movesUci = explode( ' ', $movesUci );
	$startMoveString = '';
	for ( $x = 0; $x <= $ply + 1; $x++ ){
		$startMoveString .= $movesUci[$x].' ';
	}

	//now parse this string to stockfish!
	//echo "\n".$startMoveString."\n";
	//$uciOutput = getLines( $startMoveString );
	$solutionMap = buildMateTree( $startMoveString );
}

function buildMateLine ( $moveString ) {
	
}