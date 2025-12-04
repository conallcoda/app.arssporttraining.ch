<div class="flex gap-8 mb-20">
    {{-- Sticky Sidebar TOC --}}
    @if (count($this->tableOfContents) > 0)
        <aside class="hidden w-64 shrink-0 lg:block">
            <nav
                class="sticky top-4 max-h-[calc(100vh-2rem)] overflow-y-auto rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800/50">
                <p class="mb-3 text-sm font-semibold text-zinc-900 dark:text-zinc-100">Contents</p>
                <ul class="space-y-1 text-sm">
                    @foreach ($this->tableOfContents as $item)
                        <li class="{{ $item['level'] === 3 ? 'ml-4' : '' }}">
                            <a href="#{{ $item['slug'] }}"
                                class="flex text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100">
                                <span class="mr-2 text-zinc-400 dark:text-zinc-500">{{ $item['number'] }}</span>
                                <span>{{ $item['title'] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </nav>
        </aside>
    @endif

    {{-- Main Content --}}
    <div
        class="prose prose-zinc dark:prose-invert min-w-0 max-w-none flex-1 prose-headings:scroll-mt-8 prose-h1:text-2xl prose-h2:text-xl prose-h3:text-lg prose-h3:font-semibold prose-h4:text-base prose-h4:font-medium prose-p:text-zinc-600 dark:prose-p:text-zinc-300 prose-a:text-accent prose-a:no-underline hover:prose-a:underline prose-table:w-full prose-table:text-sm prose-th:bg-zinc-100 prose-th:px-4 prose-th:py-2 prose-th:text-left prose-th:font-semibold dark:prose-th:bg-zinc-800 prose-td:border-t prose-td:border-zinc-200 prose-td:px-4 prose-td:py-2 dark:prose-td:border-zinc-700 prose-strong:text-zinc-900 dark:prose-strong:text-zinc-100 prose-hr:border-zinc-200 dark:prose-hr:border-zinc-700">
        {!! $this->html !!}
    </div>

</div>
