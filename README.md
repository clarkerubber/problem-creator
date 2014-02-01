problem-creator
===============

create chess problems for lichess

### problem-creator Command Line Interface:
`user$ problem-creator.php [number of games to request = 1]`

Note: The amount of games requested is not the amount of problems returned

### Output JSON:
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
