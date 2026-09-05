<?php

namespace Tests\Unit;

use App\Services\TournamentResultVisionService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class TournamentResultVisionParseTest extends TestCase
{
    private function invoke(string $method, mixed ...$args): mixed
    {
        $service = (new \ReflectionClass(TournamentResultVisionService::class))->newInstanceWithoutConstructor();
        $reflection = new ReflectionMethod(TournamentResultVisionService::class, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke($service, ...$args);
    }

    public function test_parses_json_with_kills_and_team_labels(): void
    {
        $raw = <<<'JSON'
Here is the scoreboard:
[
  {"rank":1,"team_label":"TEAM11","player_names":["Alpha","Beta"],"kills":[6,2]},
  {"rank":2,"team_number":18,"player_names":["Solo"],"kill":4}
]
JSON;

        $teams = $this->invoke('parseTeamsJson', $raw);

        $this->assertCount(2, $teams);
        $this->assertSame(1, $teams[0]['rank']);
        $this->assertSame(11, $teams[0]['team_number']);
        $this->assertSame([6, 2], $teams[0]['kills']);
        $this->assertSame(2, $teams[1]['rank']);
        $this->assertSame(18, $teams[1]['team_number']);
        $this->assertSame([4], $teams[1]['kills']);
    }

    public function test_parses_chatgpt_markdown_fence(): void
    {
        $raw = <<<'TXT'
Sure, here is the result:

```json
[
  {"rank":1,"team_label":"TEAM3","player_names":["Ali","Reza"],"kills":[8,1]},
  {"rank":2,"team_number":7,"player_names":["Sara"],"kills":[2]}
]
```
TXT;

        $teams = $this->invoke('parseTeamsJson', $raw);

        $this->assertCount(2, $teams);
        $this->assertSame(1, $teams[0]['rank']);
        $this->assertSame(3, $teams[0]['team_number']);
        $this->assertSame([8, 1], $teams[0]['kills']);
    }

    public function test_build_coverage_reports_missing_prize_ranks(): void
    {
        $teams = [
            [
                'rank' => 1,
                'team_number' => 1,
                'team_label' => 'TEAM1',
                'player_names' => ['A'],
                'uids' => [null],
                'kills' => [5],
            ],
        ];

        $prizeTable = [1 => 500000.0, 2 => 300000.0, 3 => 100000.0];
        $matchResult = [
            'matched' => [['kills' => 5]],
            'unmatched' => [],
        ];

        $coverage = $this->invoke('buildCoverage', $teams, $prizeTable, $matchResult);

        $this->assertFalse($coverage['is_complete']);
        $this->assertSame([2, 3], $coverage['missing_ranks']);
        $this->assertSame(3, $coverage['expected_last_rank']);
        $this->assertSame(1, $coverage['players_with_kills']);
    }
}
