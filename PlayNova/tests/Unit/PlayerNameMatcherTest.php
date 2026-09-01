<?php

namespace Tests\Unit;

use App\Services\PlayerNameMatcher;
use PHPUnit\Framework\TestCase;

class PlayerNameMatcherTest extends TestCase
{
    public function test_matches_in_game_name_against_cod_id(): void
    {
        $used = [];
        $participants = [[
            'user_id' => 10,
            'username' => 'site_user_123',
            'cod_id' => '𝓟𝓵𝓪𝔂𝓮𝓻',
            'seat_number' => 1,
        ]];

        $match = PlayerNameMatcher::findBestMatch('Player', null, $participants, $used);

        $this->assertNotNull($match);
        $this->assertSame(10, $match['user_id']);
        $this->assertSame('cod_id_name', $match['match_method']);
    }

    public function test_matches_numeric_uid_against_cod_id(): void
    {
        $used = [];
        $participants = [[
            'user_id' => 11,
            'username' => 'different_username',
            'cod_id' => '7012345678',
            'seat_number' => 2,
        ]];

        $match = PlayerNameMatcher::findBestMatch(null, '7012345678', $participants, $used);

        $this->assertNotNull($match);
        $this->assertSame(11, $match['user_id']);
        $this->assertSame('cod_id_uid', $match['match_method']);
    }

    public function test_does_not_match_site_username_by_default(): void
    {
        $used = [];
        $participants = [[
            'user_id' => 12,
            'username' => 'Alpha',
            'cod_id' => 'cod_alpha_99',
            'seat_number' => 3,
        ]];

        $match = PlayerNameMatcher::findBestMatch('Alpha', null, $participants, $used);

        $this->assertNull($match);
    }

    public function test_can_match_username_when_explicitly_allowed(): void
    {
        $used = [];
        $participants = [[
            'user_id' => 12,
            'username' => 'Alpha',
            'cod_id' => null,
            'seat_number' => 3,
        ]];

        $match = PlayerNameMatcher::findBestMatch('Alpha', null, $participants, $used, 0.72, true);

        $this->assertNotNull($match);
        $this->assertSame(12, $match['user_id']);
    }

    public function test_does_not_reuse_same_participant_twice(): void
    {
        $used = [];
        $participants = [[
            'user_id' => 10,
            'username' => 'Alpha',
            'cod_id' => 'Alpha',
            'seat_number' => 1,
        ]];

        $first = PlayerNameMatcher::findBestMatch('Alpha', null, $participants, $used);
        $second = PlayerNameMatcher::findBestMatch('Alpha', null, $participants, $used);

        $this->assertNotNull($first);
        $this->assertNull($second);
    }
}
