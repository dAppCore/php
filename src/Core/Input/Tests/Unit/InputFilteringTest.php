<?php

/*
 * Core PHP Framework
 *
 * Licensed under the European Union Public Licence (EUPL) v1.2.
 * See LICENSE file for details.
 */

declare(strict_types=1);

use Core\Input\Sanitiser;

describe('Sanitiser', function () {
    describe('clean input passthrough', function () {
        it('passes clean ASCII strings', function () {
            $sanitiser = new Sanitiser;
            $result = $sanitiser->filter(['name' => 'Hello World']);

            expect($result['name'])->toBe('Hello World');
        });

        it('passes strings with punctuation', function () {
            $sanitiser = new Sanitiser;
            $result = $sanitiser->filter(['text' => "Hello, World! How's it going?"]);

            expect($result['text'])->toBe("Hello, World! How's it going?");
        });

        it('passes strings with numbers', function () {
            $sanitiser = new Sanitiser;
            $result = $sanitiser->filter(['code' => 'ABC123-XYZ']);

            expect($result['code'])->toBe('ABC123-XYZ');
        });
    });

    describe('control character stripping', function () {
        it('strips null bytes', function () {
            $sanitiser = new Sanitiser;
            $result = $sanitiser->filter(['name' => "hello\0world"]);

            expect($result['name'])->toBe('helloworld');
        });

        it('strips bell character', function () {
            $sanitiser = new Sanitiser;
            $result = $sanitiser->filter(['name' => "hello\x07world"]);

            expect($result['name'])->toBe('helloworld');
        });

        it('strips backspace character', function () {
            $sanitiser = new Sanitiser;
            $result = $sanitiser->filter(['name' => "hello\x08world"]);

            expect($result['name'])->toBe('helloworld');
        });

        it('strips escape character', function () {
            $sanitiser = new Sanitiser;
            $result = $sanitiser->filter(['name' => "hello\x1Bworld"]);

            expect($result['name'])->toBe('helloworld');
        });

        it('strips tabs', function () {
            $sanitiser = new Sanitiser;
            $result = $sanitiser->filter(['name' => "hello\tworld"]);

            expect($result['name'])->toBe('helloworld');
        });

        it('strips newlines', function () {
            $sanitiser = new Sanitiser;
            $result = $sanitiser->filter(['name' => "hello\nworld"]);

            expect($result['name'])->toBe('helloworld');
        });

        it('strips carriage returns', function () {
            $sanitiser = new Sanitiser;
            $result = $sanitiser->filter(['name' => "hello\rworld"]);

            expect($result['name'])->toBe('helloworld');
        });

        it('strips form feeds', function () {
            $sanitiser = new Sanitiser;
            $result = $sanitiser->filter(['name' => "hello\fworld"]);

            expect($result['name'])->toBe('helloworld');
        });

        it('strips multiple control characters', function () {
            $sanitiser = new Sanitiser;
            $result = $sanitiser->filter(['name' => "he\0ll\x07o\tworld\n"]);

            expect($result['name'])->toBe('helloworld');
        });
    });

    describe('Unicode preservation', function () {
        it('preserves Chinese characters', function () {
            $sanitiser = new Sanitiser;
            $result = $sanitiser->filter(['name' => '李明']);

            expect($result['name'])->toBe('李明');
        });

        it('preserves Japanese characters', function () {
            $sanitiser = new Sanitiser;
            $result = $sanitiser->filter(['name' => 'こんにちは']);

            expect($result['name'])->toBe('こんにちは');
        });

        it('preserves Korean characters', function () {
            $sanitiser = new Sanitiser;
            $result = $sanitiser->filter(['name' => '안녕하세요']);

            expect($result['name'])->toBe('안녕하세요');
        });

        it('preserves Arabic characters', function () {
            $sanitiser = new Sanitiser;
            $result = $sanitiser->filter(['name' => 'مرحبا']);

            expect($result['name'])->toBe('مرحبا');
        });

        it('preserves Russian characters', function () {
            $sanitiser = new Sanitiser;
            $result = $sanitiser->filter(['name' => 'Привет']);

            expect($result['name'])->toBe('Привет');
        });

        it('preserves French accented characters', function () {
            $sanitiser = new Sanitiser;
            $result = $sanitiser->filter(['name' => 'François']);

            expect($result['name'])->toBe('François');
        });

        it('preserves Spanish accented characters', function () {
            $sanitiser = new Sanitiser;
            $result = $sanitiser->filter(['name' => 'José María']);

            expect($result['name'])->toBe('José María');
        });

        it('preserves German umlauts', function () {
            $sanitiser = new Sanitiser;
            $result = $sanitiser->filter(['name' => 'Müller']);

            expect($result['name'])->toBe('Müller');
        });

        it('preserves emojis', function () {
            $sanitiser = new Sanitiser;
            $result = $sanitiser->filter(['message' => 'Hello 👋 World 🌍']);

            expect($result['message'])->toBe('Hello 👋 World 🌍');
        });

        it('preserves mixed Unicode and ASCII', function () {
            $sanitiser = new Sanitiser;
            $result = $sanitiser->filter(['name' => 'Hello 李明 Привет']);

            expect($result['name'])->toBe('Hello 李明 Привет');
        });

        it('strips control chars but preserves Unicode', function () {
            $sanitiser = new Sanitiser;
            $result = $sanitiser->filter(['name' => "José\0María"]);

            expect($result['name'])->toBe('JoséMaría');
        });
    });

    describe('edge cases', function () {
        it('handles empty input', function () {
            $sanitiser = new Sanitiser;
            $result = $sanitiser->filter([]);

            expect($result)->toBe([]);
        });

        it('handles empty string value', function () {
            $sanitiser = new Sanitiser;
            $result = $sanitiser->filter(['name' => '']);

            expect($result['name'])->toBe('');
        });

        it('handles multiple keys', function () {
            $sanitiser = new Sanitiser;
            $result = $sanitiser->filter([
                'a' => "test\0one",
                'b' => "test\x07two",
                'c' => 'clean',
            ]);

            expect($result['a'])->toBe('testone');
            expect($result['b'])->toBe('testtwo');
            expect($result['c'])->toBe('clean');
        });

        it('handles special characters in values', function () {
            $sanitiser = new Sanitiser;
            $result = $sanitiser->filter(['sql' => "SELECT * FROM users WHERE name = 'test'"]);

            expect($result['sql'])->toBe("SELECT * FROM users WHERE name = 'test'");
        });

        it('handles HTML in values', function () {
            $sanitiser = new Sanitiser;
            $result = $sanitiser->filter(['html' => '<script>alert("xss")</script>']);

            // Note: Sanitiser only strips control chars, not HTML
            expect($result['html'])->toBe('<script>alert("xss")</script>');
        });

        it('handles long strings', function () {
            $sanitiser = new Sanitiser;
            $longString = str_repeat('a', 10000);
            $result = $sanitiser->filter(['long' => $longString]);

            expect($result['long'])->toBe($longString);
        });

        it('handles string with only control characters', function () {
            $sanitiser = new Sanitiser;
            $result = $sanitiser->filter(['empty' => "\0\x07\t\n"]);

            expect($result['empty'])->toBe('');
        });
    });
});
