problem-creator
===============

create chess problems for lichess

### problem-creator Command Line Interface:
`user$ php problem-creator.php [ number of games per batch = 1, [number of batches = 1] ]`

### CLI Output:
```
Batch 1 of 10
Parent -> Child | Mate In 
==========================
  d3g6 ->  a8a1 |  +0 
  d3g6 ->  d4a1 |  +0 
Array
(
    [tags] => Array
        (
            [0] => forced mate
        )

    [color] => black
    [position] => e2e4 c7c5 g1f3 e7e6 b1c3 b8c6 b2b3 f8e7 c1b2 e7f6 f1b5 g8e7 d1e2 a7a6 b5d3 e7g6 e1c1 d7d6 g2g3 e8g8 h2h4 c6b4 h4h5 g6e5 f3e5 f6e5 f2f4 e5c3 b2c3 b4a2 c1b2 a2c3 d2c3 b7b5 c3c4 c8b7 h5h6 g7g6 h1f1 d8a5 d1a1 a5b6 f4f5 b5b4 g3g4 a6a5 e4e5 a5a4 e5d6 b6d6 f5g6 d6d4 b2b1 h7g6 e2f2 a4b3 a1a8 b3c2 f2c2 f8a8 d3g6 
    [solution] => Array
        (
            [a8a1] => end
            [d4a1] => end
        )

    [id] => f2fwl3en
)
kthxbye
[{"tags":["material advantage"],"color":"black","position":"e2e4 c7c5 g1f3 e7e6 b1c3 b8c6 b2b3 f8e7 c1b2 e7f6 f1b5 g8e7 d1e2 a7a6 b5d3 e7g6 e1c1 d7d6 g2g3 e8g8 h2h4 c6b4 h4h5 g6e5 f3e5 f6e5 f2f4 e5c3 b2c3 b4a2 c1b2 a2c3 d2c3 b7b5 c3c4 c8b7 h5h6 g7g6 h1f1 d8a5 d1a1 a5b6 f4f5 b5b4 g3g4 a6a5 e4e5 a5a4 e5d6 b6d6 f5g6 d6d4 b2b1 h7g6 e2f2 a4b3 a1a8 b3c2 f2c2 ","solution":{"f8a8":{"h6h7":"end"}},"id":"f2fwl3en"},{"tags":["forced mate"],"color":"black","position":"e2e4 c7c5 g1f3 e7e6 b1c3 b8c6 b2b3 f8e7 c1b2 e7f6 f1b5 g8e7 d1e2 a7a6 b5d3 e7g6 e1c1 d7d6 g2g3 e8g8 h2h4 c6b4 h4h5 g6e5 f3e5 f6e5 f2f4 e5c3 b2c3 b4a2 c1b2 a2c3 d2c3 b7b5 c3c4 c8b7 h5h6 g7g6 h1f1 d8a5 d1a1 a5b6 f4f5 b5b4 g3g4 a6a5 e4e5 a5a4 e5d6 b6d6 f5g6 d6d4 b2b1 h7g6 e2f2 a4b3 a1a8 b3c2 f2c2 f8a8 d3g6 ","solution":{"a8a1":"end","d4a1":"end"},"id":"f2fwl3en"}]
```
* `Batch 1 of 10` - Progress through batch set
* `Parent -> Child` - The parent move that the current child node links to
* `Mate In` or `Plies Left` - A countdown to when the line will complete or expire
* Array representation of the problem
* `kthnxbye` - The POST to the API was successful
* JSON string of what was posted

### JSON Output:
```javascript
[
    {
        "tags":["material advantage"], // Tags related to the problem
        "color":"black", // Which side is solving the problem
        "position":"e2e4 c7c6 d2d4 d7d5 e4d5 c6d5 b1c3 g8f6 c1g5 c8g4 f2f3 g4f5 f1d3 f5d3 d1d3 e7e6 e1c1 b8c6 d1e1 f8e7 g5f6 e7f6 c3d5 ", // The starting position for the problem
        "solution":{
            "d8d5":{ // The first move that the problem solver (player) has to play
                "c1b1":"end" // The computer response followed by either more moves or "end" indicating that the problem is complete
            }
        },
        "id":"1ic77ndw" //The game ID that the problem came from
    },
    {
        // ... More problems
    }
]
```

### config.php and keys.php setup
config.php
```php
<?php

//-----thresholds
$BALANCED = 150; // +-centipawns for a position to be considered even
$UNBALANCED = 350; // +-centipawns for a position to be considered greatly advantageous


//-----engine settings
$STOCKFISH_PATH = "/path/to/stockfish-4-or-higher"; // location of stockfish engine
$FIRST_PASS_TIME = 6000; // milliseconds to gather candidate moves
$SECOND_PASS_TIME = 2000; // milliseconds to consider each candidate move
$ALT_THRESHOLD = 0.1; // Percentage of top eval for a move to be allowed

//-----Problem Settings
$MAX_CAPTURE_LINES = 4; // maximum amonut of lines that can be used in capture lines
$MAJOR_MOVE_THRESHOLD = 6; // Amount of plys for a capture or promotion to take place
$MINOR_MOVE_THRESHOLD = 2; // Amount of plys after the initial capture for more captures to take place

$MAX_MATE_LINES = 10; // Maximum amount of ways to checkmate from each node in the mating tree
```
* It is important to change the path to Stockfish to your own executable version.
* Time settings can be adjusted as per the performance of your computer. Larger times means higher quality puzzles

resources/keys.php
```php
<?php

$LICHESS_API_TOKEN = 'private_key_to_lichess_API';
```
* Contact Thibault Duplessis if you wish to create puzzles for lichess. The key is necessary to successfully submit puzzles to lichess.

### Final Notes
The code is currently not compatible with Windows operating systems due to difficulty connecting to Stockfish. You can try to fix this in [functions/global.php](https://github.com/clarkerubber/problem-creator/blob/master/functions/global.php#L86-L119).
