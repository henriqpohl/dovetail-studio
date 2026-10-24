(() => {
  const mode = document.getElementById('dovetail-mode');
  const confirm = document.getElementById('dovetail-confirm');
  const mailchimp = document.getElementById('dovetail-mailchimp');
  const cf7 = document.getElementById('dovetail-cf7');
  const submit = document.getElementById('submit');
  if (!mode || !confirm || !mailchimp || !cf7 || !submit) return;
  function reveal(confirmed) {
    const value = confirmed ? mode.value : '';
    mailchimp.hidden = mailchimp.disabled = !['mailchimp', 'both'].includes(value);
    cf7.hidden = cf7.disabled = !['cf7', 'mailchimp', 'both'].includes(value);
    submit.disabled = !value;
  }
  mode.addEventListener('change', () => reveal(false));
  confirm.addEventListener('click', () => reveal(true));
  reveal(Boolean(mode.value));
})();
