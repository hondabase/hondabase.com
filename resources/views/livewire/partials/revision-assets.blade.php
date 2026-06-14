@if (!empty($rev->assets))
    <div class="rev-assets" aria-label="Uploaded images">
        @foreach ($rev->assets as $asset)
            <a href="{{ route('admin.revision.asset', ['revision' => $rev, 'file' => $asset]) }}" target="_blank" rel="noopener">
                <img src="{{ route('admin.revision.asset', ['revision' => $rev, 'file' => $asset]) }}" alt="">
                <span>{{ $asset }}</span>
            </a>
        @endforeach
    </div>
@endif
