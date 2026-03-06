<?php

declare(strict_types=1);

use Core\Agentic\Services\AgentDetection;
use Core\Agentic\Support\AgentIdentity;
use Illuminate\Http\Request;

describe('AgentDetection Service', function () {
    beforeEach(function () {
        $this->service = new AgentDetection;
    });

    describe('provider detection from User-Agent', function () {
        it('detects Anthropic from claude-code User-Agent', function () {
            $request = Request::create('/', 'GET', [], [], [], [
                'HTTP_USER_AGENT' => 'claude-code/1.0',
            ]);

            $identity = $this->service->identify($request);

            expect($identity->isAgent())->toBeTrue();
            expect($identity->provider)->toBe('anthropic');
        });

        it('detects Anthropic from anthropic-api User-Agent', function () {
            $request = Request::create('/', 'GET', [], [], [], [
                'HTTP_USER_AGENT' => 'anthropic-api/2.0',
            ]);

            $identity = $this->service->identify($request);

            expect($identity->isAgent())->toBeTrue();
            expect($identity->provider)->toBe('anthropic');
        });

        it('detects OpenAI from ChatGPT User-Agent', function () {
            $request = Request::create('/', 'GET', [], [], [], [
                'HTTP_USER_AGENT' => 'ChatGPT/1.0',
            ]);

            $identity = $this->service->identify($request);

            expect($identity->isAgent())->toBeTrue();
            expect($identity->provider)->toBe('openai');
        });

        it('detects OpenAI from OpenAI User-Agent', function () {
            $request = Request::create('/', 'GET', [], [], [], [
                'HTTP_USER_AGENT' => 'OpenAI-API/1.0',
            ]);

            $identity = $this->service->identify($request);

            expect($identity->isAgent())->toBeTrue();
            expect($identity->provider)->toBe('openai');
        });

        it('detects Google from Gemini User-Agent', function () {
            $request = Request::create('/', 'GET', [], [], [], [
                'HTTP_USER_AGENT' => 'Gemini/1.5-pro',
            ]);

            $identity = $this->service->identify($request);

            expect($identity->isAgent())->toBeTrue();
            expect($identity->provider)->toBe('google');
        });

        it('detects Google from Google-AI User-Agent', function () {
            $request = Request::create('/', 'GET', [], [], [], [
                'HTTP_USER_AGENT' => 'Google-AI/1.0',
            ]);

            $identity = $this->service->identify($request);

            expect($identity->isAgent())->toBeTrue();
            expect($identity->provider)->toBe('google');
        });

        it('detects Meta from LLaMA User-Agent', function () {
            $request = Request::create('/', 'GET', [], [], [], [
                'HTTP_USER_AGENT' => 'LLaMA-3/1.0',
            ]);

            $identity = $this->service->identify($request);

            expect($identity->isAgent())->toBeTrue();
            expect($identity->provider)->toBe('meta');
        });

        it('detects Mistral from Mistral User-Agent', function () {
            $request = Request::create('/', 'GET', [], [], [], [
                'HTTP_USER_AGENT' => 'Mistral-Large/1.0',
            ]);

            $identity = $this->service->identify($request);

            expect($identity->isAgent())->toBeTrue();
            expect($identity->provider)->toBe('mistral');
        });
    });

    describe('model extraction', function () {
        it('extracts claude-opus model from User-Agent', function () {
            $request = Request::create('/', 'GET', [], [], [], [
                'HTTP_USER_AGENT' => 'claude-code claude-opus/1.0',
            ]);

            $identity = $this->service->identify($request);

            expect($identity->provider)->toBe('anthropic');
            expect($identity->model)->toBe('claude-opus');
        });

        it('extracts claude-sonnet model from User-Agent', function () {
            $request = Request::create('/', 'GET', [], [], [], [
                'HTTP_USER_AGENT' => 'anthropic-api claude-sonnet/1.0',
            ]);

            $identity = $this->service->identify($request);

            expect($identity->provider)->toBe('anthropic');
            expect($identity->model)->toBe('claude-sonnet');
        });

        it('extracts gpt-4 model from User-Agent', function () {
            $request = Request::create('/', 'GET', [], [], [], [
                'HTTP_USER_AGENT' => 'GPT-4/1.0',
            ]);

            $identity = $this->service->identify($request);

            expect($identity->provider)->toBe('openai');
            expect($identity->model)->toBe('gpt-4');
        });
    });

    describe('MCP token detection', function () {
        it('identifies agent from X-MCP-Token header', function () {
            $request = Request::create('/', 'GET', [], [], [], [
                'HTTP_X_MCP_TOKEN' => 'anthropic:claude-opus:secret123',
            ]);

            $identity = $this->service->identify($request);

            expect($identity->isAgent())->toBeTrue();
            expect($identity->provider)->toBe('anthropic');
            expect($identity->model)->toBe('claude-opus');
            expect($identity->confidence)->toBe('high');
        });

        it('handles opaque MCP tokens as unknown agent', function () {
            $request = Request::create('/', 'GET', [], [], [], [
                'HTTP_X_MCP_TOKEN' => 'some-opaque-token',
            ]);

            $identity = $this->service->identify($request);

            expect($identity->isAgent())->toBeTrue();
            expect($identity->provider)->toBe('unknown');
        });
    });

    describe('browser detection', function () {
        it('identifies Chrome browser as not an agent', function () {
            $request = Request::create('/', 'GET', [], [], [], [
                'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            ]);

            $identity = $this->service->identify($request);

            expect($identity->isAgent())->toBeFalse();
        });

        it('identifies Firefox browser as not an agent', function () {
            $request = Request::create('/', 'GET', [], [], [], [
                'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:121.0) Gecko/20100101 Firefox/121.0',
            ]);

            $identity = $this->service->identify($request);

            expect($identity->isAgent())->toBeFalse();
        });

        it('identifies Safari browser as not an agent', function () {
            $request = Request::create('/', 'GET', [], [], [], [
                'HTTP_USER_AGENT' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15',
            ]);

            $identity = $this->service->identify($request);

            expect($identity->isAgent())->toBeFalse();
        });
    });

    describe('non-agent bot detection', function () {
        it('identifies Googlebot as not an agent', function () {
            $request = Request::create('/', 'GET', [], [], [], [
                'HTTP_USER_AGENT' => 'Googlebot/2.1 (+http://www.google.com/bot.html)',
            ]);

            $identity = $this->service->identify($request);

            expect($identity->isAgent())->toBeFalse();
        });

        it('identifies curl as not an agent', function () {
            $request = Request::create('/', 'GET', [], [], [], [
                'HTTP_USER_AGENT' => 'curl/7.68.0',
            ]);

            $identity = $this->service->identify($request);

            expect($identity->isAgent())->toBeFalse();
        });

        it('identifies Postman as not an agent', function () {
            $request = Request::create('/', 'GET', [], [], [], [
                'HTTP_USER_AGENT' => 'Postman/10.0',
            ]);

            $identity = $this->service->identify($request);

            expect($identity->isAgent())->toBeFalse();
        });
    });

    describe('unknown agent detection', function () {
        it('identifies programmatic access without browser indicators as unknown agent', function () {
            $request = Request::create('/', 'GET', [], [], [], [
                'HTTP_USER_AGENT' => 'custom-ai-agent/1.0',
            ]);

            $identity = $this->service->identify($request);

            expect($identity->isAgent())->toBeTrue();
            expect($identity->provider)->toBe('unknown');
        });
    });
});

describe('AgentIdentity Value Object', function () {
    it('creates not an agent identity', function () {
        $identity = AgentIdentity::notAnAgent();

        expect($identity->isAgent())->toBeFalse();
        expect($identity->isNotAgent())->toBeTrue();
        expect($identity->provider)->toBe('not_agent');
    });

    it('creates unknown agent identity', function () {
        $identity = AgentIdentity::unknownAgent();

        expect($identity->isAgent())->toBeTrue();
        expect($identity->isUnknown())->toBeTrue();
        expect($identity->provider)->toBe('unknown');
    });

    it('generates correct referral path', function () {
        $identity = new AgentIdentity('anthropic', 'claude-opus', 'high');

        expect($identity->getReferralPath())->toBe('/ref/anthropic/claude-opus');
    });

    it('generates referral path without model', function () {
        $identity = new AgentIdentity('anthropic', null, 'high');

        expect($identity->getReferralPath())->toBe('/ref/anthropic');
    });

    it('provides correct display names', function () {
        $anthropic = new AgentIdentity('anthropic', 'claude-opus', 'high');
        expect($anthropic->getProviderDisplayName())->toBe('Anthropic');
        expect($anthropic->getModelDisplayName())->toBe('Claude Opus');

        $openai = new AgentIdentity('openai', 'gpt-4', 'high');
        expect($openai->getProviderDisplayName())->toBe('OpenAI');
        expect($openai->getModelDisplayName())->toBe('GPT-4');
    });
});
