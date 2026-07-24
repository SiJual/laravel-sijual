@props(['active' => false, 'icon' => '', 'href' => '#'])

<a href="{{ $href }}"
   @class([
       'flex items-center gap-4 px-4 py-2 text-sm font-semibold tracking-wide rounded-md transition-colors',
       'bg-primary-subtle text-primary border-r-4 border-primary' => $active,
       'text-on-surface-variant hover:bg-surface-alt' => !$active,
   ])>
    <span class="size-[18px]">
        @switch($icon)
            @case('grid')
                <svg fill="currentColor" viewBox="0 0 20 20"><path d="M5 3a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2V5a2 2 0 00-2-2H5zM5 11a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2v-2a2 2 0 00-2-2H5zM11 5a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V5zM11 13a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                @break
            @case('wallet')
                <svg fill="currentColor" viewBox="0 0 20 20"><path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z"/><path fill-rule="evenodd" d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM14 13a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>
                @break
            @case('map')
                <svg fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M12 1.586l-4 4v12.828l4-4V1.586zM3.707 3.293A1 1 0 002 4v10a1 1 0 00.293.707l4 4a1 1 0 001.414 0l4-4a1 1 0 00.293-.707V4a1 1 0 00-.293-.707l-4-4a1 1 0 00-1.414 0l-4 4z" clip-rule="evenodd"/></svg>
                @break
            @case('megaphone')
                <svg fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 3a1 1 0 00-1.447-.894L8.763 6H5a3 3 0 00-3 3v2a3 3 0 003 3h3.763l7.79 3.894A1 1 0 0018 17V3z" clip-rule="evenodd"/></svg>
                @break
            @case('box')
                <svg fill="currentColor" viewBox="0 0 20 20"><path d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4V7h12v8z"/></svg>
                @break
            @default
                <svg fill="currentColor" viewBox="0 0 20 20"><path d="M10 2a8 8 0 100 16 8 8 0 000-16z"/></svg>
        @endswitch
    </span>
    <span>{{ $slot }}</span>
</a>
