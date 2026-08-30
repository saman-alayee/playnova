<?php

namespace Tests\Unit;

use App\Services\PlayerNameMatcher;
use PHPUnit\Framework\TestCase;

class PlayerNameMatcherTest extends TestCase
{
    public function test_matches_fancy_unicode_names_after_normalization(): void
    {
        $used = [];
        $participants = [[
            'user_id' => 10,
            'username' => '𝓟𝓵𝓪𝔂𝓮𝓻',
            'cod_id' => null,
            'seat_number' => 1,
        ]];

        $match = PlayerNameMatcher::findBestMatch('Player', null, $participants, $used);

        $this->assertNotNull($match);
        $this->assertSame(10, $match['user_id']);
    }

    public function test_does_not_reuse_same_participant_twice(): void
    {
        $used = [];
        $participants = [[
            'user_id' => 10,
            'username' => 'Alpha',
            'cod_id' => null,
            'seat_number' => 1,
        ]];

        $first = PlayerNameMatcher::findBestMatch('Alpha', null, $participants, $used);
        $second = PlayerNameMatcher::findBestMatch('Alpha', null, $participants, $used);

        $this->assertNotNull($first);
        $this->assertNull($second);
    }
}
