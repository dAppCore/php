<?php

declare(strict_types=1);

namespace Core\Tests\Feature;

use Core\Events\EventAuditLog;
use Core\Tests\TestCase;

class EventAuditLogTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        EventAuditLog::reset();
    }

    protected function tearDown(): void
    {
        EventAuditLog::reset();
        parent::tearDown();
    }

    public function test_is_disabled_by_default(): void
    {
        $this->assertFalse(EventAuditLog::isEnabled());
    }

    public function test_can_be_enabled_and_disabled(): void
    {
        EventAuditLog::enable();
        $this->assertTrue(EventAuditLog::isEnabled());

        EventAuditLog::disable();
        $this->assertFalse(EventAuditLog::isEnabled());
    }

    public function test_does_not_record_when_disabled(): void
    {
        EventAuditLog::recordStart('TestEvent', 'TestHandler');
        EventAuditLog::recordSuccess('TestEvent', 'TestHandler');

        $this->assertEmpty(EventAuditLog::entries());
    }

    public function test_records_successful_handler_execution(): void
    {
        EventAuditLog::enable();

        EventAuditLog::recordStart('TestEvent', 'TestHandler');
        EventAuditLog::recordSuccess('TestEvent', 'TestHandler');

        $entries = EventAuditLog::entries();

        $this->assertCount(1, $entries);
        $this->assertEquals('TestEvent', $entries[0]['event']);
        $this->assertEquals('TestHandler', $entries[0]['handler']);
        $this->assertFalse($entries[0]['failed']);
        $this->assertGreaterThanOrEqual(0, $entries[0]['duration_ms']);
    }

    public function test_records_failed_handler_execution(): void
    {
        EventAuditLog::enable();

        $error = new \RuntimeException('Test error');
        EventAuditLog::recordStart('TestEvent', 'TestHandler');
        EventAuditLog::recordFailure('TestEvent', 'TestHandler', $error);

        $entries = EventAuditLog::entries();

        $this->assertCount(1, $entries);
        $this->assertEquals('TestEvent', $entries[0]['event']);
        $this->assertEquals('TestHandler', $entries[0]['handler']);
        $this->assertTrue($entries[0]['failed']);
        $this->assertEquals('Test error', $entries[0]['error']);
    }

    public function test_returns_entries_for_specific_event(): void
    {
        EventAuditLog::enable();

        EventAuditLog::recordStart('EventA', 'Handler1');
        EventAuditLog::recordSuccess('EventA', 'Handler1');
        EventAuditLog::recordStart('EventB', 'Handler2');
        EventAuditLog::recordSuccess('EventB', 'Handler2');
        EventAuditLog::recordStart('EventA', 'Handler3');
        EventAuditLog::recordSuccess('EventA', 'Handler3');

        $entries = EventAuditLog::entriesFor('EventA');

        $this->assertCount(2, $entries);
        $this->assertEquals('Handler1', $entries[0]['handler']);
        $this->assertEquals('Handler3', $entries[1]['handler']);
    }

    public function test_returns_only_failures(): void
    {
        EventAuditLog::enable();

        EventAuditLog::recordStart('Event1', 'Handler1');
        EventAuditLog::recordSuccess('Event1', 'Handler1');

        $error = new \RuntimeException('Failed');
        EventAuditLog::recordStart('Event2', 'Handler2');
        EventAuditLog::recordFailure('Event2', 'Handler2', $error);

        $failures = EventAuditLog::failures();

        $this->assertCount(1, $failures);
        $this->assertEquals('Handler2', $failures[0]['handler']);
    }

    public function test_provides_summary_statistics(): void
    {
        EventAuditLog::enable();

        EventAuditLog::recordStart('EventA', 'Handler1');
        EventAuditLog::recordSuccess('EventA', 'Handler1');
        EventAuditLog::recordStart('EventA', 'Handler2');
        EventAuditLog::recordSuccess('EventA', 'Handler2');
        EventAuditLog::recordStart('EventB', 'Handler3');
        EventAuditLog::recordFailure('EventB', 'Handler3', new \Exception('Error'));

        $summary = EventAuditLog::summary();

        $this->assertEquals(3, $summary['total']);
        $this->assertEquals(1, $summary['failed']);
        $this->assertEquals(2, $summary['events']['EventA']);
        $this->assertEquals(1, $summary['events']['EventB']);
    }

    public function test_can_clear_entries(): void
    {
        EventAuditLog::enable();

        EventAuditLog::recordStart('TestEvent', 'TestHandler');
        EventAuditLog::recordSuccess('TestEvent', 'TestHandler');

        $this->assertCount(1, EventAuditLog::entries());

        EventAuditLog::clear();

        $this->assertEmpty(EventAuditLog::entries());
    }

    public function test_reset_disables_and_clears(): void
    {
        EventAuditLog::enable();
        EventAuditLog::enableLog();

        EventAuditLog::recordStart('TestEvent', 'TestHandler');
        EventAuditLog::recordSuccess('TestEvent', 'TestHandler');

        EventAuditLog::reset();

        $this->assertFalse(EventAuditLog::isEnabled());
        $this->assertEmpty(EventAuditLog::entries());
    }
}
