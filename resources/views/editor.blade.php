<div>
    <div x-data="symfonyExpressionEditor()" class="w-full">
        <!-- Editor Container -->
        <div class="border border-gray-300 rounded-lg overflow-hidden bg-white">
            <!-- Toolbar -->
            <div class="bg-gray-100 border-b border-gray-300 px-4 py-2 flex gap-2">
                <button @click="insertFunction('abs')" type="button"
                    class="px-3 py-1 bg-blue-500 text-white rounded text-sm hover:bg-blue-600">
                    Insert Function
                </button>
                <button @click="clearEditor()" type="button"
                    class="px-3 py-1 bg-gray-500 text-white rounded text-sm hover:bg-gray-600">
                    Clear
                </button>
            </div>

            <!-- Editor Area -->
            <div class="relative">
                <!-- Hidden textarea for value binding -->
                <textarea x-ref="textarea" @input="updateValue($event)" @keydown="handleKeydown($event)"
                    class="absolute inset-0 w-full h-full p-4 font-mono text-sm resize-none focus:outline-none bg-transparent text-transparent caret-black z-10"
                    style="min-height: 300px;" spellcheck="false"></textarea>

                <!-- Syntax highlighted display -->
                <pre class="p-4 font-mono text-sm whitespace-pre-wrap break-words bg-gray-50 border-r border-gray-200"
                    style="min-height: 300px; color: #1e293b;">
                <code x-ref="highlighter" class="block"></code>
            </pre>

                <!-- Autocomplete dropdown -->
                <div x-show="showAutocomplete" @click.outside="showAutocomplete = false"
                    class="absolute top-full left-0 w-64 bg-white border border-gray-300 rounded shadow-lg z-20 max-h-48 overflow-y-auto">
                    <template x-for="(item, index) in filteredSuggestions" :key="index">
                        <div @click="selectSuggestion(item)"
                            :class="index === selectedSuggestionIndex && 'bg-blue-500 text-white'"
                            class="px-4 py-2 hover:bg-blue-400 hover:text-white cursor-pointer text-sm">
                            <span class="font-semibold" x-text="item.text"></span>
                            <span class="text-gray-500 ml-2 text-xs" x-text="item.type"></span>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <!-- Error display -->
        <div x-show="error" class="mt-2 p-3 bg-red-100 text-red-700 rounded text-sm">
            <span x-text="error"></span>
        </div>

        <!-- Value output (for debugging) -->
        <div class="mt-4 p-3 bg-gray-100 rounded">
            <p class="text-xs text-gray-600 mb-1">Expression:</p>
            <code x-text="expressionValue" class="block font-mono text-sm text-gray-800 break-all"></code>
        </div>
    </div>

    <script>
        function symfonyExpressionEditor() {
            return {
                expressionValue: '',
                showAutocomplete: false,
                filteredSuggestions: [],
                selectedSuggestionIndex: 0,
                error: '',
                currentWord: '',

                // Symfony expression language reference
                functions: [
                    'abs', 'constant', 'cycle', 'date', 'dump', 'env',
                    'floor', 'range', 'round', 'sqrt', 'max', 'min', 'random', 'trim'
                ],

                operators: [
                    '+', '-', '*', '/', '%', '**',
                    '==', '!=', '<', '>', '<=', '>=',
                    'and', 'or', 'not', 'in', 'matches',
                    '~', '..', 'starts with', 'ends with'
                ],

                keywords: [
                    'true', 'false', 'null', 'and', 'or', 'not', 'in'
                ],

                variables: {
                    'user': 'Current user object',
                    'now': 'Current datetime',
                    'request': 'Current request',
                    'app': 'Application container'
                },

                init() {
                    this.$watch('expressionValue', () => this.highlight());
                },

                updateValue(event) {
                    this.expressionValue = event.target.value;
                    this.$refs.textarea.value = this.expressionValue;
                    this.handleAutocomplete();
                },

                handleKeydown(event) {
                    // Arrow keys for autocomplete navigation
                    if (this.showAutocomplete) {
                        if (event.key === 'ArrowDown') {
                            event.preventDefault();
                            this.selectedSuggestionIndex = Math.min(
                                this.selectedSuggestionIndex + 1,
                                this.filteredSuggestions.length - 1
                            );
                        } else if (event.key === 'ArrowUp') {
                            event.preventDefault();
                            this.selectedSuggestionIndex = Math.max(this.selectedSuggestionIndex - 1, 0);
                        } else if (event.key === 'Enter') {
                            event.preventDefault();
                            this.selectSuggestion(this.filteredSuggestions[this.selectedSuggestionIndex]);
                        } else if (event.key === 'Escape') {
                            this.showAutocomplete = false;
                        }
                    }

                    // Trigger autocomplete on special characters
                    if ([' ', '(', ',', '{', '['].includes(event.key)) {
                        setTimeout(() => this.handleAutocomplete(), 0);
                    }
                },

                handleAutocomplete() {
                    const textarea = this.$refs.textarea;
                    const text = textarea.value;
                    const cursorPos = textarea.selectionStart;

                    // Get the word being typed
                    const beforeCursor = text.substring(0, cursorPos);
                    const wordMatch = beforeCursor.match(/[\w]*$/);
                    this.currentWord = wordMatch ? wordMatch[0].toLowerCase() : '';

                    if (this.currentWord.length < 1) {
                        this.showAutocomplete = false;
                        return;
                    }

                    // Build suggestions
                    const suggestions = [];

                    // Add functions
                    this.functions.forEach(func => {
                        if (func.startsWith(this.currentWord)) {
                            suggestions.push({
                                text: func,
                                type: 'function',
                                insert: func + '()'
                            });
                        }
                    });

                    // Add operators
                    this.operators.forEach(op => {
                        if (op.startsWith(this.currentWord)) {
                            suggestions.push({
                                text: op,
                                type: 'operator',
                                insert: ' ' + op + ' '
                            });
                        }
                    });

                    // Add keywords
                    this.keywords.forEach(kw => {
                        if (kw.startsWith(this.currentWord)) {
                            suggestions.push({
                                text: kw,
                                type: 'keyword',
                                insert: kw
                            });
                        }
                    });

                    // Add variables
                    Object.entries(this.variables).forEach(([varName, desc]) => {
                        if (varName.startsWith(this.currentWord)) {
                            suggestions.push({
                                text: varName,
                                type: 'variable',
                                insert: varName,
                                description: desc
                            });
                        }
                    });

                    this.filteredSuggestions = suggestions.slice(0, 10);
                    this.selectedSuggestionIndex = 0;
                    this.showAutocomplete = this.filteredSuggestions.length > 0;
                },

                selectSuggestion(item) {
                    const textarea = this.$refs.textarea;
                    const text = textarea.value;
                    const cursorPos = textarea.selectionStart;

                    // Replace the current word with the suggestion
                    const beforeCursor = text.substring(0, cursorPos);
                    const afterCursor = text.substring(cursorPos);
                    const wordStart = beforeCursor.lastIndexOf(beforeCursor.match(/[\w]*$/)[0]);

                    const newText = text.substring(0, wordStart) + item.insert + afterCursor;
                    this.expressionValue = newText;
                    this.$refs.textarea.value = newText;

                    // Move cursor after the inserted text
                    setTimeout(() => {
                        textarea.selectionStart = wordStart + item.insert.length;
                        textarea.selectionEnd = wordStart + item.insert.length;
                        textarea.focus();
                    }, 0);

                    this.showAutocomplete = false;
                    this.highlight();
                },

                insertFunction(funcName) {
                    const textarea = this.$refs.textarea;
                    const cursorPos = textarea.selectionStart;
                    const text = textarea.value;
                    const insertion = funcName + '()';
                    const newText = text.substring(0, cursorPos) + insertion + text.substring(cursorPos);

                    this.expressionValue = newText;
                    this.$refs.textarea.value = newText;

                    setTimeout(() => {
                        textarea.selectionStart = cursorPos + funcName.length + 1;
                        textarea.selectionEnd = cursorPos + funcName.length + 1;
                        textarea.focus();
                    }, 0);

                    this.highlight();
                },

                highlight() {
                    const code = this.expressionValue;
                    let highlighted = this.escapeHtml(code);

                    // Highlight functions
                    this.functions.forEach(func => {
                        const regex = new RegExp(`\\b${func}\\b`, 'g');
                        highlighted = highlighted.replace(regex, `<span style="color: #3b82f6;">${func}</span>`);
                    });

                    // Highlight keywords
                    this.keywords.forEach(kw => {
                        const regex = new RegExp(`\\b${kw}\\b`, 'g');
                        highlighted = highlighted.replace(regex, `<span style="color: #d946ef;">${kw}</span>`);
                    });

                    // Highlight operators
                    const operatorRegex = /(\+|-|\*|\/|%|\*\*|==|!=|<=|>=|<|>|~|\.\.|and|or|not|in|matches)/g;
                    highlighted = highlighted.replace(operatorRegex, `<span style="color: #f97316;">$1</span>`);

                    // Highlight strings
                    highlighted = highlighted.replace(/(".*?"|'.*?')/g, `<span style="color: #16a34a;">$1</span>`);

                    // Highlight numbers
                    highlighted = highlighted.replace(/\b(\d+\.?\d*)\b/g, `<span style="color: #0891b2;">$1</span>`);

                    // Highlight variables
                    highlighted = highlighted.replace(/\b([a-zA-Z_]\w*)\b/g, (match) => {
                        if (!['true', 'false', 'null', 'and', 'or', 'not', 'in'].includes(match)) {
                            return `<span style="color: #059669;">${match}</span>`;
                        }
                        return match;
                    });

                    this.$refs.highlighter.innerHTML = highlighted;
                },

                escapeHtml(text) {
                    const map = {
                        '&': '&amp;',
                        '<': '&lt;',
                        '>': '&gt;',
                        '"': '&quot;',
                        "'": '&#039;'
                    };
                    return text.replace(/[&<>"']/g, m => map[m]);
                },

                clearEditor() {
                    this.expressionValue = '';
                    this.$refs.textarea.value = '';
                    this.error = '';
                    this.highlight();
                }
            };
        }
    </script>

    <style>
        /* Smooth transitions */
        [x-cloak] {
            display: none;
        }
    </style>
</div>
