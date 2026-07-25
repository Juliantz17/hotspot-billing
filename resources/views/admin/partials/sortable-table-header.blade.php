<th class="{{ $class ?? 'px-4 py-2 border-r border-gray-600' }}">
    <div class="flex items-center {{ $align ?? 'justify-start' }} gap-1.5">
        <span>{{ $label }}</span>
        <span class="inline-flex overflow-hidden rounded-sm border border-gray-500 bg-white normal-case">
            <a
                href="{{ request()->fullUrlWithQuery([$sortPrefix.'_sort' => $sortField, $sortPrefix.'_direction' => 'asc', 'page' => 1]) }}"
                data-analytics-sort="{{ $sortPrefix }}"
                class="inline-flex h-5 w-5 items-center justify-center {{ $currentSort === $sortField && $currentDirection === 'asc' ? 'bg-blue-600 text-white' : 'text-gray-700 hover:bg-gray-100' }}"
                title="Sort {{ strtolower($label) }} ascending"
                aria-label="Sort {{ strtolower($label) }} ascending"
            >&uarr;</a>
            <a
                href="{{ request()->fullUrlWithQuery([$sortPrefix.'_sort' => $sortField, $sortPrefix.'_direction' => 'desc', 'page' => 1]) }}"
                data-analytics-sort="{{ $sortPrefix }}"
                class="inline-flex h-5 w-5 items-center justify-center border-l border-gray-300 {{ $currentSort === $sortField && $currentDirection === 'desc' ? 'bg-blue-600 text-white' : 'text-gray-700 hover:bg-gray-100' }}"
                title="Sort {{ strtolower($label) }} descending"
                aria-label="Sort {{ strtolower($label) }} descending"
            >&darr;</a>
        </span>
    </div>
</th>