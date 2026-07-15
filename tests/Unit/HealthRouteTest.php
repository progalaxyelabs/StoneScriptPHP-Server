<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Routes\HealthRoute;
use PHPUnit\Framework\TestCase;

/**
 * Regression test for a real bug found during the stonescriptphp 5.x -> 6.0.0
 * upgrade investigation (2026-07-15): HealthRoute::process() called
 * `Database::query("SELECT 1")`, a method that does not exist anywhere on
 * the framework's `Database` class (gateway-only architecture only exposes
 * `Database::fn($name, $params)`). PHP raises a `\Error` (not `\Exception`)
 * for a call to an undefined method, so the surrounding
 * `catch (\Exception $e)` never caught it — every request to `/health`
 * fataled with a 500 instead of returning a graceful response. Since this
 * skeleton's `/health` also backs Docker/swarm liveness probes, that would
 * have kept any scaffolded container permanently "unhealthy".
 *
 * Fix: dropped the DB check entirely (matches every real platform's
 * HealthRoute — webmeteor-platform, medstoreapp-platform — which also never
 * probe the database here, since a DB blip should not cascade into a
 * container restart loop).
 */
class HealthRouteTest extends TestCase
{
    public function test_process_does_not_throw(): void
    {
        $response = (new HealthRoute())->process();

        $this->assertSame('ok', $response->status);
    }

    public function test_process_reports_healthy_status(): void
    {
        $response = (new HealthRoute())->process();

        $this->assertIsArray($response->data);
        $this->assertSame('healthy', $response->data['status']);
        $this->assertArrayHasKey('timestamp', $response->data);
    }

    public function test_validation_rules_is_empty(): void
    {
        $rules = (new HealthRoute())->validation_rules();

        $this->assertSame([], $rules);
    }
}
