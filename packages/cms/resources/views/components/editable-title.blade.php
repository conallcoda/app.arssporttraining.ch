@props(['name', 'wireMethod' => 'updateName'])

<div x-data="{
    editing: false,
    name: @js($name),
    originalName: @js($name),
    startEditing() {
        this.editing = true;
        this.$nextTick(() => this.$refs.input.select());
    },
    save() {
        this.editing = false;
        if (this.name.trim() && this.name !== this.originalName) {
            this.originalName = this.name;
            $wire.{{ $wireMethod }}(this.name);
        } else {
            this.name = this.originalName;
        }
    },
    cancel() {
        this.name = this.originalName;
        this.editing = false;
    }
}" class="mb-6 group">
    <div x-show="!editing" @click="startEditing()" class="flex items-center gap-2 cursor-pointer">
        <flux:heading size="xl" level="1" class="hover:text-zinc-600 dark:hover:text-zinc-300">
            <span x-text="name"></span>
        </flux:heading>
        <flux:icon.pencil class="w-5 h-5 text-zinc-400 opacity-0 group-hover:opacity-100 transition-opacity" />
    </div>
    <input x-show="editing" x-cloak x-ref="input" x-model="name" @keydown.enter.prevent="save()"
        @keydown.escape.prevent="cancel()" @blur="save()" type="text"
        class="text-2xl font-semibold bg-transparent border-b-2 border-zinc-300 dark:border-zinc-600 focus:border-blue-500 focus:outline-none w-full max-w-lg text-zinc-800 dark:text-white" />
</div>
