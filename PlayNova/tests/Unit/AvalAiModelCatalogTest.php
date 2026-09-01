<?php

namespace Tests\Unit;

use App\Services\AvalAiModelCatalog;
use PHPUnit\Framework\TestCase;

class AvalAiModelCatalogTest extends TestCase
{
    public function test_categorizes_known_models_into_tiers(): void
    {
        $categories = AvalAiModelCatalog::categorize([
            'gpt-4o-mini',
            'gpt-4o',
            'gpt-5.5',
            'unknown-custom-model',
        ]);

        $byId = collect($categories)->keyBy('id');

        $this->assertTrue($byId->has(AvalAiModelCatalog::TIER_ECONOMY));
        $this->assertTrue($byId->has(AvalAiModelCatalog::TIER_BALANCED));
        $this->assertTrue($byId->has(AvalAiModelCatalog::TIER_PREMIUM));

        $economyIds = collect($byId[AvalAiModelCatalog::TIER_ECONOMY]['models'])->pluck('id')->all();
        $this->assertContains('gpt-4o-mini', $economyIds);
    }

    public function test_infers_mini_models_as_economy(): void
    {
        $this->assertSame(
            AvalAiModelCatalog::TIER_ECONOMY,
            AvalAiModelCatalog::inferTier('some-vendor-mini-v2'),
        );
    }
}
