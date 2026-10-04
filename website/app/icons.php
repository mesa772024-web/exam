<?php
/**
 * مكتبة أيقونات SVG خطّية أنيقة — بديل الإيموجي.
 * الاستخدام: echo icon('calendar'); أو icon('phone', 'ic-lg')
 * كل الأيقونات تستخدم currentColor فترث لون النص.
 */
function icon(string $name, string $class = ''): string
{
    static $lib = null;
    if ($lib === null) $lib = icon_lib();
    $p = $lib[$name] ?? $lib['dot'];
    $cls = 'ic' . ($class ? ' ' . $class : '');
    return '<svg class="' . $cls . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" '
         . 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
}

function icon_lib(): array
{
    return [
        // ——— عام / واجهة ———
        'dot'        => '<circle cx="12" cy="12" r="3"/>',
        'arrow-l'    => '<path d="M19 12H5"/><path d="M12 19l-7-7 7-7"/>',
        'arrow-r'    => '<path d="M5 12h14"/><path d="M12 5l7 7-7 7"/>',
        'chevron-d'  => '<path d="M6 9l6 6 6-6"/>',
        'check'      => '<path d="M20 6L9 17l-5-5"/>',
        'check-circle'=> '<circle cx="12" cy="12" r="9"/><path d="M8.5 12.5l2.5 2.5 4.5-5"/>',
        'close'      => '<path d="M18 6L6 18"/><path d="M6 6l12 12"/>',
        'search'     => '<circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/>',
        'camera'     => '<path d="M4 7h3l1.5-2h7L17 7h3a2 2 0 012 2v9a2 2 0 01-2 2H4a2 2 0 01-2-2V9a2 2 0 012-2z"/><circle cx="12" cy="13" r="4"/>',
        'download'   => '<path d="M12 3v12"/><path d="M7 11l5 5 5-5"/><path d="M5 21h14"/>',
        'upload'     => '<path d="M12 21V9"/><path d="M7 13l5-5 5 5"/><path d="M5 3h14"/>',
        'plus'       => '<path d="M12 5v14"/><path d="M5 12h14"/>',
        'edit'       => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>',
        'trash'      => '<path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>',
        'copy'       => '<rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>',
        'menu'       => '<path d="M3 12h18"/><path d="M3 6h18"/><path d="M3 18h18"/>',
        'external'   => '<path d="M14 3h7v7"/><path d="M10 14L21 3"/><path d="M21 14v5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5"/>',
        'globe'      => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3a15 15 0 0 1 0 18a15 15 0 0 1 0-18"/>',
        'refresh'    => '<path d="M21 12a9 9 0 1 1-2.6-6.4"/><path d="M21 4v5h-5"/>',
        'eye'        => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>',
        'eye-off'    => '<path d="M9.9 5.2A9.6 9.6 0 0 1 12 5c6.5 0 10 7 10 7a15 15 0 0 1-3.3 4M6.6 6.6A15 15 0 0 0 2 12s3.5 7 10 7a9.6 9.6 0 0 0 4.1-.9"/><path d="M3 3l18 18"/>',
        'grid'       => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'save'       => '<path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2Z"/><path d="M17 21v-8H7v8"/><path d="M7 3v5h8"/>',
        'settings'   => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.6 1.6 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.6 1.6 0 0 0-2.7 1.1V21a2 2 0 0 1-4 0v-.1A1.6 1.6 0 0 0 7 19.4a1.6 1.6 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.6 1.6 0 0 0-1.1-2.7H1a2 2 0 0 1 0-4h.1A1.6 1.6 0 0 0 4.6 7a1.6 1.6 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.6 1.6 0 0 0 2.7-1.1V1a2 2 0 0 1 4 0v.1A1.6 1.6 0 0 0 17 4.6a1.6 1.6 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.6 1.6 0 0 0 1.1 2.7H23a2 2 0 0 1 0 4h-.1a1.6 1.6 0 0 0-1.5 1.1Z"/>',

        // ——— تواصل ———
        'phone'      => '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6 19.8 19.8 0 0 1-3.1-8.7A2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.3 1.8.6 2.7a2 2 0 0 1-.5 2.1L8 9.6a16 16 0 0 0 6 6l1.1-1.1a2 2 0 0 1 2.1-.5c.9.3 1.8.5 2.7.6a2 2 0 0 1 1.7 2Z"/>',
        'mail'       => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="M2 7l10 6 10-6"/>',
        'pin'        => '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/>',
        'calendar'   => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M3 10h18"/><path d="M8 2v4"/><path d="M16 2v4"/>',
        'clock'      => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'whatsapp'   => '<path d="M20.5 3.5A11 11 0 0 0 3.2 17L2 22l5.2-1.2A11 11 0 1 0 20.5 3.5Z"/><path d="M8.5 8c-.3 0-.7.1-1 .5-.3.4-1 1-1 2.3s1 2.7 1.2 2.9c.1.2 2 3.1 5 4.3 2.4 1 2.9.8 3.5.8.6-.1 1.8-.7 2-1.5.3-.7.3-1.4.2-1.5-.1-.1-.3-.2-.6-.4l-2-.9c-.3-.1-.5-.2-.7.1l-.9 1.1c-.2.2-.3.2-.6.1a8 8 0 0 1-2.4-1.5 9 9 0 0 1-1.6-2c-.2-.3 0-.4.1-.6l.5-.6c.1-.2.1-.3.2-.5s0-.4 0-.5l-.9-2c-.2-.6-.5-.5-.7-.5Z" fill="currentColor" stroke="none"/>',
        'facebook'   => '<path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3Z"/>',
        'instagram'  => '<rect x="2" y="2" width="20" height="20" rx="5.5"/><circle cx="12" cy="12" r="4.5"/><circle cx="17.5" cy="6.5" r="1.2" fill="currentColor" stroke="none"/>',
        'linkedin'   => '<rect x="2" y="2" width="20" height="20" rx="3"/><path d="M7 10v7M7 7v0M11 17v-4a2 2 0 0 1 4 0v4M11 10v7" stroke-width="2"/>',

        // ——— محتوى / بطاقات ———
        'target'     => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1.5" fill="currentColor" stroke="none"/>',
        'trending'   => '<path d="M22 7l-8.5 8.5-5-5L2 17"/><path d="M16 7h6v6"/>',
        'handshake'  => '<path d="M11 17l2 2a1.4 1.4 0 0 0 2-2"/><path d="M13 15l2.5 2.5a1.4 1.4 0 0 0 2-2L15 13"/><path d="M2 10l3-3 5 1 3-2"/><path d="M22 10l-3-3-3 1"/><path d="M2 10l4 4M22 10l-6 5-1-1"/>',
        'star'       => '<path d="M12 2l2.9 6.3 6.9.7-5.1 4.6 1.4 6.8L12 17.8 5.9 20.4l1.4-6.8L2.2 9l6.9-.7Z"/>',
        'mic'        => '<rect x="9" y="2" width="6" height="12" rx="3"/><path d="M5 11a7 7 0 0 0 14 0"/><path d="M12 18v3"/>',
        'users'      => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16 5.5a3.5 3.5 0 0 1 0 6.9"/><path d="M18 14a6.5 6.5 0 0 1 3.5 6"/>',
        'ticket'     => '<path d="M4 6a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3a2 2 0 0 0 0 4v3a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-3a2 2 0 0 0 0-4Z"/><path d="M14 4v16" stroke-dasharray="2 2"/>',
        'building'   => '<rect x="4" y="2" width="16" height="20" rx="1.5"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01M12 6h.01M16 6h.01M8 10h.01M12 10h.01M16 10h.01M8 14h.01M16 14h.01"/>',
        'doc'        => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/><path d="M8 13h8M8 17h8M8 9h2"/>',
        'file-pdf'   => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/><path d="M8 15h1.5a1.5 1.5 0 0 0 0-3H8v5M13 12v5h1a1.5 1.5 0 0 0 1.5-1.5v-2A1.5 1.5 0 0 0 14 12ZM18 12h-1.5v5M16.5 14.5H18"/>',
        'image'      => '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.8"/><path d="M21 15l-5-5L5 21"/>',
        'code'       => '<path d="M16 18l6-6-6-6"/><path d="M8 6l-6 6 6 6"/>',
        'layout'     => '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/>',
        'list'       => '<path d="M8 6h13M8 12h13M8 18h13"/><path d="M3.5 6h.01M3.5 12h.01M3.5 18h.01"/>',
        'shield'     => '<path d="M12 2l8 3v6c0 5-3.4 8.5-8 11-4.6-2.5-8-6-8-11V5Z"/><path d="M9 12l2 2 4-4"/>',
        'shield-alert'=> '<path d="M12 2l8 3v6c0 5-3.4 8.5-8 11-4.6-2.5-8-6-8-11V5Z"/><path d="M12 8v4M12 16h.01"/>',
        'lock'       => '<rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>',
        'database'   => '<ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v6c0 1.7 3.6 3 8 3s8-1.3 8-3V5"/><path d="M4 11v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6"/>',
        'badge-id'   => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2.2"/><path d="M5.5 16a3.5 3.5 0 0 1 7 0"/><path d="M15 9h4M15 12h4M15 15h2"/>',
        'qr'         => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3M20 14v.01M14 20h.01M17 20h.01M20 17v4"/>',
        'printer'    => '<path d="M6 9V3h12v6"/><rect x="4" y="9" width="16" height="8" rx="2"/><path d="M6 17h12v4H6z"/>',
        'chart'      => '<path d="M3 3v18h18"/><rect x="7" y="12" width="3" height="6"/><rect x="12" y="8" width="3" height="10"/><rect x="17" y="5" width="3" height="13"/>',
        'filter'     => '<path d="M3 4h18l-7 8v6l-4 2v-8Z"/>',
        'bolt'       => '<path d="M13 2L3 14h7l-1 8 10-12h-7Z"/>',
        'play'       => '<circle cx="12" cy="12" r="9"/><path d="M10 8l6 4-6 4Z" fill="currentColor" stroke="none"/>',

        // ——— القطاعات ———
        's-urban'    => '<path d="M3 21h18"/><path d="M5 21V7l6-4 6 4v14"/><path d="M9 9h.01M13 9h.01M9 13h.01M13 13h.01M9 17h.01M13 17h.01"/>',
        's-egov'     => '<rect x="3" y="4" width="18" height="14" rx="2"/><path d="M8 21h8M12 18v3"/><path d="M7 9l2 2-2 2M12 13h4"/>',
        's-invest'   => '<path d="M12 2v20M17 6H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>',
        's-fintech'  => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/><path d="M6 15h4"/>',
        's-waste'    => '<path d="M4 7h16"/><path d="M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/><path d="M6 7l1 13a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-13"/><path d="M10 11v6M14 11v6"/>',
        's-transport'=> '<rect x="3" y="4" width="18" height="12" rx="2"/><path d="M3 10h18"/><circle cx="7.5" cy="19" r="1.5"/><circle cx="16.5" cy="19" r="1.5"/><path d="M6 16v2M18 16v2"/>',
        's-housing'  => '<path d="M3 11l9-8 9 8"/><path d="M5 10v10h14V10"/><rect x="10" y="14" width="4" height="6"/>',
        's-univ'     => '<path d="M12 3L2 8l10 5 10-5Z"/><path d="M6 10v5c0 1.5 2.7 3 6 3s6-1.5 6-3v-5"/><path d="M22 8v6"/>',
        's-health'   => '<rect x="3" y="3" width="18" height="18" rx="3"/><path d="M12 8v8M8 12h8"/>',
        's-security' => '<path d="M12 2l8 3v6c0 5-3.4 8.5-8 11-4.6-2.5-8-6-8-11V5Z"/><circle cx="12" cy="11" r="2"/><path d="M12 13v3"/>',
        's-water'    => '<path d="M12 2s7 7.6 7 12a7 7 0 0 1-14 0c0-4.4 7-12 7-12Z"/><path d="M9 15a3 3 0 0 0 3 3"/>',
        's-build'    => '<path d="M3 21h18"/><path d="M5 21V11l7-5 7 5v10"/><path d="M12 6V3"/><path d="M9 21v-5h6v5"/>',
        's-energy'   => '<circle cx="12" cy="12" r="4"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M5 5l2 2M17 17l2 2M5 19l2-2M17 7l2-2"/>',
        's-telecom'  => '<path d="M5 12a7 7 0 0 1 14 0"/><path d="M8.5 12a3.5 3.5 0 0 1 7 0"/><circle cx="12" cy="12" r="1.4" fill="currentColor" stroke="none"/><path d="M12 13v8"/>',
        's-it'       => '<rect x="2" y="4" width="20" height="13" rx="2"/><path d="M8 21h8M12 17v4"/><path d="M8 10l2 2-2 2M13 14h3"/>',

        // ——— حالات ———
        'success'    => '<circle cx="12" cy="12" r="9"/><path d="M8.5 12.5l2.5 2.5 4.5-5"/>',
        'info'       => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>',
        'pause'      => '<circle cx="12" cy="12" r="9"/><path d="M10 9v6M14 9v6"/>',
    ];
}

/** أيقونة قطاع حسب الفهرس (بالترتيب المخزّن في الإعدادات) */
function sector_icon(int $i): string
{
    $order = ['s-urban','s-egov','s-invest','s-fintech','s-waste','s-transport','s-housing','s-univ','s-health','s-security','s-water','s-build','s-energy','s-telecom','s-it'];
    return $order[$i % count($order)];
}
