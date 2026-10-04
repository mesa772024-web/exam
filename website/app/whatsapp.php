<?php
/**
 * إرسال واتساب — يدوي (wa.me) أو عبر API.
 * يدعم UltraMsg (الأكثر شيوعاً) وصيغة مخصّصة عامة.
 * يُرسل نص الرسالة مع صورة الباركود (إن فُعّلت).
 */
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/badge.php';

/** نص رسالة القبول لمسجّل (يستبدل {name} و {code}) */
function wa_message_for(array $r): string
{
    $tpl = setting('wa_template_' . (($r['lang'] ?? 'ar') === 'en' ? 'en' : 'ar'), setting('wa_template_ar', ''));
    return str_replace(['{name}', '{code}'], [$r['full_name'], $r['code']], $tpl);
}

/** هل إعدادات API مكتملة؟ */
function wa_api_ready(): bool
{
    $p = setting('wa_api_provider', '');
    if ($p === 'meta') {
        return setting('wa_api_instance', '') !== '' && setting('wa_api_token', '') !== '';
    }
    if ($p === 'ultramsg') {
        return setting('wa_api_instance', '') !== '' && setting('wa_api_token', '') !== '';
    }
    if ($p === 'custom') {
        return setting('wa_api_url', '') !== '' && setting('wa_api_token', '') !== '';
    }
    return false;
}

/** Meta is an independent send option; the manual wa.me button never depends on this. */
function wa_meta_ready(): bool
{
    return setting('wa_api_instance', '') !== '' && setting('wa_api_token', '') !== '';
}

/** POST بصيغة JSON مع ترويسة Bearer (لـ Meta Graph API) */
function wa_http_post_json(string $url, array $body, string $bearer, int $timeout = 15): array
{
    $json = json_encode($body, JSON_UNESCAPED_UNICODE);
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $bearer],
        ]);
        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['ok' => $code >= 200 && $code < 300, 'code' => $code, 'body' => $res];
    }
    $ctx = stream_context_create(['http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\nAuthorization: Bearer " . $bearer . "\r\n",
        'content' => $json,
        'timeout' => $timeout,
        'ignore_errors' => true,
    ]]);
    $res = @file_get_contents($url, false, $ctx);
    return ['ok' => $res !== false, 'code' => $res !== false ? 200 : 0, 'body' => (string)$res];
}

/** إرسال عبر Meta WhatsApp Cloud API — نص + صورة الباركود للزبون */
function wa_send_meta(array $r): array
{
    $phoneId = setting('wa_api_instance', '');   // Phone Number ID
    $token   = setting('wa_api_token', '');       // Permanent access token
    $to = preg_replace('/[^0-9]/', '', (string)$r['phone']);
    if ($phoneId === '' || $token === '' || $to === '') return ['ok' => false, 'msg' => 'إعدادات Meta ناقصة'];
    $url = 'https://graph.facebook.com/v21.0/' . rawurlencode($phoneId) . '/messages';
    $body = wa_message_for($r);
    $sendBarcode = setting('wa_send_barcode', '1') === '1';
    $imgUrl = qr_img_url((int)$r['id']);

    // رسالة نصية (تعمل ضمن نافذة 24 ساعة أو مع قالب معتمد)
    $t = wa_http_post_json($url, [
        'messaging_product' => 'whatsapp',
        'to' => $to,
        'type' => 'text',
        'text' => ['body' => $body],
    ], $token);

    // صورة الباركود
    if ($sendBarcode) {
        wa_http_post_json($url, [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'image',
            'image' => ['link' => $imgUrl, 'caption' => 'رمز الدخول: ' . $r['code']],
        ], $token);
    }

    if ($t['ok']) return ['ok' => true, 'msg' => 'أُرسلت عبر Meta'];
    $err = '';
    $j = json_decode((string)$t['body'], true);
    if (isset($j['error']['message'])) $err = ' — ' . mb_substr($j['error']['message'], 0, 80);
    return ['ok' => false, 'msg' => 'فشل Meta (' . $t['code'] . ')' . $err];
}

function wa_http_post(string $url, array $fields, int $timeout = 15): array
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($fields),
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        return ['ok' => $code >= 200 && $code < 300, 'code' => $code, 'body' => $res, 'err' => $err];
    }
    // fallback stream
    $ctx = stream_context_create(['http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
        'content' => http_build_query($fields),
        'timeout' => $timeout,
    ]]);
    $res = @file_get_contents($url, false, $ctx);
    return ['ok' => $res !== false, 'code' => $res !== false ? 200 : 0, 'body' => (string)$res, 'err' => ''];
}

/**
 * إرسال رسالة القبول + الباركود لمسجّل عبر API.
 * @return array{ok:bool,msg:string}
 */
function wa_send_api(array $r): array
{
    if (!wa_api_ready()) return ['ok' => false, 'msg' => 'إعدادات واتساب API غير مكتملة'];

    $to = preg_replace('/[^0-9]/', '', (string)$r['phone']);
    if ($to === '') return ['ok' => false, 'msg' => 'رقم غير صالح'];
    $body = wa_message_for($r);
    $sendBarcode = setting('wa_send_barcode', '1') === '1';
    $imgUrl = qr_img_url((int)$r['id']);
    $provider = setting('wa_api_provider', '');

    if ($provider === 'meta') {
        return wa_send_meta($r);
    }

    if ($provider === 'ultramsg') {
        $instance = setting('wa_api_instance', '');
        $token = setting('wa_api_token', '');
        $base = 'https://api.ultramsg.com/' . rawurlencode($instance);
        // نص
        $t = wa_http_post($base . '/messages/chat', ['token' => $token, 'to' => $to, 'body' => $body]);
        // صورة الباركود مع تعليق
        if ($sendBarcode) {
            wa_http_post($base . '/messages/image', ['token' => $token, 'to' => $to, 'image' => $imgUrl,
                'caption' => 'رمز الدخول: ' . $r['code']]);
        }
        return $t['ok'] ? ['ok' => true, 'msg' => 'أُرسلت عبر UltraMsg'] : ['ok' => false, 'msg' => 'فشل UltraMsg (' . $t['code'] . ')'];
    }

    if ($provider === 'custom') {
        $url = setting('wa_api_url', '');
        $token = setting('wa_api_token', '');
        $fields = ['token' => $token, 'to' => $to, 'message' => $body];
        if ($sendBarcode) $fields['image'] = $imgUrl;
        $sender = setting('wa_api_sender', '');
        if ($sender !== '') $fields['from'] = $sender;
        $res = wa_http_post($url, $fields);
        return $res['ok'] ? ['ok' => true, 'msg' => 'أُرسلت'] : ['ok' => false, 'msg' => 'فشل الإرسال (' . $res['code'] . ')'];
    }

    return ['ok' => false, 'msg' => 'مزوّد غير مدعوم'];
}
