<?php
/**
 * Teamplte type popup
 */
?>
<div class="jet-listings-popup">
	<div class="jet-listings-popup__overlay"></div>
	<div class="jet-listings-popup__content">
		<h3 class="jet-listings-popup__heading"><?php
			esc_html_e( 'Setup Listing Item', 'jet-engine' );
		?></h3>
		<form class="jet-listings-popup__form" id="templates_type_form" method="POST" action="<?php echo $action; ?>" >
			<div class="jet-listings-popup__form-row">
				<label for="listing_source"><?php esc_html_e( 'Listing source:', 'jet-engine' ); ?></label>
				<select id="listing_source" name="listing_source"><?php
					foreach ( $sources as $source_key => $source_label ) {
						printf( '<option value="%1$s">%2$s</option>', $source_key, $source_label );
					}
				?></select>
			</div>
			<div class="jet-listings-popup__form-row jet-template-listing jet-template-posts jet-template-repeater jet-template-act">
				<label for="listing_post_type"><?php esc_html_e( 'From post type:', 'jet-engine' ); ?></label>
				<select id="listing_post_type" name="listing_post_type"><?php
					foreach ( jet_engine()->listings->get_post_types_for_options() as $key => $value ) {
						printf( '<option value="%1$s">%2$s</option>', $key, $value );
					}
				?></select>
			</div>
			<div class="jet-listings-popup__form-row jet-template-listing jet-template-terms">
				<label for="listing_tax"><?php esc_html_e( 'From taxonomy:', 'jet-engine' ); ?></label>
				<select id="listing_tax" name="listing_tax"><?php
					foreach ( jet_engine()->listings->get_taxonomies_for_options() as $key => $value ) {
						printf( '<option value="%1$s">%2$s</option>', $key, $value );
					}
				?></select>
			</div>
			<div class="jet-listings-popup__form-row jet-template-listing jet-template-repeater">
				<label for="repeater_source"><?php esc_html_e( 'Repeater source:', 'jet-engine' ); ?></label>
				<select id="repeater_source" name="repeater_source"><?php
					foreach ( jet_engine()->listings->repeater_sources() as $source_id => $source_name ) {
						printf( '<option value="%1$s">%2$s</option>', $source_id, $source_name );
					}
				?></select>
			</div>
			<div class="jet-listings-popup__form-row jet-template-listing jet-template-repeater">
				<label for="repeater_field"><?php esc_html_e( 'Repeater field:', 'jet-engine' ); ?></label>
				<input type="text" id="repeater_field" name="repeater_field" placeholder="<?php esc_html_e( 'Set repeater field name', 'jet-engine' ); ?>">
			</div>
			<div class="jet-listings-popup__form-row">
				<label for="template_name"><?php esc_html_e( 'Listing item name:', 'jet-engine' ); ?></label>
				<input type="text" id="template_name" name="template_name" placeholder="<?php esc_html_e( 'Set listing name', 'jet-engine' ); ?>">
			</div>
			<div class="jet-listings-popup__form-row">
				<label for="listing_view_type"><?php esc_html_e( 'Listing view:', 'jet-engine' ); ?></label>
				<select id="listing_view_type" name="listing_view_type"><?php
					foreach ( $views as $view_key => $view_label ) {
						printf( '<option value="%1$s">%2$s</option>', $view_key, $view_label );
					}
				?></select>
			</div>
			<div class="jet-listings-popup__form-actions">
				<button type="submit" id="templates_type_submit" class="button button-primary button-hero"><?php
					esc_html_e( 'Create Listing Item', 'jet-engine' );
				?></button>
			</div>
		</form>
	</div>
</div>