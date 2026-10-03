(() => {
  'use strict';
  const app = document.querySelector('[data-chat-app]');
  if (!app) return;
  const api = app.dataset.api;
  const statusText = (form, message, error = false) => {
    const status = form.querySelector('[data-form-status]');
    if (!status) return;
    status.textContent = message || '';
    status.classList.toggle('error', error);
  };
  const submit = async (form) => {
    statusText(form, document.documentElement.lang === 'ar' ? 'جارٍ الإرسال…' : 'Sending…');
    const button = form.querySelector('[type="submit"]');
    if (button) button.disabled = true;
    try {
      const response = await fetch(api, { method: 'POST', body: new FormData(form), credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      const result = await response.json();
      if (!response.ok || !result.ok) throw new Error(result.message || 'Request failed');
      statusText(form, result.message || 'Done');
      if (result.reload) location.reload();
    } catch (error) {
      statusText(form, error.message, true);
    } finally {
      if (button) button.disabled = false;
    }
  };
  document.querySelector('[data-start-chat]')?.addEventListener('submit', (event) => { event.preventDefault(); submit(event.currentTarget); });
  const messageForm = document.querySelector('[data-send-message]');
  messageForm?.addEventListener('submit', (event) => { event.preventDefault(); submit(event.currentTarget); });
  const log = document.querySelector('[data-chat-log]');
  if (log) log.scrollTop = log.scrollHeight;
  document.querySelector('[data-refresh]')?.addEventListener('click', () => location.reload());
  const fileInput = document.querySelector('[data-file-input]');
  fileInput?.addEventListener('change', () => {
    const file = fileInput.files?.[0];
    const purpose = document.querySelector('[data-file-purpose]');
    if (file && purpose) purpose.value = file.type.startsWith('image/') ? 'image' : 'file';
    if (file && messageForm) statusText(messageForm, fileInput.files.length > 1 ? `${fileInput.files.length} files selected` : file.name);
  });

  const recordButton = document.querySelector('[data-record-audio]');
  let recorder = null;
  let chunks = [];
  recordButton?.addEventListener('click', async () => {
    if (recorder?.state === 'recording') {
      recorder.stop();
      return;
    }
    try {
      const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
      const preferred = MediaRecorder.isTypeSupported('audio/webm;codecs=opus') ? 'audio/webm;codecs=opus' : 'audio/webm';
      recorder = new MediaRecorder(stream, { mimeType: preferred });
      chunks = [];
      recorder.addEventListener('dataavailable', (event) => { if (event.data.size) chunks.push(event.data); });
      recorder.addEventListener('stop', async () => {
        stream.getTracks().forEach((track) => track.stop());
        recordButton.classList.remove('recording');
        const blob = new Blob(chunks, { type: 'audio/webm' });
        const formData = new FormData(messageForm);
        formData.set('purpose', 'audio');
        formData.set('attachment', blob, `voice-${Date.now()}.webm`);
        statusText(messageForm, document.documentElement.lang === 'ar' ? 'جارٍ إرسال الرسالة الصوتية…' : 'Sending voice message…');
        try {
          const response = await fetch(api, { method: 'POST', body: formData, credentials: 'same-origin' });
          const result = await response.json();
          if (!response.ok || !result.ok) throw new Error(result.message || 'Request failed');
          location.reload();
        } catch (error) { statusText(messageForm, error.message, true); }
      });
      recorder.start();
      recordButton.classList.add('recording');
      statusText(messageForm, document.documentElement.lang === 'ar' ? 'يتم التسجيل… اضغط مرة أخرى للإرسال.' : 'Recording… press again to send.');
    } catch (error) {
      statusText(messageForm, document.documentElement.lang === 'ar' ? 'تعذر الوصول إلى الميكروفون.' : 'Microphone access was unavailable.', true);
    }
  });

  const dialog = document.querySelector('[data-booking-dialog]');
  document.querySelector('[data-booking-open]')?.addEventListener('click', () => dialog?.showModal());
  document.querySelector('[data-booking-close]')?.addEventListener('click', () => dialog?.close());
  document.querySelector('[data-booking-form]')?.addEventListener('submit', async (event) => {
    event.preventDefault();
    await submit(event.currentTarget);
  });
})();
