@props([
    'id' => null,
    'type' => 'line',
    'labels' => [],
    'datasets' => [],
    'height' => 'h-64',
    'options' => [],
])

@php
    $chartId = $id ?? 'chart-' . \Illuminate\Support\Str::random(6);
@endphp

<div 
    x-data="{
        chartInstance: null,
        initChart() {
            if (!window.Chart) return;
            const ctx = document.getElementById('{{ $chartId }}');
            if (!ctx) return;

            const isDark = document.documentElement.classList.contains('dark');
            const textColor = isDark ? '#94a3b8' : '#64748b';
            const gridColor = isDark ? 'rgba(255, 255, 255, 0.06)' : 'rgba(0, 0, 0, 0.06)';

            const defaultOptions = {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        labels: { color: textColor, font: { family: 'Inter', size: 11, weight: '600' } }
                    }
                },
                scales: {
                    x: {
                        grid: { color: gridColor },
                        ticks: { color: textColor, font: { family: 'Inter', size: 10 } }
                    },
                    y: {
                        grid: { color: gridColor },
                        ticks: { color: textColor, font: { family: 'Inter', size: 10 } }
                    }
                }
            };

            const mergedOptions = Object.assign({}, defaultOptions, @js($options));

            this.chartInstance = new window.Chart(ctx, {
                type: '{{ $type }}',
                data: {
                    labels: @js($labels),
                    datasets: @js($datasets),
                },
                options: mergedOptions
            });
        }
    }"
    x-init="$nextTick(() => initChart())"
    class="w-full relative {{ $height }}"
>
    <canvas id="{{ $chartId }}"></canvas>
</div>
