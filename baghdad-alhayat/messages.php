<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$locale = current_locale();
$isAr = $locale === 'ar';

$sent = false;
$error = '';
$old = ['full_name' => '', 'email' => '', 'phone' => '', 'subject' => '', 'message' => ''];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        verify_csrf($_POST['csrf_token'] ?? null);
        enforce_rate_limit('speakup', 8, 600);
        // Honeypot — silently accept bots without saving.
        if (clean_text($_POST['website'] ?? '', 200) !== '') {
            header('Location: ' . site_url('messages.php?lang=' . $locale . '&sent=1'));
            exit;
        }
        $message = clean_multiline($_POST['message'] ?? '', 4000);
        $old['message'] = $message;

        if (mb_strlen($message) < 3) {
            throw new RuntimeException($isAr ? 'يرجى كتابة رسالتك.' : 'Please write your message.');
        }
        db()->prepare('INSERT INTO contact_messages(id, full_name, email, phone, subject, message) VALUES(:id, :full_name, :email, :phone, :subject, :message)')
            ->execute([
                'id' => uuid_v4(),
                'full_name' => $isAr ? 'مجهول' : 'Anonymous',
                'email' => '',
                'phone' => null,
                'subject' => 'Speak Up',
                'message' => $message,
            ]);
        header('Location: ' . site_url('messages.php?lang=' . $locale . '&sent=1'));
        exit;
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
    } catch (Throwable) {
        $error = $isAr ? 'الخدمة غير متاحة مؤقتاً، حاول لاحقاً.' : 'The service is temporarily unavailable. Please try again later.';
    }
}

if (isset($_GET['sent'])) {
    $sent = true;
}

require APP_ROOT . '/includes/v2-layout.php';
render_v2_head($locale, $isAr ? 'أسمِعنا صوتك' : 'Speak Up', $isAr ? 'أرسل رسالتك مباشرة إلى مكتب بغداد الحياة العلمي.' : 'Send your message directly to Baghdad Al Hayat Scientific Office.', 'messages');
render_v2_nav($locale);
?>
<style>
  .speakup{padding:clamp(96px,12vw,150px) 0 clamp(60px,8vw,110px);background:linear-gradient(180deg,var(--soft,#f7f2f5),#ffffff 58%)}
  .speakup-wrap{max-width:720px;margin:0 auto;padding:0 clamp(18px,5vw,40px)}
  .speakup-head{text-align:center;margin-bottom:clamp(26px,4vw,40px)}
  .speakup-eyebrow{display:inline-block;font:700 .72rem/1 var(--latin,'Inter',sans-serif);letter-spacing:.14em;text-transform:uppercase;color:var(--brand,#7a1440);background:rgba(122,20,64,.08);padding:8px 16px;border-radius:999px;margin-bottom:18px}
  .speakup-head h1{font-size:clamp(2rem,5vw,3.1rem);line-height:1.2;margin:0 0 14px;color:#240e17;font-weight:800;letter-spacing:-.02em}
  .speakup-head p{margin:0;color:#725c65;font-size:clamp(.95rem,1.2vw,1.08rem);line-height:1.8}
  .speakup-card{background:#fff;border:1px solid #ede3e8;border-radius:clamp(20px,2.4vw,30px);padding:clamp(24px,4vw,44px);box-shadow:0 24px 60px rgba(74,15,39,.10)}
  .speakup-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
  .speakup-field{display:flex;flex-direction:column;gap:8px}
  .speakup-field.full{grid-column:1/-1}
  .speakup-field label{font-size:.82rem;font-weight:700;color:#362029}
  .speakup-field .opt{font-weight:500;color:#a18a94;font-size:.74rem}
  .speakup-field input,.speakup-field textarea{width:100%;font:inherit;font-size:.95rem;color:#240e17;background:#fbf7f9;border:1px solid #e5d5dc;border-radius:14px;padding:13px 15px;transition:border-color .2s,box-shadow .2s}
  .speakup-field input::placeholder,.speakup-field textarea::placeholder{color:#b39da7}
  .speakup-field input:focus,.speakup-field textarea:focus{outline:none;border-color:var(--brand,#7a1440);box-shadow:0 0 0 3px rgba(122,20,64,.14);background:#fff}
  .speakup-field textarea{resize:vertical;min-height:150px;line-height:1.7}
  .speakup-actions{display:flex;align-items:center;gap:16px;flex-wrap:wrap;margin-top:22px}
  .speakup-btn{appearance:none;border:0;cursor:pointer;background:linear-gradient(135deg,#9e1e56,#691237);color:#fff;font:800 1rem var(--latin,'Inter',sans-serif);padding:14px 30px;border-radius:999px;box-shadow:0 14px 30px rgba(105,18,55,.28);transition:transform .2s,box-shadow .2s}
  .speakup-btn:hover{transform:translateY(-2px);box-shadow:0 18px 38px rgba(105,18,55,.34)}
  .speakup-hint{color:#a18a94;font-size:.8rem;margin:0}
  .speakup-alert{border-radius:16px;padding:16px 20px;margin-bottom:24px;font-size:.92rem;line-height:1.7;display:flex;gap:12px;align-items:flex-start}
  .speakup-alert.ok{background:#f5e7ee;color:#691237;border:1px solid #e6bfd1}
  .speakup-alert.err{background:#fbeef0;color:#9c2b3e;border:1px solid #f0cdd4}
  .speakup-alert b{font-weight:800}
  @media(max-width:640px){.speakup-grid{grid-template-columns:1fr}}
</style>
<main class="speakup">
  <div class="speakup-wrap">
    <div class="speakup-head">
      <span class="speakup-eyebrow">Speak Up</span>
      <h1><?= $isAr ? 'أسمِعنا صوتك' : 'Speak Up' ?></h1>
      <p><?= $isAr ? 'اكتب رسالتك وستصل مباشرة إلى مكتب بغداد الحياة العلمي.' : 'Write your message and it goes straight to Baghdad Al Hayat Scientific Office.' ?></p>
    </div>

    <?php if ($sent): ?>
      <div class="speakup-alert ok" role="status"><span aria-hidden="true">✓</span><span><b><?= $isAr ? 'تم استلام رسالتك.' : 'Your message was received.' ?></b><br><?= $isAr ? 'شكراً لتواصلك — سيطّلع عليها فريق المكتب.' : 'Thank you for reaching out — the office team will review it.' ?></span></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
      <div class="speakup-alert err" role="alert"><span aria-hidden="true">!</span><span><?= h($error) ?></span></div>
    <?php endif; ?>

    <div class="speakup-card">
      <form method="post" action="<?= h(site_url('messages.php?lang=' . $locale)) ?>" novalidate>
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <div class="speakup-hp" aria-hidden="true" style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden"><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div>
        <div class="speakup-grid">
          <div class="speakup-field full">
            <label for="su-message"><?= $isAr ? 'رسالتك' : 'Your message' ?></label>
            <textarea id="su-message" name="message" maxlength="4000" required placeholder="<?= $isAr ? 'اكتب رسالتك هنا…' : 'Write your message here…' ?>"><?= h($old['message']) ?></textarea>
          </div>
        </div>
        <div class="speakup-actions">
          <button class="speakup-btn" type="submit"><?= $isAr ? 'أرسِل رسالتك' : 'Send message' ?></button>
        </div>
      </form>
    </div>
  </div>
</main>
<?php render_v2_footer($locale); ?>
