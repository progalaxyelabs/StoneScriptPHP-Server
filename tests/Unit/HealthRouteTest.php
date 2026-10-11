<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Routes\HealthRoute;
use PHPUnit\Framework\TestCase;

/**
 * /health must answer 200 without touching the database: it backs Docker liveness probes, and a
 * database blip must not cascade into a container restart loop. (Regression: an earlier version
 * called a method that does not exist on `Database`; the resulting \Error escaped the
 * `catch (\Exception)` and every probe fataled.)
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
