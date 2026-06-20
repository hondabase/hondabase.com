<div>
    {{-- Root slide: product lines from config --}}
    @if($node === '')
        @foreach($children as $line)
            <div>
                <span>{{ $line['label'] }}</span>
                @if(!empty($line['coming_soon']))
                    <span>Coming soon</span>
                @else
                    @php $count = $counts[$line['type']] ?? 0; @endphp
                    <span>{{ $count }} {{ Str::plural('article', $count) }}</span>
                @endif
            </div>
        @endforeach
    @else
        {{-- Breadcrumb --}}
        @foreach($breadcrumb as $crumb)
            <span>{{ $crumb['label'] }}</span>
        @endforeach

        {{-- Children --}}
        @foreach($children as $child)
            <div>{{ $child->name }}</div>
        @endforeach

        {{-- Handoff --}}
        @if($handoffUrl)
            <a href="{{ $handoffUrl }}">See all articles</a>
        @endif
    @endif

    {{-- Typeahead suggestions --}}
    @foreach($suggestions as $suggestion)
        <div>{{ $suggestion->name }}</div>
    @endforeach
</div>
