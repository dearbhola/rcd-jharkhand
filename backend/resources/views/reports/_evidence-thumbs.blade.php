{{-- $items: evidence collection --}}
@if ($items->isNotEmpty())
    <div class="d-flex flex-wrap gap-2 mt-2">
        @foreach ($items as $ev)
            <a href="{{ route('evidence.display', $ev) }}" target="_blank" class="d-inline-block border rounded bg-light text-center" style="width:96px;height:72px;overflow:hidden" title="{{ ucfirst($ev->kind) }} · {{ d($ev->captured_at, true) }}">
                @if ($ev->thumbnail_path)
                    <img src="{{ route('evidence.thumbnail', $ev) }}" alt="{{ ucfirst($ev->kind) }} evidence" style="width:100%;height:100%;object-fit:cover" loading="lazy">
                @else
                    <i class="bi {{ $ev->kind === 'video' ? 'bi-camera-video' : 'bi-image' }} fs-3 d-block pt-3 text-muted"></i>
                @endif
            </a>
        @endforeach
    </div>
@endif
