<?php

declare(strict_types=1);

namespace Core\Tests\Feature;

use Core\Input\Input;
use Core\Tests\TestCase;
use Illuminate\Http\Request;

class InputTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Reset superglobals
        $_GET = [];
        $_POST = [];
    }

    protected function tearDown(): void
    {
        $_GET = [];
        $_POST = [];
        parent::tearDown();
    }

    public function test_capture_returns_request_instance(): void
    {
        $request = Input::capture();

        $this->assertInstanceOf(Request::class, $request);
    }

    public function test_capture_sanitises_get_data(): void
    {
        $_GET = [
            'name' => "Test\x00Value",
            'clean' => 'normal',
        ];

        Input::capture();

        $this->assertEquals('TestValue', $_GET['name']);
        $this->assertEquals('normal', $_GET['clean']);
    }

    public function test_capture_sanitises_post_data(): void
    {
        $_POST = [
            'data' => "Post\x01Data",
            'clean' => 'normal',
        ];

        Input::capture();

        $this->assertEquals('PostData', $_POST['data']);
        $this->assertEquals('normal', $_POST['clean']);
    }

    public function test_capture_handles_empty_superglobals(): void
    {
        $_GET = [];
        $_POST = [];

        $request = Input::capture();

        $this->assertInstanceOf(Request::class, $request);
    }
}
