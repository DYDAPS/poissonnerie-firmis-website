<?php
/**
 * Form fields.
 *
 * This file is part of the Manage Notification Emails plugin
 * You can find out more about this plugin at https://www.freeamigos.nl
 * Copyright (c) 2006-2015  Virgial Berveling
 *
 * @package WordPress
 * @author Virgial Berveling
 * @copyright 2006-2015
 *
 * since: 1.3.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'FAMNESettingsPage', false ) ) {
	exit;
}



/**
 * Print start_table_form.
 *
 * @return void
 */
function famne_print_start_table_form() {
	?>
	<table class="form-table"><tbody>
	<?php
}

/**
 * Print end_table_form.
 *
 * @return void
 */
function famne_print_end_table_form() {
	?>
	</tbody></table>
	<?php
}

/**
 * Print checkbox.
 *
 * @param  array  $options Plugin options.
 * @param  string $id      Option key.
 * @param  string $title   Field title, may contain HTML.
 * @param  string $label   Field label, may contain HTML.
 * @param  string $message Description below the field, may contain HTML.
 * @return void
 */
function famne_print_checkbox( $options, $id, $title, $label = '', $message = '' ) {
	$checked = isset( $options[ $id ] ) && '1' === strval( $options[ $id ] ) ? true : false;

	print '<div class="option-container ' . ( $checked ? 'active' : '' ) . '">';
	print '<label><input type="checkbox" name="' . esc_attr( 'famne_options[' . $id . ']' ) . '" value="1" ' . ( $checked ? 'checked="checked"' : '' ) . ' class="checkbox-change"/>';
	print '<strong>' . wp_kses_post( $title ) . '</strong><br/>' . wp_kses_post( $label );
	print '</label>';
	print '<p class="description">' . wp_kses_post( $message ) . '</p>';
	print '</div>';
}

/**
 * Print textbox.
 *
 * @param  array  $options     Plugin options.
 * @param  string $id          Option key.
 * @param  string $title       Field title, may contain HTML.
 * @param  string $label       Field label, may contain HTML.
 * @param  string $message     Description below the field, may contain HTML.
 * @param  string $type        Input type.
 * @param  string $placeholder Placeholder text.
 * @return void
 */
function famne_print_textbox( $options, $id, $title, $label = '', $message = '', $type = 'text', $placeholder = '' ) {
	$value = isset( $options[ $id ] ) && ! empty( $options[ $id ] ) ? $options[ $id ] : '';

	print '<div class="text-container ' . ( ! empty( $value ) ? 'active' : '' ) . '">';
	print '<br/><strong>' . wp_kses_post( $title ) . '</strong><br/>';
	print '<label><input type="' . esc_attr( $type ) . '" name="' . esc_attr( 'famne_options[' . $id . ']' ) . '" placeholder="' . esc_attr( $placeholder ) . '" value="' . esc_attr( $value ) . '" />';
	print wp_kses_post( $label );
	print '</label>';
	print '<p class="description">' . wp_kses_post( $message ) . '</p>';
	print '</div>';
}


// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Backwards compatible function names.

if ( ! function_exists( 'print_start_table_form' ) ) :
	/**
	 * Print start_table_form.
	 *
	 * @deprecated 1.9.0 Use famne_print_start_table_form().
	 *
	 * @return void
	 */
	function print_start_table_form() {
		_deprecated_function( __FUNCTION__, '1.9.0', 'famne_print_start_table_form' );
		famne_print_start_table_form();
	}
endif;

if ( ! function_exists( 'print_end_table_form' ) ) :
	/**
	 * Print end_table_form.
	 *
	 * @deprecated 1.9.0 Use famne_print_end_table_form().
	 *
	 * @return void
	 */
	function print_end_table_form() {
		_deprecated_function( __FUNCTION__, '1.9.0', 'famne_print_end_table_form' );
		famne_print_end_table_form();
	}
endif;

if ( ! function_exists( 'print_checkbox' ) ) :
	/**
	 * Print a checkbox field.
	 *
	 * @deprecated 1.9.0 Use famne_print_checkbox().
	 *
	 * @param  array  $options Plugin options.
	 * @param  string $id      Option key.
	 * @param  string $title   Field title.
	 * @param  string $label   Field label.
	 * @param  string $message Description below the field.
	 * @return void
	 */
	function print_checkbox( $options, $id, $title, $label = '', $message = '' ) {
		_deprecated_function( __FUNCTION__, '1.9.0', 'famne_print_checkbox' );
		famne_print_checkbox( $options, $id, $title, $label, $message );
	}
endif;

if ( ! function_exists( 'print_textbox' ) ) :
	/**
	 * Print a text field.
	 *
	 * @deprecated 1.9.0 Use famne_print_textbox().
	 *
	 * @param  array  $options     Plugin options.
	 * @param  string $id          Option key.
	 * @param  string $title       Field title.
	 * @param  string $label       Field label.
	 * @param  string $message     Description below the field.
	 * @param  string $type        Input type.
	 * @param  string $placeholder Placeholder text.
	 * @return void
	 */
	function print_textbox( $options, $id, $title, $label = '', $message = '', $type = 'text', $placeholder = '' ) {
		_deprecated_function( __FUNCTION__, '1.9.0', 'famne_print_textbox' );
		famne_print_textbox( $options, $id, $title, $label, $message, $type, $placeholder );
	}
endif;

// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
