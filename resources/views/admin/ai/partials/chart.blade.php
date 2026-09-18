@php
    $labels = array_values($block['labels'] ?? []);
    $datasets = array_values($block['datasets'] ?? []);
    $values = collect($datasets)->flatMap(fn ($dataset) => $dataset['data'] ?? [])->map(fn ($value) => (float) $value);
    $max = max(1, (float) ($values->max() ?? 1));
    $min = min(0, (float) ($values->min() ?? 0));
    $palette = ['#7b2f50', '#bd4e78', '#d89aae', '#8f6f7d', '#d6a44a', '#56876d'];
    $chartType = $block['chart_type'] ?? 'bar';
@endphp

<figure class="ai-chart mb-0" data-ai-chart="{{ e($chartType) }}">
    @if (! empty($block['title']))
        <figcaption class="fw-semibold mb-3">{{ $block['title'] }}</figcaption>
    @endif

    @if ($chartType === 'doughnut')
        @php
            $first = $datasets[0]['data'] ?? [];
            $total = max(1, array_sum(array_map('floatval', $first)));
            $offset = 25.0;
        @endphp
        <div class="d-flex flex-column flex-sm-row align-items-center gap-3">
            <svg class="ai-chart-doughnut" viewBox="0 0 42 42" role="img" aria-label="{{ e($block['title'] ?? 'Biểu đồ tròn') }}">
                <circle cx="21" cy="21" r="15.9" fill="none" stroke="#f1e7eb" stroke-width="6" />
                @foreach ($first as $index => $value)
                    @php
                        $percent = max(0, (float) $value / $total * 100);
                        $currentOffset = $offset;
                        $offset -= $percent;
                    @endphp
                    <circle cx="21" cy="21" r="15.9" fill="none"
                            stroke="{{ $palette[$index % count($palette)] }}" stroke-width="6"
                            stroke-dasharray="{{ $percent }} {{ 100 - $percent }}"
                            stroke-dashoffset="{{ $currentOffset }}" />
                @endforeach
            </svg>
            <div class="small flex-grow-1">
                @foreach ($labels as $index => $label)
                    <div class="d-flex justify-content-between gap-3 py-1">
                        <span><i class="ai-chart-dot ai-chart-color-{{ $index % count($palette) }}"></i>{{ $label }}</span>
                        <strong class="neo-num">{{ $first[$index] ?? 0 }}</strong>
                    </div>
                @endforeach
            </div>
        </div>
    @elseif ($chartType === 'line')
        @php
            $pointCount = max(2, count($labels));
            $width = 100;
            $height = 40;
            $paddingX = 4;
            $paddingY = 4;
            $plotWidth = $width - ($paddingX * 2);
            $plotHeight = $height - ($paddingY * 2);
            $range = max(1, $max - $min);

            $xPositions = array_map(
                fn (int $i): float => $paddingX + ($pointCount <= 1 ? 0 : ($i / ($pointCount - 1)) * $plotWidth),
                range(0, $pointCount - 1),
            );

            $lines = [];
            $dots = [];
            foreach ($datasets as $dsIndex => $dataset) {
                $data = array_values(array_slice($dataset['data'] ?? [], 0, $pointCount));
                $pathPoints = [];
                foreach ($data as $i => $value) {
                    $x = $xPositions[$i] ?? $paddingX;
                    $y = $paddingY + ($plotHeight - ((float) $value - $min) / $range * $plotHeight);
                    $pathPoints[] = "{$x},{$y}";
                    $dots[] = [
                        'x' => $x, 'y' => $y,
                        'color' => $palette[$dsIndex % count($palette)],
                        'label' => ($dataset['label'] ?? 'Dữ liệu').': '.($labels[$i] ?? '').' = '.$value,
                    ];
                }
                $lines[] = [
                    'd' => 'M'.implode(' L', $pathPoints),
                    'color' => $palette[$dsIndex % count($palette)],
                    'label' => $dataset['label'] ?? 'Dữ liệu',
                ];
            }
        @endphp
        <div class="ai-chart-line-wrap">
            <svg class="ai-chart-line" viewBox="0 0 100 40" preserveAspectRatio="none" role="img" aria-label="{{ e($block['title'] ?? 'Biểu đồ đường') }}">
                @foreach ($lines as $line)
                    <path d="{{ $line['d'] }}" fill="none" stroke="{{ $line['color'] }}" stroke-width="0.6" stroke-linecap="round" stroke-linejoin="round" />
                @endforeach
                @foreach ($dots as $dot)
                    <circle cx="{{ $dot['x'] }}" cy="{{ $dot['y'] }}" r="0.8" fill="{{ $dot['color'] }}" role="img" aria-label="{{ e($dot['label']) }}" />
                @endforeach
            </svg>
            <div class="ai-chart-line-labels small text-body-secondary">
                @foreach ($labels as $index => $label)
                    <span title="{{ e($label) }}">{{ Str::limit($label, 10) }}</span>
                @endforeach
            </div>
        </div>
        @if (count($datasets) > 1)
            <div class="d-flex flex-wrap gap-3 mt-2 small text-body-secondary">
                @foreach ($datasets as $index => $dataset)
                    <span><i class="ai-chart-dot ai-chart-color-{{ $index % count($palette) }}"></i>{{ $dataset['label'] ?? 'Dữ liệu' }}</span>
                @endforeach
            </div>
        @endif
        {{-- Fallback accessible: hidden table for screen readers --}}
        <table class="visually-hidden" role="table" aria-label="{{ e($block['title'] ?? 'Dữ liệu biểu đồ đường') }}">
            <thead>
                <tr><th>Nhãn</th>
                    @foreach ($datasets as $dataset)
                        <th>{{ e($dataset['label'] ?? 'Dữ liệu') }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($labels as $index => $label)
                    <tr>
                        <td>{{ e($label) }}</td>
                        @foreach ($datasets as $dataset)
                            <td>{{ $dataset['data'][$index] ?? 0 }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="ai-chart-bars" role="img" aria-label="{{ e($block['title'] ?? 'Biểu đồ') }}">
            @foreach ($labels as $index => $label)
                <div class="ai-chart-row">
                    <span class="ai-chart-label" title="{{ e($label) }}">{{ $label }}</span>
                    <div class="ai-chart-tracks">
                        @foreach ($datasets as $datasetIndex => $dataset)
                            @php($value = (float) ($dataset['data'][$index] ?? 0))
                            <div class="ai-chart-track" title="{{ e($dataset['label'] ?? '') }}: {{ $value }}">
                                <progress class="ai-chart-progress ai-chart-color-{{ $datasetIndex % count($palette) }}"
                                          max="{{ $max }}" value="{{ max(0, $value) }}">{{ $value }}</progress>
                                <small>{{ $value }}</small>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
        @if (count($datasets) > 1)
            <div class="d-flex flex-wrap gap-3 mt-2 small text-body-secondary">
                @foreach ($datasets as $index => $dataset)
                    <span><i class="ai-chart-dot ai-chart-color-{{ $index % count($palette) }}"></i>{{ $dataset['label'] ?? 'Dữ liệu' }}</span>
                @endforeach
            </div>
        @endif
    @endif
</figure>
