<?php

//-----thresholds
$BALANCED = 150; // +-centipawns for a position to be considered even
$UNBALANCED = 350; // +-centipawns for a position to be considered greatly advantageous


//-----engine settings
$STOCKFISH_PATH = "/Users/clarkey/Documents/workspace/lichess/problem-creator/resources/stockfish-dd-mac/Mac/stockfish-dd-32"; // location of stockfish engine
$FIRST_PASS_TIME = 10000; // milliseconds to gather candidate moves
$SECOND_PASS_TIME = 2000; // milliseconds to consider each candidate move
$ALT_THRESHOLD = 40; // Amount of centipawns for an alternative line to be valid.
$FORCED_INCLUSION = 700; // If an alt capture line has an advantage over 8 pawns, it's included

//-----Problem Settings
$MAX_CAPTURE_LINES = 4; // maximum amonut of lines that can be used in capture lines
$MAJOR_MOVE_THRESHOLD = 6; // Amount of time for a capture or promotion to take place
$MINOR_MOVE_THRESHOLD = 2; // Amount of plys after the initial capture for more captures to take place

$MAX_MATE_LINES = 10; // Maximum amount of ways to checkmate