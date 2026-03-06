<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

namespace Core\Tests\Unit;

use Core\Helpers\UtmHelper;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UtmHelperTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Extract From Request
    // -------------------------------------------------------------------------

    #[Test]
    public function it_extracts_all_utm_params_from_request(): void
    {
        $request = Request::create('/', 'GET', [
            'utm_source' => 'google',
            'utm_medium' => 'cpc',
            'utm_campaign' => 'spring_sale',
            'utm_term' => 'running shoes',
            'utm_content' => 'banner_ad',
        ]);

        $result = UtmHelper::extractFromRequest($request);

        $this->assertSame('google', $result['utm_source']);
        $this->assertSame('cpc', $result['utm_medium']);
        $this->assertSame('spring_sale', $result['utm_campaign']);
        $this->assertSame('running shoes', $result['utm_term']);
        $this->assertSame('banner_ad', $result['utm_content']);
    }

    #[Test]
    public function it_returns_null_for_missing_utm_params(): void
    {
        $request = Request::create('/', 'GET', [
            'utm_source' => 'google',
        ]);

        $result = UtmHelper::extractFromRequest($request);

        $this->assertSame('google', $result['utm_source']);
        $this->assertNull($result['utm_medium']);
        $this->assertNull($result['utm_campaign']);
        $this->assertNull($result['utm_term']);
        $this->assertNull($result['utm_content']);
    }

    #[Test]
    public function it_returns_all_nulls_when_no_utm_params(): void
    {
        $request = Request::create('/', 'GET');

        $result = UtmHelper::extractFromRequest($request);

        $this->assertNull($result['utm_source']);
        $this->assertNull($result['utm_medium']);
        $this->assertNull($result['utm_campaign']);
        $this->assertNull($result['utm_term']);
        $this->assertNull($result['utm_content']);
    }

    #[Test]
    public function it_sanitises_utm_values(): void
    {
        $request = Request::create('/', 'GET', [
            'utm_source' => '  google  ', // whitespace
            'utm_medium' => "with\nnewline", // control char
        ]);

        $result = UtmHelper::extractFromRequest($request);

        $this->assertSame('google', $result['utm_source']);
        $this->assertSame('withnewline', $result['utm_medium']);
    }

    #[Test]
    public function it_truncates_long_utm_values(): void
    {
        $longValue = str_repeat('a', 500);

        $request = Request::create('/', 'GET', [
            'utm_source' => $longValue,
        ]);

        $result = UtmHelper::extractFromRequest($request);

        $this->assertSame(255, strlen($result['utm_source']));
    }

    #[Test]
    public function it_treats_empty_strings_as_null(): void
    {
        $request = Request::create('/', 'GET', [
            'utm_source' => '',
            'utm_medium' => '   ', // whitespace only
        ]);

        $result = UtmHelper::extractFromRequest($request);

        $this->assertNull($result['utm_source']);
        $this->assertNull($result['utm_medium']);
    }

    // -------------------------------------------------------------------------
    // Extract From Array
    // -------------------------------------------------------------------------

    #[Test]
    public function it_extracts_utm_params_from_array(): void
    {
        $data = [
            'utm_source' => 'facebook',
            'utm_medium' => 'social',
            'utm_campaign' => 'awareness',
        ];

        $result = UtmHelper::extractFromArray($data);

        $this->assertSame('facebook', $result['utm_source']);
        $this->assertSame('social', $result['utm_medium']);
        $this->assertSame('awareness', $result['utm_campaign']);
    }

    #[Test]
    public function it_handles_empty_array(): void
    {
        $result = UtmHelper::extractFromArray([]);

        $this->assertNull($result['utm_source']);
        $this->assertNull($result['utm_medium']);
    }

    // -------------------------------------------------------------------------
    // Extract From URL
    // -------------------------------------------------------------------------

    #[Test]
    public function it_extracts_utm_params_from_url(): void
    {
        $url = 'https://example.com/page?utm_source=twitter&utm_medium=social&utm_campaign=launch';

        $result = UtmHelper::extractFromUrl($url);

        $this->assertSame('twitter', $result['utm_source']);
        $this->assertSame('social', $result['utm_medium']);
        $this->assertSame('launch', $result['utm_campaign']);
    }

    #[Test]
    public function it_handles_url_without_query_string(): void
    {
        $url = 'https://example.com/page';

        $result = UtmHelper::extractFromUrl($url);

        $this->assertNull($result['utm_source']);
        $this->assertNull($result['utm_medium']);
    }

    #[Test]
    public function it_handles_url_with_other_params(): void
    {
        $url = 'https://example.com/page?foo=bar&utm_source=newsletter&baz=qux';

        $result = UtmHelper::extractFromUrl($url);

        $this->assertSame('newsletter', $result['utm_source']);
        $this->assertNull($result['utm_medium']);
    }

    #[Test]
    public function it_handles_encoded_utm_values_in_url(): void
    {
        $url = 'https://example.com/page?utm_source=google&utm_term=running%20shoes';

        $result = UtmHelper::extractFromUrl($url);

        $this->assertSame('google', $result['utm_source']);
        $this->assertSame('running shoes', $result['utm_term']);
    }

    // -------------------------------------------------------------------------
    // Has UTM Params
    // -------------------------------------------------------------------------

    #[Test]
    public function it_detects_when_utm_params_present(): void
    {
        $request = Request::create('/', 'GET', [
            'utm_source' => 'google',
        ]);

        $this->assertTrue(UtmHelper::hasUtmParams($request));
    }

    #[Test]
    public function it_detects_when_no_utm_params(): void
    {
        $request = Request::create('/', 'GET', [
            'foo' => 'bar',
        ]);

        $this->assertFalse(UtmHelper::hasUtmParams($request));
    }

    #[Test]
    public function it_detects_any_utm_param(): void
    {
        // Only utm_content present
        $request = Request::create('/', 'GET', [
            'utm_content' => 'sidebar',
        ]);

        $this->assertTrue(UtmHelper::hasUtmParams($request));
    }

    // -------------------------------------------------------------------------
    // Get Source
    // -------------------------------------------------------------------------

    #[Test]
    public function it_gets_source_from_utm_source(): void
    {
        $request = Request::create('/', 'GET', [
            'utm_source' => 'linkedin',
        ]);

        $this->assertSame('linkedin', UtmHelper::getSource($request));
    }

    #[Test]
    public function it_falls_back_to_referrer_when_no_utm_source(): void
    {
        $request = Request::create('/', 'GET');
        $request->headers->set('Referer', 'https://twitter.com/some/page');

        $this->assertSame('twitter.com', UtmHelper::getSource($request));
    }

    #[Test]
    public function it_strips_www_from_referrer(): void
    {
        $request = Request::create('/', 'GET');
        $request->headers->set('Referer', 'https://www.facebook.com/some/page');

        $this->assertSame('facebook.com', UtmHelper::getSource($request));
    }

    #[Test]
    public function it_prefers_utm_source_over_referrer(): void
    {
        $request = Request::create('/', 'GET', [
            'utm_source' => 'email',
        ]);
        $request->headers->set('Referer', 'https://twitter.com/');

        $this->assertSame('email', UtmHelper::getSource($request));
    }

    #[Test]
    public function it_returns_null_when_no_source_available(): void
    {
        $request = Request::create('/', 'GET');

        $this->assertNull(UtmHelper::getSource($request));
    }

    // -------------------------------------------------------------------------
    // Extract Referrer Host
    // -------------------------------------------------------------------------

    #[Test]
    public function it_extracts_host_from_referrer(): void
    {
        $this->assertSame('example.com', UtmHelper::extractReferrerHost('https://example.com/page'));
        $this->assertSame('sub.example.com', UtmHelper::extractReferrerHost('https://sub.example.com/'));
    }

    #[Test]
    public function it_removes_www_from_referrer_host(): void
    {
        $this->assertSame('example.com', UtmHelper::extractReferrerHost('https://www.example.com/page'));
    }

    #[Test]
    public function it_returns_null_for_null_referrer(): void
    {
        $this->assertNull(UtmHelper::extractReferrerHost(null));
    }

    #[Test]
    public function it_returns_null_for_invalid_referrer(): void
    {
        $this->assertNull(UtmHelper::extractReferrerHost('not-a-url'));
    }

    // -------------------------------------------------------------------------
    // Constants
    // -------------------------------------------------------------------------

    #[Test]
    public function it_defines_utm_constants(): void
    {
        $this->assertSame('utm_source', UtmHelper::UTM_SOURCE);
        $this->assertSame('utm_medium', UtmHelper::UTM_MEDIUM);
        $this->assertSame('utm_campaign', UtmHelper::UTM_CAMPAIGN);
        $this->assertSame('utm_term', UtmHelper::UTM_TERM);
        $this->assertSame('utm_content', UtmHelper::UTM_CONTENT);
    }

    #[Test]
    public function it_lists_all_utm_params(): void
    {
        $all = UtmHelper::ALL_PARAMS;

        $this->assertCount(5, $all);
        $this->assertContains('utm_source', $all);
        $this->assertContains('utm_medium', $all);
        $this->assertContains('utm_campaign', $all);
        $this->assertContains('utm_term', $all);
        $this->assertContains('utm_content', $all);
    }
}
