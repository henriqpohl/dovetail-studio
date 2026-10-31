/**
 * Login Guard: reCAPTCHA v3 tokens for the login and registration forms.
 *
 * reCAPTCHA v3 has no widget. Every hidden field marked with
 * data-dovetail-lg-recaptcha gets a token when the page loads, and a fresh one
 * every 90 seconds, because tokens expire after two minutes.
 */
( function () {
  'use strict';

  var fields = document.querySelectorAll( '[data-dovetail-lg-recaptcha]' );

  if ( ! fields.length || ! window.grecaptcha ) {
    return;
  }

  /**
   * Request a token for one field and store it in the field.
   *
   * @param {HTMLInputElement} field Hidden token field with data-sitekey and the action name.
   * @return {void}
   */
  function refresh( field ) {
    window.grecaptcha
      .execute( field.getAttribute( 'data-sitekey' ), {
        action: field.getAttribute( 'data-dovetail-lg-recaptcha' ),
      } )
      .then(
        /**
         * Save the token.
         *
         * @param {string} token reCAPTCHA token.
         * @return {void}
         */
        function ( token ) {
          field.value = token;
        }
      );
  }

  /**
   * Refresh every field on the page.
   *
   * @return {void}
   */
  function refreshAll() {
    fields.forEach( refresh );
  }

  window.grecaptcha.ready(
    /**
     * Fetch the first tokens and keep them fresh.
     *
     * @return {void}
     */
    function () {
      refreshAll();
      window.setInterval( refreshAll, 90000 );
    }
  );
}() );
