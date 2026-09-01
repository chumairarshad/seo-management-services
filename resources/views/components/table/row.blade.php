@props([
    'selected' => false,
])

<tr {{ $attributes->merge(['class' => 'group/row '.($selected ? '[&>td]:bg-accent-soft/60' : '')]) }} data-list-row>
    {{ $slot }}
</tr>
