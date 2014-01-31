<?php

//-----thresholds
$BALANCED = 200; // +-centipawns for a position to be considered even
$UNBALANCED = 400; // +-centipawns for a position to be considered greatly advantageous


//-----engine settings
$STOCKFISH_PATH = "/Users/clarkey/Documents/workspace/lichess/problem-creator/resources/stockfish-dd-mac/Mac/stockfish-dd-32"; //location of stockfish engine
$FIRST_PASS_TIME = 2000; //milliseconds to think for each position.
$SECOND_PASS_TIME = 1000;
$ALT_THRESHOLD = 50; //Amount of centipawns for an alternative line to be valid.
$FORCED_INCLUSION = 700; //If an alt capture line has an advantage over 8 pawns, it's included

//-----Problem Settings
$MAX_CAPTURE_LINES = 5; // maximum amonut of lines that can be used in capture lines
$MAJOR_MOVE_THRESHOLD = 6;
$MINOR_MOVE_THRESHOLD = 2;

$MAX_MATE_LINES = 10;