<?php

/** Doors (the assistant's initial menu) and Step questions (the guided questions after a door is picked). */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/setup.php';
require_once __DIR__ . '/json-tools.php';

function dt_va_flow_door_actions() {
	return array( 'service', 'other', 'human' );
}

/** Any plain id-shaped string is accepted (not a fixed business-specific list), so a door's service can name anything relevant to the installation (e.g. "web", "seo"), not just the built-in enquiry/client. */
function dt_va_flow_service_valid( $service ) {
	return is_string( $service ) && preg_match( '/^[a-z0-9_-]{1,40}$/', $service );
}

/** Starting point shown in the admin textarea and used as a scaffold; front.js keeps its own literal default in sync separately. Generic on purpose — no business-specific service names, since this is what a fresh install falls back to before anyone configures doors. */
function dt_va_flow_doors_default() {
	return array(
		array(
			'id'      => 'enquiry',
			'label'   => __( 'Enquire about a product or service', 'dovetail-studio' ),
			'sub'     => __( "Tell us what you're looking for", 'dovetail-studio' ),
			'icon'    => 'logo',
			'action'  => 'service',
			'service' => 'enquiry',
		),
		array(
			'id'      => 'client',
			'label'   => __( "I'm already a client", 'dovetail-studio' ),
			'sub'     => __( 'Changes, updates or an account query', 'dovetail-studio' ),
			'icon'    => 'client',
			'action'  => 'service',
			'service' => 'client',
		),
		array(
			'id'     => 'other',
			'label'  => __( 'Something else', 'dovetail-studio' ),
			'sub'    => __( 'Or just ask me a question', 'dovetail-studio' ),
			'icon'   => 'other',
			'action' => 'other',
		),
		array(
			'id'     => 'human',
			'label'  => __( "I'd rather talk to a person", 'dovetail-studio' ),
			'sub'    => __( 'Call us or ask for a call back', 'dovetail-studio' ),
			'icon'   => 'human',
			'action' => 'human',
		),
	);
}

/** Returns a validated array on success, or a WP_Error describing the first problem found. Never trusts the shape of decoded JSON. */
function dt_va_flow_doors_validate( $json ) {
	$decoded = json_decode( (string) $json, true );

	if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) ) {
		return new WP_Error( 'flow', __( 'Not valid JSON, or not a JSON array.', 'dovetail-studio' ) );
	}

	if ( count( $decoded ) < 1 || count( $decoded ) > 12 ) {
		return new WP_Error( 'flow', __( 'Provide between 1 and 12 doors.', 'dovetail-studio' ) );
	}

	$actions = dt_va_flow_door_actions();
	$out     = array();

	foreach ( $decoded as $i => $door ) {
		if ( ! is_array( $door ) ) {
			return new WP_Error(
				'flow',
				sprintf(
					/* translators: %d: door number. */
					__( 'Door %d is not an object.', 'dovetail-studio' ),
					$i + 1
				)
			);
		}

		$id    = isset( $door['id'] ) && is_string( $door['id'] ) ? trim( $door['id'] ) : '';
		$label = isset( $door['label'] ) && is_string( $door['label'] ) ? trim( $door['label'] ) : '';

		if ( '' === $id || '' === $label ) {
			return new WP_Error(
				'flow',
				sprintf(
					/* translators: %d: door number. */
					__( 'Door %d needs an id and a label.', 'dovetail-studio' ),
					$i + 1
				)
			);
		}

		$action = isset( $door['action'] ) && in_array( $door['action'], $actions, true )
			? $door['action']
			: '';

		if ( '' === $action ) {
			return new WP_Error(
				'flow',
				sprintf(
					/* translators: 1: door number, 2: list of actions. */
					__( 'Door %1$d: action must be one of %2$s.', 'dovetail-studio' ),
					$i + 1,
					implode( ', ', $actions )
				)
			);
		}

		$row = array(
			'id'     => mb_substr( $id, 0, 40 ),
			'label'  => mb_substr( $label, 0, 80 ),
			'action' => $action,
		);

		if ( 'service' === $action ) {
			$service = isset( $door['service'] ) && dt_va_flow_service_valid( $door['service'] )
				? $door['service']
				: '';

			if ( '' === $service ) {
				return new WP_Error(
					'flow',
					sprintf(
						/* translators: %d: door number. */
						__( 'Door %d: service must be a lowercase id (letters, numbers, - or _, max 40 characters).', 'dovetail-studio' ),
						$i + 1
					)
				);
			}

			$row['service'] = $service;
		}

		foreach ( array( 'sub', 'short', 'icon' ) as $optional ) {
			if ( isset( $door[ $optional ] )
				&& is_string( $door[ $optional ] )
				&& '' !== trim( $door[ $optional ] )
			) {
				$row[ $optional ] = mb_substr( trim( $door[ $optional ] ), 0, 80 );
			}
		}

		$out[] = $row;
	}

	return $out;
}

function dt_va_flow_doors_config() {
	$saved = get_option( 'dt_va_flow_doors', array() );

	return is_array( $saved ) && $saved ? $saved : array();
}

/* ================= STEPS =================
	The questions asked after a door is picked. Only choice/text steps are configurable; the
	fixed pipeline (work/money/timing/capture) keeps its own internal chaining in front.js and
	can only be referenced as a target, never redefined. A step not overridden here keeps using
	the built-in front.js behaviour for that id, so a partial override is safe by default. */
function dt_va_flow_step_types() {
	return array( 'choice', 'text' );
}

function dt_va_flow_condition_ops() {
	return array( 'answerContains', 'answerMissing', 'answerEquals' );
}

/** ids front.js already knows how to run; a step's "next" may target these even when the admin never defines them. */
function dt_va_flow_builtin_step_ids() {
	return array(
		'open',
		'enquiry1',
		'client1',
		'describe',
		'work',
		'money',
		'timing',
		'capture',
		'other',
		'human',
	);
}

/** Recursively validates a "next" value: a step id string, or a branch {if:{key,op:...}, then, else}. Returns the cleaned value or a WP_Error. */
function dt_va_flow_step_next_validate( $next, $depth = 0 ) {
	if ( $depth > 5 ) {
		return new WP_Error( 'flow', __( 'Branch nesting is too deep (max 5 levels).', 'dovetail-studio' ) );
	}

	if ( is_string( $next ) ) {
		$next = trim( $next );

		return '' === $next
			? new WP_Error( 'flow', __( 'A step id is required.', 'dovetail-studio' ) )
			: $next;
	}

	if ( ! is_array( $next ) || ! isset( $next['if'] ) || ! is_array( $next['if'] ) ) {
		return new WP_Error(
			'flow',
			__( '"next" must be a step id string, or a branch object with "if"/"then".', 'dovetail-studio' )
		);
	}

	$cond = $next['if'];
	$key  = isset( $cond['key'] ) && is_string( $cond['key'] ) ? trim( $cond['key'] ) : '';
	$op   = null;

	foreach ( dt_va_flow_condition_ops() as $candidate ) {
		if ( array_key_exists( $candidate, $cond ) ) {
			$op = $candidate;

			break;
		}
	}

	if ( '' === $key || null === $op ) {
		return new WP_Error(
			'flow',
			sprintf(
				/* translators: %s: list of condition keys. */
				__( 'Branch condition needs a "key" and one of: %s.', 'dovetail-studio' ),
				implode( ', ', dt_va_flow_condition_ops() )
			)
		);
	}

	$cond_out = array( 'key' => mb_substr( $key, 0, 40 ) );

	if ( 'answerContains' === $op ) {
		$words = array();

		if ( is_array( $cond['answerContains'] ) ) {
			foreach ( $cond['answerContains'] as $w ) {
				if ( is_string( $w ) && '' !== trim( $w ) ) {
					$words[] = mb_strtolower( mb_substr( trim( $w ), 0, 40 ) );
				}
			}
		}

		if ( ! $words ) {
			return new WP_Error( 'flow', __( 'answerContains needs at least one word.', 'dovetail-studio' ) );
		}

		$cond_out['answerContains'] = $words;
	} elseif ( 'answerEquals' === $op ) {
		$val = $cond['answerEquals'];

		if ( ! is_string( $val ) || '' === trim( $val ) ) {
			return new WP_Error( 'flow', __( 'answerEquals needs a non-empty string.', 'dovetail-studio' ) );
		}

		$cond_out['answerEquals'] = mb_substr( trim( $val ), 0, 80 );
	} else {
		$cond_out['answerMissing'] = true;
	}

	if ( ! isset( $next['then'] ) ) {
		return new WP_Error( 'flow', __( 'A branch needs "then".', 'dovetail-studio' ) );
	}

	$then = dt_va_flow_step_next_validate( $next['then'], $depth + 1 );

	if ( is_wp_error( $then ) ) {
		return $then;
	}

	$out = array(
		'if'   => $cond_out,
		'then' => $then,
	);

	if ( isset( $next['else'] ) ) {
		$else = dt_va_flow_step_next_validate( $next['else'], $depth + 1 );

		if ( is_wp_error( $else ) ) {
			return $else;
		}

		$out['else'] = $else;
	}

	return $out;
}

/** Every string reachable inside a "next" value must resolve to a known step id: one defined in this same submission, or one of the built-ins. */
function dt_va_flow_step_next_refs( $next, $known ) {
	if ( is_string( $next ) ) {
		return in_array( $next, $known, true )
			? true
			: new WP_Error(
				'flow',
				sprintf(
					/* translators: %s: step id. */
					__( '"next" points to unknown step "%s".', 'dovetail-studio' ),
					$next
				)
			);
	}

	$then = dt_va_flow_step_next_refs( $next['then'], $known );

	if ( is_wp_error( $then ) ) {
		return $then;
	}

	if ( isset( $next['else'] ) ) {
		$else = dt_va_flow_step_next_refs( $next['else'], $known );

		if ( is_wp_error( $else ) ) {
			return $else;
		}
	}

	return true;
}

function dt_va_flow_steps_validate( $json ) {
	$decoded = json_decode( (string) $json, true );

	if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) ) {
		return new WP_Error( 'flow', __( 'Not valid JSON, or not a JSON object.', 'dovetail-studio' ) );
	}

	if ( count( $decoded ) < 1 || count( $decoded ) > 60 ) {
		return new WP_Error( 'flow', __( 'Provide between 1 and 60 steps.', 'dovetail-studio' ) );
	}

	$types = dt_va_flow_step_types();
	$out   = array();

	foreach ( $decoded as $id => $step ) {
		$id = is_string( $id ) ? trim( $id ) : ( is_int( $id ) ? '' : '' );

		if ( '' === $id || ! is_array( $step ) ) {
			return new WP_Error(
				'flow',
				__( 'Every step needs a non-empty id (object key) and must itself be an object.', 'dovetail-studio' )
			);
		}

		$type = isset( $step['type'] ) && in_array( $step['type'], $types, true ) ? $step['type'] : '';

		if ( '' === $type ) {
			return new WP_Error(
				'flow',
				sprintf(
					/* translators: 1: step id, 2: list of types. */
					__( 'Step "%1$s": type must be one of %2$s.', 'dovetail-studio' ),
					$id,
					implode( ', ', $types )
				)
			);
		}

		$question   = isset( $step['question'] ) && is_string( $step['question'] )
			? trim( $step['question'] )
			: '';
		$answer_key = isset( $step['answerKey'] ) && is_string( $step['answerKey'] )
			? trim( $step['answerKey'] )
			: '';

		if ( '' === $question || '' === $answer_key ) {
			return new WP_Error(
				'flow',
				sprintf(
					/* translators: %s: step id. */
					__( 'Step "%s": needs a question and an answerKey.', 'dovetail-studio' ),
					$id
				)
			);
		}

		$row = array(
			'type'      => $type,
			'question'  => mb_substr( $question, 0, 400 ),
			'answerKey' => mb_substr( $answer_key, 0, 40 ),
		);

		if ( isset( $step['example'] ) && is_string( $step['example'] ) && '' !== trim( $step['example'] ) ) {
			$row['example'] = mb_substr( trim( $step['example'] ), 0, 400 );
		}

		if ( 'choice' === $type ) {
			$options = array();

			if ( isset( $step['options'] ) && is_array( $step['options'] ) ) {
				foreach ( $step['options'] as $o ) {
					if ( is_string( $o ) && '' !== trim( $o ) ) {
						$options[] = mb_substr( trim( $o ), 0, 80 );
					}
				}
			}

			if ( count( $options ) < 1 || count( $options ) > 8 ) {
				return new WP_Error(
					'flow',
					sprintf(
						/* translators: %s: step id. */
						__( 'Step "%s": choice needs between 1 and 8 options.', 'dovetail-studio' ),
						$id
					)
				);
			}

			$row['options'] = $options;
		}

		if ( ! isset( $step['next'] ) ) {
			return new WP_Error( 'flow', 'Step "' . $id . '": needs "next".' );
		}

		$next = dt_va_flow_step_next_validate( $step['next'] );

		if ( is_wp_error( $next ) ) {
			return new WP_Error( 'flow', 'Step "' . $id . '": ' . $next->get_error_message() );
		}

		$row['next']                    = $next;
		$out[ mb_substr( $id, 0, 60 ) ] = $row;
	}

	$known = array_merge( array_keys( $out ), dt_va_flow_builtin_step_ids() );

	foreach ( $out as $id => $row ) {
		$ok = dt_va_flow_step_next_refs( $row['next'], $known );

		if ( is_wp_error( $ok ) ) {
			return new WP_Error( 'flow', 'Step "' . $id . '": ' . $ok->get_error_message() );
		}
	}

	return $out;
}

function dt_va_flow_steps_config() {
	$saved = get_option( 'dt_va_flow_steps', array() );

	return is_array( $saved ) && $saved ? $saved : array();
}

/** Shown in the admin textarea as a starting example; not a copy of the real front.js flow. */
function dt_va_flow_steps_starter() {
	return array(
		'enquiry1' => array(
			'type'      => 'choice',
			'question'  => __( 'What are you enquiring about?', 'dovetail-studio' ),
			'answerKey' => 'type',
			'options'   => array( __( 'A product', 'dovetail-studio' ), __( 'A service', 'dovetail-studio' ), __( 'Not sure yet', 'dovetail-studio' ) ),
			'next'      => 'describe',
		),
		'describe' => array(
			'type'      => 'text',
			'question'  => __( "Tell me a little about the business and who it's for.", 'dovetail-studio' ),
			'answerKey' => 'business',
			'next'      => 'work',
		),
	);
}

function dt_va_flow_steps_settings_process() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return array(
			'notice' => '',
			'error'  => '',
			'raw'    => null,
		);
	}

	$notice = '';
	$error  = '';
	$raw    = null;

	if ( isset( $_POST['dt_va_flow_add_step'] ) && check_admin_referer( 'dt_va_flow_add_step' ) ) {
		$base = dt_va_flow_steps_config();
		$id   = isset( $_POST['add_id'] ) ? trim( wp_unslash( $_POST['add_id'] ) ) : '';

		if ( '' === $id ) {
			$error = __( 'Enter a step id.', 'dovetail-studio' );
		} else {
			$new = array(
				'type'      => isset( $_POST['add_type'] ) ? trim( wp_unslash( $_POST['add_type'] ) ) : '',
				'question'  => isset( $_POST['add_question'] ) ? trim( wp_unslash( $_POST['add_question'] ) ) : '',
				'answerKey' => isset( $_POST['add_answerkey'] ) ? trim( wp_unslash( $_POST['add_answerkey'] ) ) : '',
				'next'      => isset( $_POST['add_next'] ) ? trim( wp_unslash( $_POST['add_next'] ) ) : '',
			);

			if ( 'choice' === $new['type'] ) {
				$opts           = isset( $_POST['add_options'] ) ? trim( wp_unslash( $_POST['add_options'] ) ) : '';
				$new['options'] = array_values( array_filter( array_map( 'trim', explode( ',', $opts ) ) ) );
			}

			$example = isset( $_POST['add_example'] ) ? trim( wp_unslash( $_POST['add_example'] ) ) : '';

			if ( '' !== $example ) {
				$new['example'] = $example;
			}

			$candidate        = $base;
			$candidate[ $id ] = $new;
			$parsed           = dt_va_flow_steps_validate( wp_json_encode( $candidate ) );

			if ( is_wp_error( $parsed ) ) {
				$error = $parsed->get_error_message();
			} else {
				update_option( 'dt_va_flow_steps', $parsed, false );
				$notice = __( 'Step added.', 'dovetail-studio' );
			}
		}
	} elseif ( isset( $_POST['dt_va_flow_steps'] ) && check_admin_referer( 'dt_va_flow_steps' ) ) {
		if ( isset( $_POST['restore_default'] ) ) {
			delete_option( 'dt_va_flow_steps' );
			$notice = __( 'Default step questions restored.', 'dovetail-studio' );
		} else {
			$raw    = isset( $_POST['flow_steps'] ) && is_string( $_POST['flow_steps'] )
				? wp_unslash( $_POST['flow_steps'] )
				: '';
			$parsed = dt_va_flow_steps_validate( $raw );

			if ( is_wp_error( $parsed ) ) {
				$error = $parsed->get_error_message();
			} else {
				update_option( 'dt_va_flow_steps', $parsed, false );
				$notice = __( 'Step questions saved.', 'dovetail-studio' );
			}
		}
	}

	if ( '' !== $notice ) {
		delete_option( 'dt_va_setup_review_doors' );
	}

	return array(
		'notice' => $notice,
		'error'  => $error,
		'raw'    => $raw,
	);
}

function dt_va_flow_steps_settings() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$result = $GLOBALS['dt_va_setup_processed']['dt_va_flow_steps_settings'] ?? dt_va_flow_steps_settings_process();
	$notice = $result['notice'];
	$error  = $result['error'];
	$raw    = $result['raw'];

	if ( $notice || $error ) {
		\DovetailStudio\Core\AdminScreens::notice(
			$error ? 'error' : 'success',
			esc_html( $error ? $error : $notice )
		);
	}

	echo '<h3>'
		. esc_html__( 'Add a step', 'dovetail-studio' ) . '</h3><p class="description">'
		. wp_kses_post(
			__( 'Adds one step without hand-editing JSON. <code>next</code> here only accepts a plain step id (no branching); use the JSON below for a branch. <code>options</code> is comma-separated and only used when Type is "choice".', 'dovetail-studio' )
		) . '</p>';
	echo '<form method="post">';
	wp_nonce_field( 'dt_va_flow_add_step' );
	echo '<input type="hidden" name="dt_va_flow_add_step" value="1"><table class="form-table">';
	echo '<tr><th><label for="dt-va-step-id">'
		. esc_html__( 'Step id', 'dovetail-studio' )
		. '</label></th><td><input class="regular-text" id="dt-va-step-id" name="add_id" placeholder="'
		. esc_attr__( 'e.g. web1', 'dovetail-studio' ) . '" required><p class="description">'
		. wp_kses_post(
			__( 'The first step of a flow is named after its door\'s Service + <code>1</code> (Service <code>web</code> → <code>web1</code>); the next steps can have any name. The order is set only by each step\'s Next step id.', 'dovetail-studio' )
		) . '</p></td></tr>';
	echo '<tr><th><label for="dt-va-step-type">'
		. esc_html__( 'Type', 'dovetail-studio' )
		. '</label></th><td><select id="dt-va-step-type" name="add_type"><option value="choice">choice</option><option value="text">text</option></select></td></tr>';
	echo '<tr><th><label for="dt-va-step-question">'
		. esc_html__( 'Question', 'dovetail-studio' )
		. '</label></th><td><input class="regular-text" id="dt-va-step-question" name="add_question" required></td></tr>';
	echo '<tr><th><label for="dt-va-step-example">'
		. esc_html__( 'Example (optional)', 'dovetail-studio' )
		. '</label></th><td><input class="regular-text" id="dt-va-step-example" name="add_example"></td></tr>';
	echo '<tr><th><label for="dt-va-step-answerkey">'
		. esc_html__( 'Answer key', 'dovetail-studio' )
		. '</label></th><td><input class="regular-text" id="dt-va-step-answerkey" name="add_answerkey" placeholder="'
		. esc_attr__( 'e.g. site_type', 'dovetail-studio' ) . '" required></td></tr>';
	echo '<tr><th><label for="dt-va-step-options">'
		. esc_html__( 'Options', 'dovetail-studio' )
		. '</label></th><td><input class="regular-text" id="dt-va-step-options" name="add_options" placeholder="'
		. esc_attr__( 'Comma-separated, e.g. Brochure site, Online shop, Landing page', 'dovetail-studio' )
		. '"></td></tr>';
	echo '<tr><th><label for="dt-va-step-next">'
		. esc_html__( 'Next step id', 'dovetail-studio' )
		. '</label></th><td><input class="regular-text" id="dt-va-step-next" name="add_next" placeholder="'
		. esc_attr__( 'e.g. web2', 'dovetail-studio' ) . '" required></td></tr>';
	echo '</table><p><button class="button">'
		. esc_html__( 'Add step', 'dovetail-studio' ) . '</button></p></form><hr>';
	$saved = dt_va_flow_steps_config();
	$v     = $saved ? $saved : dt_va_flow_steps_starter();
	echo '<h2>'
		. esc_html__( 'Step questions', 'dovetail-studio' ) . '</h2><p>'
		. wp_kses_post(
			__( 'The questions asked after a door is picked, keyed by step id. Each step needs a <code>type</code> (<code>choice</code> or <code>text</code>), a <code>question</code>, an <code>answerKey</code> (where the answer is stored) and a <code>next</code> step id. <code>choice</code> also needs 1–8 <code>options</code>.', 'dovetail-studio' )
		) . '</p>';
	echo '<p class="description">'
		. esc_html__( 'A step id you don\'t redefine here keeps its built-in question and behaviour, so you can override just one step at a time. Step ids already used by the assistant:', 'dovetail-studio' )
		. ' <code>' . esc_html( implode( ', ', dt_va_flow_builtin_step_ids() ) ) . '</code>'
		. wp_kses_post(
			__( '. <code>next</code> can target any of those even if you never define them here — the fixed pipeline (<code>work</code>/<code>money</code>/<code>timing</code>/<code>capture</code>) always collects the budget, timing and contact details itself and cannot be redefined, only referenced.', 'dovetail-studio' )
		) . '</p>';
	echo '<p class="description">'
		. wp_kses_post(
			__( 'Branch instead of a plain <code>next</code> id: <code>{"if": {"key": "site_type", "answerEquals": "Online shop"}, "then": "shop1", "else": "web2"}</code>. Condition keys:', 'dovetail-studio' )
		) . ' <code>' . esc_html( implode( ', ', dt_va_flow_condition_ops() ) ) . '</code>.</p>';
	echo '<form method="post">';
	wp_nonce_field( 'dt_va_flow_steps' );
	echo '<input type="hidden" name="dt_va_flow_steps" value="1">';
	echo '<textarea name="flow_steps" rows="24" style="width:100%;max-width:960px;font-family:Menlo,Consolas,monospace;font-size:13px">'
		. esc_textarea( wp_json_encode( $v, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ) . '</textarea>';
	echo '<p class="description">'
		. ( $saved ? esc_html( sprintf( /* translators: %d: number of steps. */ _n( 'Using the saved steps (%d).', 'Using the saved steps (%d).', count( $saved ), 'dovetail-studio' ), count( $saved ) ) ) : esc_html__( 'Using the built-in steps; the text above is only a starting example.', 'dovetail-studio' ) )
		. '</p>';
	echo '<p><button class="button button-primary">'
		. esc_html__( 'Save step questions', 'dovetail-studio' )
		. '</button> <button class="button" name="restore_default" value="1" formnovalidate>'
		. esc_html__( 'Restore default', 'dovetail-studio' ) . '</button>';
	dt_va_json_tools( 'flow_steps' );
	echo '</p><p class="description">'
		. esc_html__( 'Import validates a JSON file and fills this editor; click Save to apply it. Export downloads the validated editor content, including unsaved edits. Download a Sample provides a template for this format.', 'dovetail-studio' )
		. '</p></form>';
}

function dt_va_flow_settings_process() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return array(
			'notice' => '',
			'error'  => '',
			'raw'    => null,
		);
	}

	$notice = '';
	$error  = '';
	$raw    = null;

	if ( isset( $_POST['dt_va_flow_add_door'] ) && check_admin_referer( 'dt_va_flow_add_door' ) ) {
		$existing = dt_va_flow_doors_config();
		$base     = $existing ? $existing : dt_va_flow_doors_default();
		$new      = array(
			'id'     => isset( $_POST['add_id'] ) ? trim( wp_unslash( $_POST['add_id'] ) ) : '',
			'label'  => isset( $_POST['add_label'] ) ? trim( wp_unslash( $_POST['add_label'] ) ) : '',
			'action' => isset( $_POST['add_action'] ) ? trim( wp_unslash( $_POST['add_action'] ) ) : '',
		);

		if ( 'service' === $new['action'] ) {
			$new['service'] = isset( $_POST['add_service'] )
				? trim( wp_unslash( $_POST['add_service'] ) )
				: '';
		}

		foreach ( array( 'sub', 'short', 'icon' ) as $f ) {
			$v = isset( $_POST[ 'add_' . $f ] ) ? trim( wp_unslash( $_POST[ 'add_' . $f ] ) ) : '';

			if ( '' !== $v ) {
				$new[ $f ] = $v;
			}
		}

		$candidate = array_merge( $base, array( $new ) );
		$parsed    = dt_va_flow_doors_validate( wp_json_encode( $candidate ) );

		if ( is_wp_error( $parsed ) ) {
			$error = $parsed->get_error_message();
		} else {
			update_option( 'dt_va_flow_doors', $parsed, false );
			$notice = __( 'Door added.', 'dovetail-studio' );
		}
	} elseif ( isset( $_POST['dt_va_flow'] ) && check_admin_referer( 'dt_va_flow' ) ) {
		if ( isset( $_POST['restore_default'] ) ) {
			delete_option( 'dt_va_flow_doors' );
			$notice = __( 'Default doors restored.', 'dovetail-studio' );
		} else {
			$raw    = isset( $_POST['flow_doors'] ) && is_string( $_POST['flow_doors'] )
				? wp_unslash( $_POST['flow_doors'] )
				: '';
			$parsed = dt_va_flow_doors_validate( $raw );

			if ( is_wp_error( $parsed ) ) {
				$error = $parsed->get_error_message();
			} else {
				update_option( 'dt_va_flow_doors', $parsed, false );
				$notice = __( 'Doors saved.', 'dovetail-studio' );
			}
		}
	}

	if ( '' !== $notice ) {
		delete_option( 'dt_va_setup_review_doors' );
	}

	return array(
		'notice' => $notice,
		'error'  => $error,
		'raw'    => $raw,
	);
}

function dt_va_flow_settings() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$result = $GLOBALS['dt_va_setup_processed']['dt_va_flow_settings'] ?? dt_va_flow_settings_process();
	$notice = $result['notice'];
	$error  = $result['error'];
	$raw    = $result['raw'];

	if ( $notice || $error ) {
		\DovetailStudio\Core\AdminScreens::notice(
			$error ? 'error' : 'success',
			esc_html( $error ? $error : $notice )
		);
	}

	echo '<h3>'
		. esc_html__( 'Add a door', 'dovetail-studio' ) . '</h3><p class="description">'
		. esc_html__( 'Adds one door without hand-editing JSON. Service is only used when Action is "service".', 'dovetail-studio' )
		. '</p>';
	echo '<form method="post">';
	wp_nonce_field( 'dt_va_flow_add_door' );
	echo '<input type="hidden" name="dt_va_flow_add_door" value="1"><table class="form-table">';
	echo '<tr><th><label for="dt-va-door-id">'
		. esc_html__( 'Id', 'dovetail-studio' )
		. '</label></th><td><input class="regular-text" id="dt-va-door-id" name="add_id" required></td></tr>';
	echo '<tr><th><label for="dt-va-door-label">'
		. esc_html__( 'Label', 'dovetail-studio' )
		. '</label></th><td><input class="regular-text" id="dt-va-door-label" name="add_label" required></td></tr>';
	echo '<tr><th><label for="dt-va-door-action">'
		. esc_html__( 'Action', 'dovetail-studio' )
		. '</label></th><td><select id="dt-va-door-action" name="add_action"><option value="service">service</option><option value="other">other</option><option value="human">human</option></select></td></tr>';
	$step_ids = array_values(
		array_unique( array_merge( array_keys( dt_va_flow_steps_config() ), array( 'enquiry1', 'client1' ) ) )
	);
	echo '<tr><th><label for="dt-va-door-service">' . esc_html__( 'Service', 'dovetail-studio' ) . '</label></th><td><input class="regular-text" id="dt-va-door-service" name="add_service" placeholder="' . esc_attr__( 'e.g. web', 'dovetail-studio' ) . '" aria-describedby="dt-va-door-step-hint">'
		. '<p class="description" id="dt-va-door-step-hint" aria-live="polite">'
			. wp_kses_post(
				__( 'The door opens the step named Service + <code>1</code>: Service <code>web</code> opens the step <code>web1</code>.', 'dovetail-studio' )
			) . '</p></td></tr>';
	// Live hint: which step this door will open, and whether it exists yet.
	$hint = array(
		'steps'   => $step_ids,
		/* translators: %s: step id, e.g. web1. */
		'opens'   => __( 'This door opens the step %s.', 'dovetail-studio' ),
		'exists'  => __( 'It exists in Step questions.', 'dovetail-studio' ),
		'missing' => __( 'Add it in Step questions (Doors tab, below), or picking this door restarts the assistant.', 'dovetail-studio' ),
	);
	echo '<script>(function(){var h='
		. wp_json_encode( $hint )
		. ',i=document.getElementById("dt-va-door-service"),a=document.getElementById("dt-va-door-action"),p=document.getElementById("dt-va-door-step-hint"),d=p.innerHTML;function u(){var v=(i.value||"").trim().toLowerCase();if(a.value!=="service"||!v){p.innerHTML=d;return;}var s=v+"1",c=document.createElement("code");c.textContent=s;p.textContent="";var t=h.opens.split("%s");p.appendChild(document.createTextNode(t[0]));p.appendChild(c);p.appendChild(document.createTextNode((t[1]||"")+" "+(h.steps.indexOf(s)>-1?h.exists:h.missing)));}i.addEventListener("input",u);a.addEventListener("change",u);u();})();</script>';
	echo '<tr><th><label for="dt-va-door-sub">'
		. esc_html__( 'Sub (optional)', 'dovetail-studio' )
		. '</label></th><td><input class="regular-text" id="dt-va-door-sub" name="add_sub"></td></tr>';
	echo '<tr><th><label for="dt-va-door-short">'
		. esc_html__( 'Short (optional)', 'dovetail-studio' )
		. '</label></th><td><input class="regular-text" id="dt-va-door-short" name="add_short"></td></tr>';
	echo '<tr><th><label for="dt-va-door-icon">'
		. esc_html__( 'Icon (optional)', 'dovetail-studio' )
		. '</label></th><td><select id="dt-va-door-icon" name="add_icon"><option value="">'
		. esc_html__( '(default)', 'dovetail-studio' )
		. '</option><option>logo</option><option>print</option><option>web</option><option>client</option><option>other</option><option>human</option></select></td></tr>';
	echo '</table><p><button class="button">'
		. esc_html__( 'Add door', 'dovetail-studio' ) . '</button></p></form><hr>';
	$saved = dt_va_flow_doors_config();
	$v     = $saved ? $saved : dt_va_flow_doors_default();
	echo '<h2>'
		. esc_html__( 'Doors (initial menu)', 'dovetail-studio' ) . '</h2><p>'
		. wp_kses_post(
			__( 'The buttons visitors see when the assistant opens. Each door needs an <code>id</code>, a <code>label</code> and an <code>action</code> (<code>service</code>, <code>other</code> or <code>human</code>); <code>service</code> doors also need a <code>service</code>: a lowercase id (letters, numbers, <code>-</code> or <code>_</code>) of your choosing, for example <code>web</code> or <code>seo</code> — use whatever names fit this installation\'s services. Optional: <code>sub</code>, <code>short</code>, <code>icon</code> (<code>logo</code>, <code>print</code>, <code>web</code>, <code>client</code>, <code>other</code> or <code>human</code>; any other value falls back to a generic icon).', 'dovetail-studio' )
		) . '</p>';
	echo '<p class="description">'
		. wp_kses_post(
			__( 'A custom <code>service</code> needs a matching step id (<code>service</code> + <code>"1"</code>, e.g. <code>web1</code>) defined below in Step questions — otherwise picking that door just restarts the assistant.', 'dovetail-studio' )
		) . '</p>';
	echo '<form method="post">';
	wp_nonce_field( 'dt_va_flow' );
	echo '<input type="hidden" name="dt_va_flow" value="1">';
	echo '<textarea name="flow_doors" rows="24" style="width:100%;max-width:960px;font-family:Menlo,Consolas,monospace;font-size:13px">'
		. esc_textarea( wp_json_encode( $v, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ) . '</textarea>';
	echo '<p class="description">'
		. ( $saved ? esc_html__( 'Using the saved doors.', 'dovetail-studio' ) : esc_html__( 'Using the default doors.', 'dovetail-studio' ) )
		. '</p>';
	echo '<p><button class="button button-primary">'
		. esc_html__( 'Save doors', 'dovetail-studio' )
		. '</button> <button class="button" name="restore_default" value="1" formnovalidate>'
		. esc_html__( 'Restore default', 'dovetail-studio' ) . '</button>';
	dt_va_json_tools( 'flow_doors' );
	echo '</p><p class="description">'
		. esc_html__( 'Import validates a JSON file and fills this editor; click Save to apply it. Export downloads the validated editor content, including unsaved edits. Download a Sample provides a template for this format.', 'dovetail-studio' )
		. '</p></form><hr>';
	dt_va_flow_steps_settings();
	dt_va_setup_review_controls( 'doors' );
}
