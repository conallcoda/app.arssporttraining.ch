@php
    $currentRoute = request()->route()->uri();
@endphp

<flux:navbar scrollable>
    <flux:navbar.item href="/athletes" :current="$currentRoute === 'athletes'">Athletes</flux:navbar.item>
    <flux:navbar.item href="/athletes/groups" :current="$currentRoute === 'athletes/groups'">Groups</flux:navbar.item>
</flux:navbar>
