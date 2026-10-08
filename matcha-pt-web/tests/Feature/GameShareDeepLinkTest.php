<?php

namespace Tests\Feature;

use App\Models\SessionModel;
use Tests\TestCase;

class GameShareDeepLinkTest extends TestCase
{
    /**
     * Bug 1 [LNK-001/003/017]: Test AssetLinks JSON endpoint is available and valid JSON.
     */
    public function test_well_known_assetlinks_returns_valid_json(): void
    {
        $response = $this->get('/.well-known/assetlinks.json');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/json');
        $response->assertSee('com.example.matcha_app');
    }

    /**
     * Bug 1 [LNK-001/003/017]: Test Apple App Site Association endpoint is available.
     */
    public function test_well_known_apple_app_site_association_returns_valid_json(): void
    {
        $response = $this->get('/.well-known/apple-app-site-association');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/json');
        $response->assertSee('/games/share/*');
    }

    /**
     * Bug 2 [LNK-005]: Test invalid or expired share token renders friendly fallback view instead of raw 404 error.
     */
    public function test_invalid_share_token_renders_friendly_share_invalid_view(): void
    {
        $response = $this->get('/games/share/non-existent-random-token-999');

        $response->assertStatus(404);
        $response->assertViewIs('games.share_invalid');
        $response->assertSee('Sesi Tidak Ditemukan');
        $response->assertSee('Sesi tidak ditemukan atau tautan sudah kedaluwarsa');
        $response->assertSee(route('games.index'));
    }
}
