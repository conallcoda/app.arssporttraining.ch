<div class="flex flex-col gap-1">
    @foreach ($members as $member)
        <flux:checkbox
            wire:model="selectedMembers"
            :label="$member['name']"
            :value="$member['id']"
        />
    @endforeach
</div>
