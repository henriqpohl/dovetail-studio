/**
 * Virtual Assistant → Settings → Doors: live hint under the Service field of the "Add a door" form.
 *
 * The hint (data-dt-va-door-hint, JSON: steps, opens, exists, missing) says which step
 * the new door will open (Service + "1") and whether that step exists yet.
 */
( function () {
  'use strict';

  var hint = document.querySelector( '[data-dt-va-door-hint]' );
  var service = document.getElementById( 'dt-va-door-service' );
  var action = document.getElementById( 'dt-va-door-action' );

  if ( ! hint || ! service || ! action ) {
    return;
  }

  var texts = JSON.parse( hint.getAttribute( 'data-dt-va-door-hint' ) );
  var initial = hint.innerHTML;

  /**
   * Rewrite the hint for the current Service and Action, or restore the default text.
   *
   * @return {void}
   */
  function update() {
    var value = ( service.value || '' ).trim().toLowerCase();

    if ( action.value !== 'service' || ! value ) {
      hint.innerHTML = initial;

      return;
    }

    var step = value + '1';
    var code = document.createElement( 'code' );
    var parts = texts.opens.split( '%s' );

    code.textContent = step;
    hint.textContent = '';
    hint.appendChild( document.createTextNode( parts[ 0 ] ) );
    hint.appendChild( code );
    hint.appendChild(
      document.createTextNode(
        ( parts[ 1 ] || '' ) + ' ' + ( texts.steps.indexOf( step ) > -1 ? texts.exists : texts.missing )
      )
    );
  }

  service.addEventListener( 'input', update );
  action.addEventListener( 'change', update );
  update();
} )();
