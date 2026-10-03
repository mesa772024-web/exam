(() => {
  const menuButton = document.querySelector('.mobile-menu-button');
  const mobileNav = document.querySelector('#mobile-nav');
  if (menuButton && mobileNav) {
    menuButton.addEventListener('click', () => {
      const open = menuButton.getAttribute('aria-expanded') === 'true';
      menuButton.setAttribute('aria-expanded', String(!open));
      mobileNav.hidden = open;
    });
    mobileNav.addEventListener('click', (event) => {
      if (event.target instanceof HTMLAnchorElement) {
        menuButton.setAttribute('aria-expanded', 'false');
        mobileNav.hidden = true;
      }
    });
  }

  document.querySelectorAll('[data-ajax-form]').forEach((form) => {
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      const button = form.querySelector('button[type="submit"]');
      const status = form.querySelector('.form-status');
      if (button) button.disabled = true;
      if (status) status.textContent = '';
      try {
        const response = await fetch(form.action, {
          method: 'POST',
          body: new FormData(form),
          headers: { Accept: 'application/json' },
          credentials: 'same-origin',
        });
        const result = await response.json();
        if (!response.ok) throw new Error(result.error || 'تعذر إرسال الطلب.');
        if (status) status.textContent = result.message || 'تم الإرسال بنجاح.';
        form.reset();
      } catch (error) {
        if (status) status.textContent = error instanceof Error ? error.message : 'تعذر إرسال الطلب.';
      } finally {
        if (button) button.disabled = false;
      }
    });
  });
})();
