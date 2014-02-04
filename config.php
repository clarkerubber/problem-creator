<?php

//-----thresholds
$BALANCED = 150; // +-centipawns for a position to be considered even
$UNBALANCED = 350; // +-centipawns for a position to be considered greatly advantageous


//-----engine settings
$STOCKFISH_PATH = "/Users/clarkey/Documents/workspace/lichess/problem-creator/resources/stockfish-dd-mac/Mac/stockfish-dd-32"; // location of stockfish engine
$FIRST_PASS_TIME = 6000; // milliseconds to gather candidate moves
$SECOND_PASS_TIME = 2000; // milliseconds to consider each candidate move
$ALT_THRESHOLD = 0.05; // Percentage of top eval for a move to be allowed
$RETRY_THRESHOLD = 0.1;

//-----Problem Settings
$MAX_CAPTURE_LINES = 5; // maximum amonut of lines that can be used in capture lines
$MAJOR_MOVE_THRESHOLD = 4; // Amount of plys for a capture or promotion to take place

$MAX_MATE_LINES = 50; // Maximum amount of ways to checkmate