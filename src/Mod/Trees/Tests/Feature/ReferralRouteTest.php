<?php

declare(strict_types=1);

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

describe('Referral Route', function () {
    it('stores referral in session for valid provider', function () {
        $response = $this->get('/ref/anthropic');

        // Should redirect (302)
        $response->assertStatus(302);
        $response->assertSessionHas('agent_referral');

        $referral = $response->getSession()->get('agent_referral');
        expect($referral['provider'])->toBe('anthropic');
        expect($referral['model'])->toBeNull();
    });

    it('stores referral with model in session', function () {
        $response = $this->get('/ref/anthropic/claude-opus');

        $response->assertStatus(302);
        $response->assertSessionHas('agent_referral');

        $referral = $response->getSession()->get('agent_referral');
        expect($referral['provider'])->toBe('anthropic');
        expect($referral['model'])->toBe('claude-opus');
    });

    it('sets referral cookie', function () {
        $response = $this->get('/ref/openai/gpt-4');

        $response->assertCookie('agent_referral');
    });

    it('redirects with ref=agent parameter', function () {
        $response = $this->get('/ref/google');

        $response->assertStatus(302);
        // Check the redirect URL contains ref=agent
        expect($response->headers->get('Location'))->toContain('ref=agent');
    });

    it('rejects invalid provider', function () {
        $response = $this->get('/ref/invalid-provider');

        // Should redirect without storing referral
        $response->assertStatus(302);
        $response->assertSessionMissing('agent_referral');
    });

    it('accepts all valid providers', function () {
        $validProviders = ['anthropic', 'openai', 'google', 'meta', 'mistral', 'local', 'unknown'];

        foreach ($validProviders as $provider) {
            $response = $this->get("/ref/{$provider}");

            $response->assertStatus(302);
            $referral = $response->getSession()->get('agent_referral');
            expect($referral['provider'])->toBe($provider);
        }
    });

    it('stores referral timestamp', function () {
        $response = $this->get('/ref/anthropic');

        $referral = $response->getSession()->get('agent_referral');
        expect($referral['referred_at'])->not->toBeNull();
    });

    it('stores hashed client IP in referral for privacy', function () {
        $response = $this->get('/ref/anthropic');

        $referral = $response->getSession()->get('agent_referral');
        expect($referral['ip_hash'])->not->toBeNull();
        // Verify it's a hash (64 chars for SHA-256)
        expect(strlen($referral['ip_hash']))->toBe(64);
    });
});

describe('ReferralController static methods', function () {
    it('retrieves referral from session', function () {
        // Set session data and make a request through the referral route
        $response = $this->get('/ref/anthropic/claude-opus');

        // Session should have the referral
        $response->assertSessionHas('agent_referral');
        $referral = session()->get('agent_referral');

        expect($referral)->not->toBeNull();
        expect($referral['provider'])->toBe('anthropic');
        expect($referral['model'])->toBe('claude-opus');
    });

    it('clears referral from session', function () {
        // Set referral via route
        $this->get('/ref/anthropic');

        // Session should have the referral initially
        expect(session()->get('agent_referral'))->not->toBeNull();

        // Clear it manually (simulating what the controller does)
        session()->forget('agent_referral');

        expect(session()->get('agent_referral'))->toBeNull();
    });
});
