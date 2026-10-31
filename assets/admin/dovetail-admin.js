/**
 * Dovetail Studio admin screens: shared behaviour.
 *
 * Links and buttons marked with data-dovetail-confirm="Message" ask for
 * confirmation before they run; cancelling stops the click.
 */
( function () {
  'use strict';

  /**
   * Ask before following a link or submitting a button that carries data-dovetail-confirm.
   *
   * @param {MouseEvent} event Click on the document.
   * @return {void}
   */
  function confirmClick( event ) {
    var target = event.target instanceof Element ? event.target.closest( '[data-dovetail-confirm]' ) : null;

    if ( target && ! window.confirm( target.getAttribute( 'data-dovetail-confirm' ) ) ) {
      event.preventDefault();
    }
  }

  document.addEventListener( 'click', confirmClick );
} )();
