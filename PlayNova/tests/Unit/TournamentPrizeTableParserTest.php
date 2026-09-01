<?php

namespace Tests\Unit;

use App\Services\TournamentPrizeTableParser;
use PHPUnit\Framework\TestCase;

class TournamentPrizeTableParserTest extends TestCase
{
    protected TournamentPrizeTableParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new TournamentPrizeTableParser();
    }

    public function test_parses_team_totals_from_description(): void
    {
        $table = $this->parser->parse("تیم اول در مجموع 100 تومن\nتیم دوم: 50");

        $this->assertSame(100.0, $table[1]);
        $this->assertSame(50.0, $table[2]);
    }

    public function test_parses_player_ranks_and_thousand_multiplier(): void
    {
        $table = $this->parser->parse("نفر اول: ۱۰۰ هزار\nنفر دوم 50 هزار");

        $this->assertSame(100000.0, $table[1]);
        $this->assertSame(50000.0, $table[2]);
    }

    public function test_splits_team_prize_equally_between_teammates(): void
    {
        $this->assertSame([50.0, 50.0], $this->parser->splitAmongPlayers(100, 2));
        $this->assertSame([25.0, 25.0, 25.0, 25.0], $this->parser->splitAmongPlayers(100, 4));
        $this->assertSame([33.0, 33.0, 34.0], $this->parser->splitAmongPlayers(100, 3));
    }
}
