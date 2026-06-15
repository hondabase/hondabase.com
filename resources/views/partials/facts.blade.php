@php
    // Applicability panel. Fully data-driven so it serves the whole Honda + Acura catalog
    // (cars, motorcycles, aircraft, power equipment): every applies_to field is optional and
    // any field renders, with humanized labels and special styling for a few common ones.
    $at   = is_array($art['applies_to'] ?? null) ? $art['applies_to'] : [];
    $tags = (array) ($art['tags'] ?? []);

    $isFamily    = fn ($e) => !preg_match('/\d/', (string) $e);
    $familyLabel = fn ($e) => strtoupper(preg_replace('/[-_ ]?series$/i', '', strtolower(trim((string) $e)))) . '-Series';

    // Preferred labels + display order for well-known fields; anything else is humanized.
    $labels = [
        'brand' => 'Brand', 'models' => 'Models', 'model' => 'Model', 'chassis' => 'Chassis',
        'trims' => 'Trims', 'trim' => 'Trim', 'engines' => 'Engines', 'engine' => 'Engine',
        'displacement' => 'Displacement', 'ecus' => 'ECUs', 'obd' => 'OBD', 'systems' => 'Systems',
        'years' => 'Years', 'scope' => 'Scope',
    ];
    $order = ['brand', 'models', 'model', 'chassis', 'trims', 'trim', 'engines', 'engine',
              'displacement', 'ecus', 'obd', 'systems', 'years', 'scope'];

    $keys = array_merge(
        array_values(array_filter($order, fn ($k) => array_key_exists($k, $at))),
        array_values(array_filter(array_keys($at), fn ($k) => !in_array($k, $order, true)))
    );

    $rows = [];
    foreach ($keys as $k) {
        $items = [];
        foreach ((array) $at[$k] as $v) {
            if (!is_scalar($v) || trim((string) $v) === '') continue;
            $v = trim((string) $v);
            if ($k === 'obd')                            $items[] = ['badge obd', 'OBD' . $v];
            elseif ($k === 'engines' || $k === 'engine') $items[] = $isFamily($v) ? ['badge series', $familyLabel($v)] : ['chip', $v];
            elseif ($k === 'scope')                      $items[] = ['chip', ucwords(str_replace('-', ' ', $v))];
            elseif ($k === 'brand')                      $items[] = ['chip', ucfirst($v)];
            else                                         $items[] = ['chip', $v];
        }
        if ($items) {
            $rows[] = [$labels[$k] ?? ucwords(str_replace('_', ' ', $k)), $items];
        }
    }
    if ($tags) {
        $ti = [];
        foreach ($tags as $t) {
            if (trim((string) $t) !== '') $ti[] = ['chip tag', (string) $t];
        }
        if ($ti) $rows[] = [__('Tags'), $ti];
    }
@endphp
@if ($rows)
<aside class="facts" aria-label="{{ __('Article applicability') }}">
    @foreach ($rows as [$label, $items])
        <div class="facts-row">
            <span class="facts-label">{{ __($label) }}</span>
            <span class="facts-vals">@foreach ($items as [$cls, $txt])<span class="{{ $cls }}">{{ $txt }}</span>@endforeach</span>
        </div>
    @endforeach
</aside>
@endif
