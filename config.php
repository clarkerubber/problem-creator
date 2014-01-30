<?php

//-----thresholds
$BALANCED = 200; // +-centipawns for a position to be considered even
$UNBALANCED = 400; // +-centipawns for a position to be considered greatly advantageous


//-----engine settings
$STOCKFISH_PATH = "/Users/clarkey/Documents/workspace/lichess/problem-creator/resources/stockfish-3-mac/Mac/stockfish-3-32"; //location of stockfish engine
$FIRST_PASS_TIME = 10000; //milliseconds to think for each position.
$SECOND_PASS_TIME = 5000;
$ALT_THRESHOLD = 20; //Amount of centipawns for an alternative line to be valid.
$FORCED_INCLUSION = 800; //If an alt capture line has an advantage over 8 pawns, it's included