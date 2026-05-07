<?php

declare(strict_types=1);

namespace Tests\Twig;

use Daycry\Twig\Profile\RenderProfiler;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class RenderProfilerTest extends TestCase
{
    public function testRecordAggregatesPerTemplate(): void
    {
        $p = new RenderProfiler();
        $p->record('home', 0.010);
        $p->record('home', 0.030);
        $p->record('about', 0.020);
        $snap = $p->snapshot();
        $this->assertSame(2, $snap['home']['count']);
        $this->assertEqualsWithDelta(40.0, $snap['home']['total_ms'], PHP_FLOAT_EPSILON);
        $this->assertEqualsWithDelta(20.0, $snap['home']['avg_ms'], PHP_FLOAT_EPSILON);
        $this->assertEqualsWithDelta(30.0, $snap['home']['max_ms'], PHP_FLOAT_EPSILON);
        $this->assertSame(1, $snap['about']['count']);
    }

    public function testTopByTotalSortsDescending(): void
    {
        $p = new RenderProfiler();
        $p->record('a', 0.001);
        $p->record('b', 0.005);
        $p->record('c', 0.010);
        $top = $p->topByTotal(2);
        $this->assertCount(2, $top);
        $this->assertSame('c', $top[0]['template']);
        $this->assertSame('b', $top[1]['template']);
    }

    public function testOverflowBucketProtectsFromUnboundedGrowth(): void
    {
        $p = new RenderProfiler(templateCap: 2);
        $p->record('first', 0.001);
        $p->record('second', 0.001);
        $p->record('third', 0.001); // → __overflow__
        $p->record('fourth', 0.002); // → __overflow__
        $snap = $p->snapshot();
        $this->assertArrayHasKey('first', $snap);
        $this->assertArrayHasKey('second', $snap);
        $this->assertArrayNotHasKey('third', $snap);
        $this->assertArrayHasKey('__overflow__', $snap);
        $this->assertSame(2, $snap['__overflow__']['count']);
    }

    public function testResetClearsAllEntries(): void
    {
        $p = new RenderProfiler();
        $p->record('home', 0.010);
        $p->reset();
        $this->assertSame([], $p->snapshot());
    }
}
