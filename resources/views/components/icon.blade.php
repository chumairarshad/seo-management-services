@props([
    'name' => 'dot',
    'stroke' => '1.75',
])

@php
    // Inline, build-time icon set. No runtime icon dependency, no font, no sprite fetch.
    $paths = [
        'home' => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V20a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1V9.5"/>',
        'projects' => '<circle cx="12" cy="12" r="9"/><path d="M3.6 9h16.8M3.6 15h16.8"/><path d="M12 3a15 15 0 0 1 0 18a15 15 0 0 1 0-18Z"/>',
        'tasks' => '<rect x="3" y="4" width="18" height="17" rx="2.5"/><path d="M8 3v3M16 3v3"/><path d="m8.5 13.5 2.2 2.2 4.3-4.3"/>',
        'articles' => '<path d="M5 3h9l5 5v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z"/><path d="M14 3v5h5"/><path d="M8 13h8M8 17h5"/>',
        'links' => '<path d="M10.5 13.5a4 4 0 0 0 5.7 0l2.6-2.6a4 4 0 0 0-5.7-5.7l-1.3 1.3"/><path d="M13.5 10.5a4 4 0 0 0-5.7 0l-2.6 2.6a4 4 0 1 0 5.7 5.7l1.3-1.3"/>',
        'approvals' => '<path d="M4 5.5A1.5 1.5 0 0 1 5.5 4h13A1.5 1.5 0 0 1 20 5.5V15h-4.2a1 1 0 0 0-.9.6l-.5 1a1 1 0 0 1-.9.6h-3a1 1 0 0 1-.9-.6l-.5-1a1 1 0 0 0-.9-.6H4Z"/><path d="M4 15v3.5A1.5 1.5 0 0 0 5.5 20h13a1.5 1.5 0 0 0 1.5-1.5V15"/><path d="m9.5 9 1.8 1.8L14.8 7"/>',
        'people' => '<circle cx="9" cy="8" r="3.2"/><path d="M3.5 20a5.5 5.5 0 0 1 11 0"/><path d="M16 5.2a3.2 3.2 0 0 1 0 6.1"/><path d="M17.4 14.4A5.5 5.5 0 0 1 20.5 20"/>',
        'attendance' => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 1.8"/>',
        'worklogs' => '<path d="M5 3.5h11.5L20 7v13.5H5z"/><path d="M8.5 8.5h6M8.5 12h6M8.5 15.5h3.5"/>',
        'scorecard' => '<path d="M12 3.5 14.4 9l5.6.6-4.2 3.9 1.2 5.6L12 16.3 7 19.1l1.2-5.6L4 9.6 9.6 9Z"/>',
        'history' => '<path d="M3.5 12a8.5 8.5 0 1 0 2.6-6.1"/><path d="M3.5 4.5V9H8"/><path d="M12 8v4.3l2.9 1.7"/>',
        'revenue' => '<rect x="2.5" y="6" width="19" height="12" rx="2"/><circle cx="12" cy="12" r="2.6"/><path d="M6 9.5v5M18 9.5v5"/>',
        'expenses' => '<path d="M6 3.5h12v17l-2.4-1.5-2.4 1.5-2.4-1.5L8.4 20.5 6 19Z"/><path d="M9.5 8h5M9.5 11.5h5M9.5 15h3"/>',
        'pnl' => '<path d="M4 20V4"/><path d="M4 20h16"/><path d="m7.5 15 3.4-4.2 2.8 2.2L19 7"/><path d="M19 10.6V7h-3.6"/>',
        'distributions' => '<circle cx="12" cy="5" r="2.4"/><circle cx="5" cy="19" r="2.4"/><circle cx="19" cy="19" r="2.4"/><path d="M12 7.4v4.2M12 11.6 6.6 17M12 11.6 17.4 17"/>',
        'partners' => '<path d="m10.5 15.5 1.9 1.9a1.6 1.6 0 0 0 2.3-2.3"/><path d="m8 12.5 4.4 4.4a1.6 1.6 0 0 0 2.3-2.3l-.8-.8"/><path d="M3.5 8.5 7 5.4a2 2 0 0 1 2.6 0l2.6 2.3-2 1.9a1.7 1.7 0 0 1-2.3 0"/><path d="M20.5 8.5 17 5.4a2 2 0 0 0-2.6 0l-.6.5"/><path d="M17 9.5 20.5 13M7 9.9 3.5 13"/>',
        'ai' => '<path d="m12 3 1.7 4.6L18.5 9l-4.8 1.4L12 15l-1.7-4.6L5.5 9l4.8-1.4Z"/><path d="m18 15 .8 2.2 2.2.8-2.2.8L18 21l-.8-2.2-2.2-.8 2.2-.8Z"/>',
        'users' => '<circle cx="12" cy="8" r="3.4"/><path d="M5 20a7 7 0 0 1 14 0"/>',
        'settings' => '<path d="M4 7h10M18 7h2M4 17h4M12 17h8"/><circle cx="16" cy="7" r="2.2"/><circle cx="10" cy="17" r="2.2"/>',
        'templates' => '<rect x="3.5" y="3.5" width="17" height="6" rx="1.6"/><rect x="3.5" y="13" width="7.5" height="7.5" rx="1.6"/><rect x="14" y="13" width="6.5" height="7.5" rx="1.6"/>',
        'credentials' => '<circle cx="8.5" cy="12" r="4"/><path d="M12.5 12H21v3"/><path d="M17.5 12v2.5"/>',
        'search' => '<circle cx="11" cy="11" r="6.5"/><path d="m16 16 4.5 4.5"/>',
        'plus' => '<path d="M12 5.5v13M5.5 12h13"/>',
        'bell' => '<path d="M6.5 10a5.5 5.5 0 0 1 11 0c0 4 1.5 5.5 1.5 5.5H5S6.5 14 6.5 10Z"/><path d="M10.2 19a2 2 0 0 0 3.6 0"/>',
        'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2.5v2M12 19.5v2M2.5 12h2M19.5 12h2M5.2 5.2l1.4 1.4M17.4 17.4l1.4 1.4M18.8 5.2l-1.4 1.4M6.6 17.4l-1.4 1.4"/>',
        'moon' => '<path d="M20 14.2A8.2 8.2 0 0 1 9.8 4 8.4 8.4 0 1 0 20 14.2Z"/>',
        'monitor' => '<rect x="2.5" y="4" width="19" height="13" rx="2"/><path d="M8.5 21h7M12 17v4"/>',
        'chevron-down' => '<path d="m6 9.5 6 6 6-6"/>',
        'chevron-right' => '<path d="m9.5 6 6 6-6 6"/>',
        'chevron-left' => '<path d="m14.5 6-6 6 6 6"/>',
        'chevron-up-down' => '<path d="m8 10 4-4 4 4M8 14l4 4 4-4"/>',
        'arrow-right' => '<path d="M4.5 12h15M13.5 6l6 6-6 6"/>',
        'arrow-up' => '<path d="M12 19.5v-15M6 10.5 12 4.5l6 6"/>',
        'arrow-down' => '<path d="M12 4.5v15M6 13.5l6 6 6-6"/>',
        'external' => '<path d="M14 4.5h5.5V10"/><path d="m19 5-8 8"/><path d="M18 14.5v4a1.5 1.5 0 0 1-1.5 1.5h-11A1.5 1.5 0 0 1 4 18.5v-11A1.5 1.5 0 0 1 5.5 6h4"/>',
        'x' => '<path d="m6 6 12 12M18 6 6 18"/>',
        'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'panel-left' => '<rect x="3.5" y="4" width="17" height="16" rx="2.2"/><path d="M9.5 4v16"/>',
        'check' => '<path d="m5 12.5 4.5 4.5L19 7"/>',
        'check-circle' => '<circle cx="12" cy="12" r="8.5"/><path d="m8.5 12.2 2.4 2.4 4.6-5"/>',
        'alert' => '<path d="M12 4.5 21 19.5H3Z"/><path d="M12 10v4M12 16.6v.4"/>',
        'info' => '<circle cx="12" cy="12" r="8.5"/><path d="M12 11v5M12 8v.4"/>',
        'trash' => '<path d="M4.5 7h15"/><path d="M9.5 7V5.5A1.5 1.5 0 0 1 11 4h2a1.5 1.5 0 0 1 1.5 1.5V7"/><path d="M6.5 7 7.4 19a1.5 1.5 0 0 0 1.5 1.4h6.2a1.5 1.5 0 0 0 1.5-1.4L17.5 7"/>',
        'pencil' => '<path d="M4.5 19.5h4l10-10a2.1 2.1 0 0 0-3-3l-10 10Z"/><path d="m14 6.5 3 3"/>',
        'download' => '<path d="M12 4v11M7.5 10.5 12 15l4.5-4.5"/><path d="M4.5 19.5h15"/>',
        'upload' => '<path d="M12 15.5v-11M7.5 9 12 4.5 16.5 9"/><path d="M4.5 19.5h15"/>',
        'filter' => '<path d="M4 6h16l-6.2 7v5.5l-3.6 2V13Z"/>',
        'dots' => '<circle cx="6" cy="12" r="1.4"/><circle cx="12" cy="12" r="1.4"/><circle cx="18" cy="12" r="1.4"/>',
        'command' => '<path d="M7.5 4.5A2.5 2.5 0 1 0 10 7v10a2.5 2.5 0 1 1-2.5-2.5h9A2.5 2.5 0 1 1 14 17V7a2.5 2.5 0 1 0 2.5 2.5h-9Z"/>',
        'calendar' => '<rect x="3.5" y="5" width="17" height="16" rx="2"/><path d="M8 3v4M16 3v4M3.5 10h17"/>',
        'eye' => '<path d="M2.5 12S6 6 12 6s9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.8"/>',
        'eye-off' => '<path d="M4 4.5 20 20.5"/><path d="M9.5 9.7A2.8 2.8 0 0 0 12 14.8"/><path d="M6.4 7.3C4 8.9 2.5 12 2.5 12S6 18 12 18a9.8 9.8 0 0 0 4-.9"/><path d="M18.6 15.2c1.9-1.6 2.9-3.2 2.9-3.2S18 6 12 6a8.6 8.6 0 0 0-1.6.2"/>',
        'copy' => '<rect x="8.5" y="8.5" width="12" height="12" rx="2"/><path d="M15.5 5.5A2 2 0 0 0 13.5 3.5h-8a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2"/>',
        'sort' => '<path d="M7 5.5v13M7 5.5 4.5 8M7 5.5 9.5 8"/><path d="M17 18.5v-13M17 18.5 14.5 16M17 18.5l2.5-2.5"/>',
        'refresh' => '<path d="M20 12a8 8 0 1 1-2.6-5.9"/><path d="M20.5 4v4.5H16"/>',
        'clock' => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7v5.2l3.4 2"/>',
        'inbox' => '<path d="M3.5 13.5 6 5.6A2 2 0 0 1 7.9 4.2h8.2A2 2 0 0 1 18 5.6l2.5 7.9"/><path d="M3.5 13.5h4.2l1 2.4h6.6l1-2.4h4.2v4.8a1.7 1.7 0 0 1-1.7 1.7H5.2a1.7 1.7 0 0 1-1.7-1.7Z"/>',
        'target' => '<circle cx="12" cy="12" r="8.5"/><circle cx="12" cy="12" r="4.5"/><circle cx="12" cy="12" r="1"/>',
        'dot' => '<circle cx="12" cy="12" r="3.5"/>',
    ];

    $path = $paths[$name] ?? $paths['dot'];
@endphp

<svg
    {{ $attributes->merge(['class' => 'size-4 shrink-0']) }}
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="{{ $stroke }}"
    stroke-linecap="round"
    stroke-linejoin="round"
    aria-hidden="true"
    focusable="false"
>{!! $path !!}</svg>
