<?php
/**
 * Field label template
 */

if ( 'heading' === $args['type'] ) {
	$class = 'jet-form__heading';
} else {
	$class = 'jet-form__label';
}

?>
<div class="<?php echo $class; ?>">
	<span class="jet-form__label-text"><?php
	echo $args['label'];

	if ( 'heading' !== $args['type'] && $this->get_required_val( $args ) && ! empty( $this->args['required_mark'] ) ) {
		printf( '<span class="jet-form__required">%s</span>', $this->args['required_mark'] );
	}

	?></span>
	<?php
	if ( 'hidden' !== $args['type'] ) {
		include jet_engine()->get_template( 'forms/common/prev-page-button.php' );
	}
?></div>