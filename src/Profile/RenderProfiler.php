<?php

namespace Daycry\Twig\Profile;

/**
 * Aggregates per-template render timings (count / total / avg / max ms).
 *
 * Designed to be cheap when disabled: when the capability is off the facade
 * skips calling `record()`, so unused templates pay nothing beyond the boolean
 * check. Templates above the configured cap are bucketed under `__overflow__`
 * so memory cannot grow unbounded on a long-lived worker.
 */
final class RenderProfiler
{
    /**
     * @var array<string,array{count:int,total_ms:float,max_ms:float}>
     */
    private array $entries = [];

    public function __construct(private readonly int $templateCap = 200)
    {
    }

    public function record(string $template, float $elapsedSeconds): void
    {
        $key = isset($this->entries[$template]) || count($this->entries) < $this->templateCap
            ? $template
            : '__overflow__';

        $ms = $elapsedSeconds * 1000.0;
        if (! isset($this->entries[$key])) {
            $this->entries[$key] = ['count' => 0, 'total_ms' => 0.0, 'max_ms' => 0.0];
        }
        $this->entries[$key]['count']++;
        $this->entries[$key]['total_ms'] += $ms;
        if ($ms > $this->entries[$key]['max_ms']) {
            $this->entries[$key]['max_ms'] = $ms;
        }
    }

    /**
     * @return array<string,array{count:int,total_ms:float,avg_ms:float,max_ms:float}>
     */
    public function snapshot(): array
    {
        $out = [];

        foreach ($this->entries as $template => $entry) {
            $out[$template] = [
                'count'    => $entry['count'],
                'total_ms' => round($entry['total_ms'], 3),
                'avg_ms'   => $entry['count'] > 0 ? round($entry['total_ms'] / $entry['count'], 3) : 0.0,
                'max_ms'   => round($entry['max_ms'], 3),
            ];
        }

        return $out;
    }

    /**
     * @return list<array{template:string,count:int,total_ms:float,avg_ms:float,max_ms:float}>
     */
    public function topByTotal(int $limit = 10): array
    {
        $rows = [];

        foreach ($this->snapshot() as $tpl => $data) {
            $rows[] = ['template' => $tpl] + $data;
        }
        usort($rows, static fn (array $a, array $b): int => $b['total_ms'] <=> $a['total_ms']);

        return array_slice($rows, 0, $limit);
    }

    public function reset(): void
    {
        $this->entries = [];
    }
}
