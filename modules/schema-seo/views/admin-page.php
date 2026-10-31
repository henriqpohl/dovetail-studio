<?php
/**
 * Dovetail Studio → Schema SEO: integration status and the settings form.
 *
 * @package DovetailStudio\SchemaSeo
 *
 * @var array{dir: string, version: string, option: string, options: array<string,mixed>, yoast: array<string,mixed>, yoast_active: bool, yoast_is_person: bool, other_seo: string, missing: string[], schema_types: array<string,string>, hours: array<int,array<string,mixed>>, integration: array<string,mixed>} $args
 */

use DovetailStudio\Core\AdminScreens;
use DovetailStudio\Core\View;

defined( 'ABSPATH' ) || exit;

$dir             = $args['dir'];
$option          = $args['option'];
$options         = $args['options'];
$yoast           = $args['yoast'];
$yoast_active    = $args['yoast_active'];
$yoast_is_person = $args['yoast_is_person'];
$other_seo       = $args['other_seo'];

/**
 * Source selector values of a field shared with Yoast SEO.
 *
 * @param string              $field   Field key.
 * @param array<string,mixed> $options Saved options.
 * @param array<string,mixed> $yoast   Recognized Yoast values.
 * @return array<string,string>
 */
$source = static function ( string $field, array $options, array $yoast ) use ( $option ): array {
	return array(
		'name'  => $option . '[sources][' . $field . ']',
		'value' => isset( $options['sources'][ $field ] ) ? (string) $options['sources'][ $field ] : 'automatic',
		'yoast' => empty( $yoast[ $field ] ) ? '' : ( is_array( $yoast[ $field ] ) ? implode( ', ', $yoast[ $field ] ) : (string) $yoast[ $field ] ),
	);
};

/**
 * Open a settings card.
 *
 * @param string $title       Title.
 * @param string $description Optional description.
 * @param string $id          Optional anchor, prefixed with dovetail-.
 * @return void
 */
$section_start = static function ( string $title, string $description = '', string $id = '' ) use ( $dir ): void {
	View::render(
		$dir,
		'partials/section-start',
		array(
			'title'       => $title,
			'description' => $description,
			'id'          => $id,
		)
	);
};

/**
 * Close a settings card.
 *
 * @return void
 */
$section_end = static function () use ( $dir ): void {
	View::render( $dir, 'partials/section-end' );
};

/**
 * Single-line field without a source selector.
 *
 * @param string $field Field key.
 * @param string $label Label.
 * @param mixed  $value Value.
 * @param string $type  Input type.
 * @param string $help  Optional help text.
 * @param string $step  Optional step of number fields.
 * @return void
 */
$basic_input = static function ( string $field, string $label, $value, string $type = 'text', string $help = '', string $step = '' ) use ( $dir, $option ): void {
	View::render(
		$dir,
		'partials/field-input',
		array(
			'field' => $field,
			'name'  => $option . '[' . $field . ']',
			'label' => $label,
			'value' => (string) $value,
			'type'  => $type,
			'help'  => $help,
			'step'  => $step,
		)
	);
};

/**
 * Single-line field, optionally with the Yoast source selector.
 *
 * @param string              $field       Field key.
 * @param string              $label       Label.
 * @param array<string,mixed> $options     Saved options.
 * @param array<string,mixed> $yoast       Recognized Yoast values.
 * @param bool                $with_source Whether to show the source selector.
 * @param string              $type        Input type.
 * @param string              $help        Optional help text.
 * @return void
 */
$text = static function ( string $field, string $label, array $options, array $yoast, bool $with_source = false, string $type = 'text', string $help = '' ) use ( $dir, $option, $source ): void {
	View::render(
		$dir,
		'partials/field-text',
		array(
			'dir'    => $dir,
			'field'  => $field,
			'name'   => $option . '[' . $field . ']',
			'label'  => $label,
			'value'  => (string) $options[ $field ],
			'type'   => $type,
			'help'   => $help,
			'source' => $with_source ? $source( $field, $options, $yoast ) : null,
		)
	);
};

/**
 * Textarea, optionally with the Yoast source selector.
 *
 * @param string              $field       Field key.
 * @param string              $label       Label.
 * @param array<string,mixed> $options     Saved options.
 * @param array<string,mixed> $yoast       Recognized Yoast values.
 * @param bool                $with_source Whether to show the source selector.
 * @param string              $help        Optional help text.
 * @return void
 */
$textarea = static function ( string $field, string $label, array $options, array $yoast, bool $with_source = false, string $help = '' ) use ( $dir, $option, $source ): void {
	View::render(
		$dir,
		'partials/field-textarea',
		array(
			'dir'    => $dir,
			'field'  => $field,
			'name'   => $option . '[' . $field . ']',
			'label'  => $label,
			'value'  => (string) $options[ $field ],
			'help'   => $help,
			'source' => $with_source ? $source( $field, $options, $yoast ) : null,
		)
	);
};

/**
 * Textarea without a source selector.
 *
 * @param string $field Field key.
 * @param string $label Label.
 * @param mixed  $value Value.
 * @param string $help  Optional help text.
 * @return void
 */
$basic_textarea = static function ( string $field, string $label, $value, string $help = '' ) use ( $dir, $option ): void {
	View::render(
		$dir,
		'partials/field-textarea',
		array(
			'dir'    => $dir,
			'field'  => $field,
			'name'   => $option . '[' . $field . ']',
			'label'  => $label,
			'value'  => (string) $value,
			'help'   => $help,
			'source' => null,
		)
	);
};

/**
 * Image URL with the WordPress media picker, optionally with the Yoast source selector.
 *
 * @param string              $field       Field key.
 * @param string              $label       Label.
 * @param array<string,mixed> $options     Saved options.
 * @param array<string,mixed> $yoast       Recognized Yoast values.
 * @param bool                $with_source Whether to show the source selector.
 * @return void
 */
$media_field = static function ( string $field, string $label, array $options, array $yoast, bool $with_source = true ) use ( $dir, $option, $source ): void {
	View::render(
		$dir,
		'partials/field-media',
		array(
			'dir'    => $dir,
			'field'  => $field,
			'name'   => $option . '[' . $field . ']',
			'label'  => $label,
			'value'  => (string) $options[ $field ],
			'source' => $with_source ? $source( $field, $options, $yoast ) : null,
		)
	);
};

/**
 * Checkbox row.
 *
 * @param string $field Field key.
 * @param string $label Label.
 * @param mixed  $value Saved value.
 * @param string $help  Text next to the checkbox.
 * @return void
 */
$checkbox = static function ( string $field, string $label, $value, string $help = '' ) use ( $dir, $option ): void {
	View::render(
		$dir,
		'partials/field-checkbox',
		array(
			'name'  => $option . '[' . $field . ']',
			'label' => $label,
			'value' => $value,
			'help'  => $help,
		)
	);
};

/**
 * Select row.
 *
 * @param string               $field   Field key.
 * @param string               $label   Label.
 * @param mixed                $value   Saved value.
 * @param array<string,string> $choices Value => label.
 * @return void
 */
$select = static function ( string $field, string $label, $value, array $choices ) use ( $dir, $option ): void {
	View::render(
		$dir,
		'partials/field-select',
		array(
			'field'   => $field,
			'name'    => $option . '[' . $field . ']',
			'label'   => $label,
			'value'   => $value,
			'choices' => $choices,
		)
	);
};
?>
<div class="wrap dovetail-wrap">
	<div class="dovetail-page-header">
		<div>
			<h1><?php esc_html_e( 'Schema SEO', 'dovetail-studio' ); ?></h1>
			<p><?php esc_html_e( 'Manage business structured data and its integration with Yoast SEO.', 'dovetail-studio' ); ?></p>
		</div>
		<span class="dovetail-version"><?php echo esc_html( 'v' . $args['version'] ); ?></span>
	</div>

	<?php
	AdminScreens::settings_errors();

	if ( $args['missing'] ) {
		AdminScreens::notice(
			'warning',
			esc_html(
				sprintf(
					/* translators: %s: comma-separated field names. */
					__( 'Not published yet: fill in %s. Without a name no business entity is printed, and without an address LocalBusiness types are printed as Organization.', 'dovetail-studio' ),
					implode( ', ', $args['missing'] )
				)
			)
		);
	}
	?>

	<div class="dovetail-status <?php echo ( $yoast_is_person || '' !== $other_seo ) ? 'is-warning' : ( $yoast_active ? 'is-active' : 'is-native' ); ?>">
		<span class="dovetail-status-dot" aria-hidden="true"></span>
		<div>
			<strong><?php esc_html_e( 'Integration status', 'dovetail-studio' ); ?></strong>
			<p>
				<?php if ( '' !== $other_seo && ! $yoast_active ) : ?>
					<?php
					/* translators: %s: SEO plugin name, e.g. Rank Math. */
					echo esc_html( sprintf( __( '%s detected. It publishes the site schema, so this module prints no JSON-LD of its own and nothing is duplicated. The breadcrumb trail for the theme still works.', 'dovetail-studio' ), $other_seo ) );
					?>
				<?php elseif ( $yoast_is_person ) : ?>
					<?php
					esc_html_e( 'Yoast SEO represents this site as a Person. The Person will be preserved and a separate business entity will be added to the same graph.', 'dovetail-studio' );
					?>
				<?php elseif ( $yoast_active ) : ?>
					<?php esc_html_e( 'Yoast SEO detected. The existing Organization entity will be enriched; no second entity is printed.', 'dovetail-studio' ); ?>
				<?php else : ?>
					<?php
					esc_html_e( 'Yoast SEO not detected. The plugin prints a single JSON-LD graph in wp_head with the business entity, the site graph and the breadcrumb trail.', 'dovetail-studio' );
					?>
				<?php endif; ?>
			</p>
		</div>
	</div>

	<nav class="dovetail-section-nav" aria-label="<?php esc_attr_e( 'Settings sections', 'dovetail-studio' ); ?>">
		<a href="#dovetail-general"><?php esc_html_e( 'General', 'dovetail-studio' ); ?></a>
		<?php
		if ( ! $yoast_active ) :
			?>
			<a href="#dovetail-person"><?php esc_html_e( 'Person', 'dovetail-studio' ); ?></a><?php endif; ?>
		<a href="#dovetail-organization"><?php esc_html_e( 'Organization', 'dovetail-studio' ); ?></a>
		<a href="#dovetail-contact"><?php esc_html_e( 'Contact', 'dovetail-studio' ); ?></a>
		<a href="#dovetail-address"><?php esc_html_e( 'Address', 'dovetail-studio' ); ?></a>
		<a href="#dovetail-business"><?php esc_html_e( 'Business', 'dovetail-studio' ); ?></a>
		<a href="#dovetail-type-specific"><?php esc_html_e( 'Type-specific', 'dovetail-studio' ); ?></a>
		<a href="#dovetail-profiles"><?php esc_html_e( 'Profiles', 'dovetail-studio' ); ?></a>
		<a href="#dovetail-identifiers"><?php esc_html_e( 'Identifiers', 'dovetail-studio' ); ?></a>
		<a href="#dovetail-site-graph"><?php esc_html_e( 'Site Graph & Breadcrumbs', 'dovetail-studio' ); ?></a>
		<a href="#dovetail-json-preview"><?php esc_html_e( 'JSON-LD Preview', 'dovetail-studio' ); ?></a>
		<a href="#dovetail-integration"><?php esc_html_e( 'Integration', 'dovetail-studio' ); ?></a>
	</nav>

	<form action="options.php" method="post">
		<?php settings_fields( 'dovetail_schema_seo_group' ); ?>

		<?php
		$section_start(
			__( 'General', 'dovetail-studio' ),
			__( 'Output rules and what the site represents.', 'dovetail-studio' ),
			'general'
		);
		$checkbox(
			'enabled',
			__( 'Enable integration', 'dovetail-studio' ),
			$options['enabled'],
			__( 'Generate Schema or enrich Yoast SEO output.', 'dovetail-studio' )
		);
		?>
		<?php
		$select(
			'display_scope',
			__( 'Display scope', 'dovetail-studio' ),
			$options['display_scope'],
			array(
				'sitewide' => __( 'Entire site', 'dovetail-studio' ),
				'homepage' => __( 'Homepage only', 'dovetail-studio' ),
			)
		);
		?>
		<?php if ( $yoast_active ) : ?>
			<tr><th scope="row"><?php esc_html_e( 'This site represents', 'dovetail-studio' ); ?></th><td><p class="description"><?php echo $yoast_is_person ? esc_html__( 'A person, as set in Yoast SEO (Settings > Site representation). The business below is optional and is linked to that person.', 'dovetail-studio' ) : esc_html__( 'An organization, as set in Yoast SEO (Settings > Site representation).', 'dovetail-studio' ); ?></p></td></tr>
		<?php else : ?>
			<?php
			$select(
				'entity',
				__( 'This site represents', 'dovetail-studio' ),
				$options['entity'],
				array(
					'organization' => __( 'An organization or business', 'dovetail-studio' ),
					'person'       => __( 'A person', 'dovetail-studio' ),
				)
			);
			?>
		<?php endif; ?>
		<?php $section_end(); ?>

		<?php if ( ! $yoast_active ) : ?>
			<?php
			$section_start(
				__( 'Person', 'dovetail-studio' ),
				__( 'Used when the site represents a person: a blog, portfolio or a professional working under their own name. The person becomes the publisher of the site. A business filled in below is optional and is linked to the person (founder / worksFor).', 'dovetail-studio' ),
				'person'
			);
			$basic_input(
				'person_name',
				__( 'Person name', 'dovetail-studio' ),
				$options['person_name']
			);
			$basic_input(
				'person_job_title',
				__( 'Job title', 'dovetail-studio' ),
				$options['person_job_title'],
				'text',
				__( 'For example: Photographer, Dentist, Consultant.', 'dovetail-studio' )
			);
			$basic_textarea(
				'person_description',
				__( 'Description', 'dovetail-studio' ),
				$options['person_description']
			);
			$media_field(
				'person_image',
				__( 'Photo', 'dovetail-studio' ),
				$options,
				array(),
				false
			);
			$basic_textarea(
				'person_same_as',
				__( 'Profiles', 'dovetail-studio' ),
				$options['person_same_as'],
				__( 'One full URL per line: LinkedIn, Instagram, a personal website.', 'dovetail-studio' )
			);
			$section_end();
			?>
		<?php endif; ?>

		<?php
		$section_start(
			__( 'Organization', 'dovetail-studio' ),
			__( 'Identity and type of the organization or business. In Person mode, leave the name blank when there is no business.', 'dovetail-studio' ),
			'organization'
		);
		$select(
			'schema_type',
			__( 'Business type', 'dovetail-studio' ),
			$options['schema_type'],
			$args['schema_types']
		);
		$text( 'name', __( 'Business name', 'dovetail-studio' ), $options, $yoast, true );
		$text(
			'alternate_name',
			__( 'Alternate name', 'dovetail-studio' ),
			$options,
			$yoast,
			true
		);
		$text( 'legal_name', __( 'Legal name', 'dovetail-studio' ), $options, $yoast, true );
		$text( 'slogan', __( 'Slogan', 'dovetail-studio' ), $options, $yoast, true );
		$text(
			'number_of_employees',
			__( 'Number of employees', 'dovetail-studio' ),
			$options,
			$yoast,
			true,
			'number'
		);
		$textarea(
			'description',
			__( 'Description', 'dovetail-studio' ),
			$options,
			$yoast,
			true
		);
		$basic_input(
			'founding_date',
			__( 'Founding date', 'dovetail-studio' ),
			$options['founding_date'],
			'date'
		);
		$basic_textarea(
			'founders',
			__( 'Founders', 'dovetail-studio' ),
			$options['founders'],
			__( 'One per line as Name|URL. URL is optional.', 'dovetail-studio' )
		);
		$basic_input(
			'parent_organization_name',
			__( 'Parent organization name', 'dovetail-studio' ),
			$options['parent_organization_name']
		);
		$basic_input(
			'parent_organization_url',
			__( 'Parent organization URL', 'dovetail-studio' ),
			$options['parent_organization_url'],
			'url'
		);
		$text(
			'url',
			__( 'Website URL', 'dovetail-studio' ),
			$options,
			$yoast,
			true,
			'url',
			__( 'Defaults to the WordPress home URL.', 'dovetail-studio' )
		);
		$media_field( 'logo', __( 'Logo', 'dovetail-studio' ), $options, $yoast );
		$media_field( 'image', __( 'Business image', 'dovetail-studio' ), $options, $yoast );
		$section_end();
		?>

		<?php
		$section_start( __( 'Contact', 'dovetail-studio' ), '', 'contact' );
		$text(
			'telephone',
			__( 'Telephone', 'dovetail-studio' ),
			$options,
			$yoast,
			true,
			'tel'
		);
		$text( 'email', __( 'Email', 'dovetail-studio' ), $options, $yoast, true, 'email' );
		$textarea(
			'contact_points',
			__( 'Contact points', 'dovetail-studio' ),
			$options,
			$yoast,
			true,
			__( 'One per line: Type|Telephone|Email|Languages|Areas. Example: customer service|+1 555 0100|help@example.com|en,es|United States.', 'dovetail-studio' )
		);
		$section_end();
		?>

		<?php
		$section_start(
			__( 'Address', 'dovetail-studio' ),
			__( 'Address and coordinates come from this plugin.', 'dovetail-studio' ),
			'address'
		);
		$basic_input(
			'street_address',
			__( 'Street address', 'dovetail-studio' ),
			$options['street_address']
		);
		$basic_input(
			'address_locality',
			__( 'City / locality', 'dovetail-studio' ),
			$options['address_locality']
		);
		$basic_input(
			'address_region',
			__( 'Region / county', 'dovetail-studio' ),
			$options['address_region']
		);
		$basic_input(
			'postal_code',
			__( 'Postal code', 'dovetail-studio' ),
			$options['postal_code']
		);
		$basic_input(
			'address_country',
			__( 'Country code', 'dovetail-studio' ),
			$options['address_country'],
			'text',
			__( 'Two-letter ISO code, for example US.', 'dovetail-studio' )
		);
		$basic_input(
			'latitude',
			__( 'Latitude', 'dovetail-studio' ),
			$options['latitude'],
			'number',
			'',
			'any'
		);
		$basic_input(
			'longitude',
			__( 'Longitude', 'dovetail-studio' ),
			$options['longitude'],
			'number',
			'',
			'any'
		);
		$basic_input(
			'has_map',
			__( 'Map URL', 'dovetail-studio' ),
			$options['has_map'],
			'url',
			__( 'Public Google Maps or other map URL.', 'dovetail-studio' )
		);
		$basic_textarea(
			'locations',
			__( 'Additional locations', 'dovetail-studio' ),
			$options['locations'],
			__( 'One per line: Name|Street|City|Region|Postal code|Country|Latitude|Longitude|Telephone|URL.', 'dovetail-studio' )
		);
		$basic_textarea(
			'location_hours',
			__( 'Additional location hours', 'dovetail-studio' ),
			$options['location_hours'],
			__( 'One per line: Exact location name|Days|Opens|Closes. Example: Main Office|Monday,Tuesday|09:00|17:00.', 'dovetail-studio' )
		);
		$section_end();
		?>

		<?php
		$section_start( __( 'Business', 'dovetail-studio' ), '', 'business' );
		$basic_input(
			'price_range',
			__( 'Price range', 'dovetail-studio' ),
			$options['price_range'],
			'text',
			__( 'Examples: $$, $20–$100.', 'dovetail-studio' )
		);
		$basic_input(
			'currencies_accepted',
			__( 'Currencies accepted', 'dovetail-studio' ),
			$options['currencies_accepted'],
			'text',
			__( 'ISO 4217 codes separated by commas, for example USD, EUR.', 'dovetail-studio' )
		);
		$basic_input(
			'payment_accepted',
			__( 'Payment methods', 'dovetail-studio' ),
			$options['payment_accepted'],
			'text',
			__( 'Comma-separated, for example Cash, Credit Card, Bank Transfer.', 'dovetail-studio' )
		);
		$basic_textarea(
			'area_served',
			__( 'Areas served', 'dovetail-studio' ),
			$options['area_served'],
			__( 'One per line. Use Type|Name, for example Country|United States or AdministrativeArea|California.', 'dovetail-studio' )
		);
		$basic_textarea(
			'languages',
			__( 'Languages', 'dovetail-studio' ),
			$options['languages'],
			__( 'Comma-separated BCP 47 codes, for example en, es, fr.', 'dovetail-studio' )
		);
		$basic_textarea(
			'services',
			__( 'Services', 'dovetail-studio' ),
			$options['services'],
			__( 'One per line as Name|URL|Description. URL and description are optional.', 'dovetail-studio' )
		);
		View::render( $dir, 'partials/opening-hours', array( 'days' => $args['hours'] ) );
		$basic_textarea(
			'special_hours',
			__( 'Special opening hours', 'dovetail-studio' ),
			$options['special_hours'],
			__( 'One per line: Start date|End date|Opens|Closes. Use 00:00|00:00 for closed dates.', 'dovetail-studio' )
		);
		$section_end();
		?>

		<?php
		$section_start(
			__( 'Type-specific fields', 'dovetail-studio' ),
			__( 'Only properties compatible with the selected business type are emitted.', 'dovetail-studio' ),
			'type-specific'
		);
		$basic_textarea(
			'serves_cuisine',
			__( 'Cuisine types', 'dovetail-studio' ),
			$options['serves_cuisine'],
			__( 'Restaurant, cafe, bar, or bakery only. Comma-separated.', 'dovetail-studio' )
		);
		$basic_input(
			'menu',
			__( 'Menu URL', 'dovetail-studio' ),
			$options['menu'],
			'url',
			__( 'Food establishments only.', 'dovetail-studio' )
		);
		$basic_input(
			'accepts_reservations',
			__( 'Accepts reservations', 'dovetail-studio' ),
			$options['accepts_reservations'],
			'text',
			__( 'Food establishments only. Use true, false, or a reservation URL.', 'dovetail-studio' )
		);
		$basic_input(
			'checkin_time',
			__( 'Check-in time', 'dovetail-studio' ),
			$options['checkin_time'],
			'time',
			__( 'Hotel only.', 'dovetail-studio' )
		);
		$basic_input(
			'checkout_time',
			__( 'Check-out time', 'dovetail-studio' ),
			$options['checkout_time'],
			'time',
			__( 'Hotel only.', 'dovetail-studio' )
		);
		$basic_input(
			'star_rating',
			__( 'Star rating', 'dovetail-studio' ),
			$options['star_rating'],
			'number',
			__( 'Hotel only. Value from 1 to 5.', 'dovetail-studio' ),
			'0.1'
		);
		$basic_textarea(
			'amenities',
			__( 'Amenities', 'dovetail-studio' ),
			$options['amenities'],
			__( 'Hotel only. Comma-separated.', 'dovetail-studio' )
		);
		$basic_textarea(
			'medical_specialties',
			__( 'Medical specialties', 'dovetail-studio' ),
			$options['medical_specialties'],
			__( 'MedicalClinic or Dentist only. Comma-separated Schema.org specialty names.', 'dovetail-studio' )
		);
		$section_end();
		?>

		<?php
		$section_start( __( 'Profiles', 'dovetail-studio' ), '', 'profiles' );
		$textarea(
			'same_as',
			__( 'Social profiles', 'dovetail-studio' ),
			$options,
			$yoast,
			true,
			__( 'One full URL per line. In Schema.org, sameAs refers to an external URL that unequivocally identifies the same entity. Official examples include Wikipedia, Wikidata, and an official website. Yoast also describes these as URLs for social media profiles or other authoritative profiles of the organization.', 'dovetail-studio' )
		);
		$section_end();
		?>

		<?php
		$section_start( __( 'Identifiers', 'dovetail-studio' ), '', 'identifiers' );
		$text( 'vat_id', __( 'VAT ID', 'dovetail-studio' ), $options, $yoast, true );
		$text( 'tax_id', __( 'Tax ID', 'dovetail-studio' ), $options, $yoast, true );
		$text(
			'iso6523_code',
			__( 'ISO 6523 code', 'dovetail-studio' ),
			$options,
			$yoast,
			true,
			'text',
			__( 'Example: 0199:LEI-CODE.', 'dovetail-studio' )
		);
		$text(
			'global_location_number',
			__( 'Global Location Number', 'dovetail-studio' ),
			$options,
			$yoast,
			true
		);
		$text( 'lei_code', __( 'LEI code', 'dovetail-studio' ), $options, $yoast, true );
		$text( 'naics', __( 'NAICS', 'dovetail-studio' ), $options, $yoast, true );
		$text( 'duns', __( 'DUNS', 'dovetail-studio' ), $options, $yoast, true );
		$basic_textarea(
			'identifiers',
			__( 'Other identifiers', 'dovetail-studio' ),
			$options['identifiers'],
			__( 'One per line as Name|Value, for example Company number|123456.', 'dovetail-studio' )
		);
		$section_end();
		?>

		<?php
		$section_start(
			__( 'JSON-LD Preview', 'dovetail-studio' ),
			__( 'Load the JSON-LD currently published on the homepage, including the Yoast graph enriched by this plugin.', 'dovetail-studio' ),
			'json-preview'
		);
		View::render( $dir, 'partials/json-preview' );
		$section_end();
		?>

		<?php
		$section_start(
			__( 'Site Graph & Breadcrumbs', 'dovetail-studio' ),
			__( 'With Yoast SEO active, Yoast provides WebSite, WebPage, Article and BreadcrumbList and the breadcrumb trail follows the Yoast breadcrumb settings. Without Yoast SEO, this module adds them to a single JSON-LD graph together with the business entity.', 'dovetail-studio' ),
			'site-graph'
		);
		$checkbox(
			'site_graph_enabled',
			__( 'Site graph', 'dovetail-studio' ),
			$options['site_graph_enabled'],
			__( 'Add WebSite, WebPage, Article and author entities when Yoast SEO is not providing the graph.', 'dovetail-studio' )
		);
		$checkbox(
			'breadcrumbs_enabled',
			__( 'Breadcrumbs', 'dovetail-studio' ),
			$options['breadcrumbs_enabled'],
			__( 'Provide the breadcrumb trail to the theme through dovetail_studio_get_breadcrumbs() and add BreadcrumbList when Yoast SEO is not providing the graph.', 'dovetail-studio' )
		);
		$basic_input(
			'breadcrumbs_home_label',
			__( 'Home label', 'dovetail-studio' ),
			$options['breadcrumbs_home_label'],
			'text',
			__( 'First breadcrumb text when the plugin builds the trail. Leave empty to use "Home". With Yoast SEO, set it in the Yoast breadcrumb settings instead.', 'dovetail-studio' )
		);
		$section_end();
		?>

		<?php $section_start( __( 'Integration', 'dovetail-studio' ), __( 'Automatic keeps an existing Yoast property and uses the module value only when that property is missing. Use plugin value explicitly replaces that property; a blank plugin value omits it. Fields without a source selector are controlled directly by this plugin.', 'dovetail-studio' ), 'integration' ); ?>
		<?php View::render( $dir, 'partials/integration-summary', $args['integration'] ); ?>
		<?php $section_end(); ?>

		<div class="dovetail-save-bar">
			<?php submit_button(); ?>
		</div>
	</form>
</div>
