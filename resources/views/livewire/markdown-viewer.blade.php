<div
    x-data="markdown_viewer"
    x-init="init()"
    class="min-h-screen"
>
    <aside
        class="fixed left-0 top-0 h-screen w-72 overflow-y-auto border-r border-zinc-200 py-6 scrollbar-none dark:border-zinc-700"
        x-ref="sidebar"
        style="-ms-overflow-style: none; scrollbar-width: none;"
    >
        <nav class="space-y-0.5 pr-4">
            @foreach ($this->tableOfContents as $item)
                <a
                    href="#{{ $item['slug'] }}"
                    @click.prevent="scrollToSection('{{ $item['slug'] }}')"
                    class="relative block py-1 text-sm transition-colors"
                    :class="{
                        'text-accent font-medium': activeSection === '{{ $item['slug'] }}',
                        'text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100': activeSection !== '{{ $item['slug'] }}'
                    }"
                    style="padding-left: {{ ($item['level'] - 1) * 16 + 16 }}px"
                >
                    @if ($item['level'] > 1)
                        @for ($i = 1; $i < $item['level']; $i++)
                            <span
                                class="absolute top-0 bottom-0 w-px bg-zinc-200 dark:bg-zinc-700"
                                style="left: {{ $i * 16 }}px"
                            ></span>
                        @endfor
                    @endif
                    <span class="line-clamp-2">
                        @if ($item['number'])
                            <span class="text-zinc-400 mr-1">{{ $item['number'] }}</span>
                        @endif
                        {{ $item['title'] }}
                    </span>
                </a>
            @endforeach
        </nav>
    </aside>

    <main class="ml-72 min-w-0 py-8 pr-8 pl-8">
        <div class="max-w-none">
            @foreach ($this->topLevelSections as $section)
                <section
                    id="{{ $section['slug'] }}"
                    class="mb-8 scroll-mt-8"
                    x-intersect:enter.margin.-20%.0px.-70%.0px="setActiveSection('{{ $section['slug'] }}')"
                >
                    @if ($section['level'] === 1)
                        <flux:heading size="xl" class="!text-2xl">{{ $section['title'] }}</flux:heading>
                    @else
                        <flux:heading size="lg" class="!text-xl">
                            <span class="text-zinc-400 mr-2">{{ $section['number'] }}</span>
                            {{ $section['title'] }}
                        </flux:heading>
                    @endif

                    <div class="mt-4">
                        <div class="prose prose-zinc dark:prose-invert max-w-none prose-headings:scroll-mt-8 prose-h3:text-lg prose-h3:font-semibold prose-h4:text-base prose-h4:font-medium prose-h5:text-sm prose-h5:font-medium prose-h6:text-sm prose-p:text-zinc-600 dark:prose-p:text-zinc-300 prose-a:text-accent prose-a:no-underline hover:prose-a:underline prose-code:rounded prose-code:bg-zinc-100 prose-code:px-1.5 prose-code:py-0.5 prose-code:text-sm prose-code:font-normal prose-code:before:content-none prose-code:after:content-none dark:prose-code:bg-zinc-800 prose-pre:overflow-x-auto prose-pre:bg-zinc-900 prose-pre:text-zinc-100 prose-pre:p-4 dark:prose-pre:bg-zinc-950 [&_pre_code]:bg-transparent [&_pre_code]:p-0 [&_pre_code]:text-zinc-100 prose-table:w-full prose-table:text-sm prose-th:bg-zinc-100 prose-th:px-4 prose-th:py-2 prose-th:text-left prose-th:font-semibold prose-th:whitespace-nowrap dark:prose-th:bg-zinc-800 prose-td:border-t prose-td:border-zinc-200 prose-td:px-4 prose-td:py-2 dark:prose-td:border-zinc-700 prose-strong:text-zinc-900 dark:prose-strong:text-zinc-100 prose-hr:border-zinc-200 dark:prose-hr:border-zinc-700">
                            {!! $this->renderMarkdown($section['content'], $section['h2Index'] ?? 0) !!}
                        </div>
                    </div>
                </section>
            @endforeach
        </div>
    </main>
</div>
