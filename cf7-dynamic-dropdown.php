<?php
/*
Plugin Name: WPCF7 Dynamic Dropdown
Plugin URI: https://github.com/obiPlabon/cf7-dynamic-dropdown/
Description: Provides a dynamic dropdown field that accepts any shortcode to generate the select values. Requires Contact Form 7
Version: 1.0.0
Author: obiPlabon
Author URI: https://github.com/obiPlabon/
License: GPL
*/

defined( 'ABSPATH' ) || die();

if ( ! class_exists( 'WPCF7_Dynamic_Dropdown' ) ) :

class WPCF7_Dynamic_Dropdown {

	private static $tag_name = 'dynamicdropdown';

	private static $required_version = '4.6';

	public static function init() {
		add_action( 'plugins_loaded', array( __CLASS__, 'add_hooks' ), 20 );
	}

	public static function add_hooks() {
		if ( ! class_exists( 'WPCF7' ) ) {
			add_action( 'admin_notices', array( __CLASS__, 'show_required_notice' ) );
			return;
		}

		if ( ! version_compare( WPCF7_VERSION, self::$required_version, '>=' ) ) {
			add_action( 'admin_notices', array( __CLASS__, 'show_incompatibility_notice' ) );
			return;
		}

		wpcf7_add_form_tag( self::$tag_name, array( __CLASS__, 'add_form_tag' ), true );
		wpcf7_add_form_tag( self::$tag_name . '*', array( __CLASS__, 'add_form_tag' ), true );

		add_filter( 'wpcf7_validate_' . self::$tag_name, array( __CLASS__, 'validate' ), 10, 2 );
		add_filter( 'wpcf7_validate_' . self::$tag_name . '*', array( __CLASS__, 'validate' ), 10, 2 );

		add_action( 'admin_init', array( __CLASS__, 'add_tag_generator' ), 25 );
	}

	public static function show_required_notice() {
		?>
		<div class="notice notice-error">
			<p><?php printf( __( '%1$s depends on %2$s plugin. But it seems either %2$s is inactive or not installed. Please activate or install the plugin.', 'wpcf7-dd' ), '<strong>CF7 Dynamic Dropdown</strong>', '<strong>Contact Form 7</strong>' ); ?></p>
		</div>
		<?php
	}

	public static function show_incompatibility_notice() {
		?>
		<div class="notice notice-error">
			<p><?php printf( __( '%1$s is incompatible with your installed %2$s plugin. It requires at least %3$s', 'wpcf7-dd' ), '<strong>CF7 Dynamic Dropdown</strong>', '<strong>Contact Form 7</strong>', '<strong>Contact Form 7</strong>', self::$required_version ); ?></p>
		</div>
		<?php
	}

	public static function pre( $content ) {
		echo '<pre>';
		print_r( $content );
		echo '<pre>';
	}

	public static function add_form_tag( $tag ) {
		// generates html for form field
		if ( is_a( $tag, 'WPCF7_FormTag' ) ) {
			$tag = (array) $tag;
		}

		if ( empty( $tag ) ) {
			return '';
		}

		$name = $tag['name'];
		if ( empty( $name ) ) {
			return '';
		}

		$type = $tag['type'];
		$options = (array) $tag['options'];
		$values = (array) $tag['values'];
		$wpcf7_contact_form = WPCF7_ContactForm::get_current();

		$atts          = '';
		$name_attr     = $name;
		$id_attr       = '';
		$class_attr    = '';
		$multiple_attr = '';
		$tabindex_attr = '';

		$class_attr .= ' wpcf7-select';

		if ( $type == self::$tag_name . '*' ) {
			$class_attr .= ' wpcf7-validates-as-required';
			$atts .= ' aria-required="true"';
		}

		$multiple = false;

		if ( count( $options ) ) {
			foreach ( $options as $option ) {
				if ( 'multiple' === $option ) {
					$multiple_attr = ' multiple="multiple"';
					$multiple = true;
				} elseif ( preg_match('%^id:([-0-9a-zA-Z_]+)$%', $option, $matches ) ) {
					$id_attr = $matches[1];
				} elseif ( preg_match( '%^class:([-0-9a-zA-Z_]+)$%', $option, $matches ) ) {
					$class_attr .= ' ' . $matches[1];
				} elseif ( preg_match( '%^tabindex:(\d+)$%', $option, $matches ) ) {
					$tabindex_attr = intval( $matches[1] );
				}
			} // end foreach options
		} // end if count $options

		if ( $multiple ) {
			$name_attr .= '[]';
		}

		$atts .= ' name="' . esc_attr( $name_attr ) . '"';
		if ( $id_attr ) {
			$atts .= ' id="' . esc_attr( trim( $id_attr ) ) . '"';
		}
		if ( $class_attr ) {
			$atts .= ' class="' . esc_attr( trim( $class_attr ) ) . '"';
		}
		if ( $tabindex_attr ) {
			$atts .= ' tabindex="' . esc_attr( $tabindex_attr ) . '"';
		}
		$atts .= ' ' . $multiple_attr;

		$value = '';
		if ( is_a( $wpcf7_contact_form, 'WPCF7_ContactForm' ) && $wpcf7_contact_form->is_posted() ) {
			if ( isset( $_POST['_wpcf7_mail_sent'] ) && $_POST['_wpcf7_mail_sent']['ok'] ) {
				$value = '';
			} else {
				$value = stripslashes_deep( $_POST[ $name ] );
			}
		} else {
			if ( isset( $_GET[ $name ] ) ) {
				$value = stripslashes_deep( $_GET[ $name ] );
			}
		}

		$filter_args   = array();
		$filter_string = '';
		if ( isset( $values[0] ) ) {
			$filter_string = $values[0];
		}

		if ( '' != $filter_string ) {
			$filter_parts = explode( ' ', $filter_string );
			$count = count( $filter_parts );
			for ( $i = 0; $i < $count; $i++ ) {
				if ( '' != trim( $filter_parts[ $i ] ) ) {
					$arg_parts = explode( '=', $filter_parts[ $i ] );
					if ( count( $arg_parts ) === 2 ) {
						$filter_args[ trim( $arg_parts[0] ) ] = trim( $arg_parts[1], ' \'' );
					} else {
						$filter_args[] = trim( $arg_parts[0], ' \'' );
					}
				} // end if filter part
			} // end for
		} // end if filter string

		// Filter name: wpcf7_dynamicdrodown
		$field_options = apply_filters( 'wpcf7_' . self::$tag_name, array(), $filter_args );
		// Filter name: wpcf7_dynamicdropdown_$field_name
		$field_options = apply_filters( 'wpcf7_' . self::$tag_name . '_' . $name, $field_options, $filter_args );

		if ( ! is_array( $field_options ) || ! count( $field_options ) ) {
			return '';
		}

		$validation_error = '';
		if ( is_a( $wpcf7_contact_form, 'WPCF7_ContactForm' )) {
			$validation_error = $wpcf7_contact_form->validation_error( $name );
		}
		$invalid = false;
		if ( $validation_error ) {
			$invalid = true;
			$atts .= ' aria-invalid="' . $invalid . '"';
		}

		$default = '';
		if ( isset( $field_options['default'] ) ) {
			$default = $field_options['default'];
			unset( $field_options['default'] );
		}
		if ( ! is_array( $default ) ) {
			$default = array( $default );
		}
		if ( ! $multiple && count( $default ) > 1 ) {
			$default = array( array_pop( $default ) );
		}
		$use_default = true;
		if ( isset( $_POST[ $name] ) || isset( $_GET[ $name ] ) ) {
			$use_default = false;
		}

		ob_start();
		?>
			<span class="wpcf7-form-control-wrap <?php echo $name; ?>">
				<select <?php echo trim( $atts ); ?>>
					<?php
						foreach ( $field_options as $_key => $_options ) {
							if ( empty( $_options['label'] ) || empty( $_options['value'] ) ) {
								continue;
							}
							?>
							<option data-key="<?php echo esc_attr( $_key ); ?>" value="<?php echo esc_attr( $_options['value'] ); ?>"<?php
										if ( ! $use_default ) {
											if ( ! is_array( $value ) && ( $value == $_key || $value == $_options['value'] ) ) {
												echo ' selected="selected"';
											} elseif ( is_array( $value ) && ( in_array( $_key, $value ) || in_array( $_options['value'], $value ) ) ) {
												echo ' selected="selected"';
											}
										} else {
											if ( in_array( $_key, $default ) || in_array( $_options['value'], $default ) ) {
												echo ' selected="selected"';
											}
										}
									?>><?php echo esc_html( $_options['label'] ); ?></option>
							<?php
						} // end foreach field value
					?>
				</select>
				<?php echo $validation_error; ?>
			</span>
		<?php
		$html = ob_get_clean();
		return $html;
	}

	public static function validate( $result, $tag ) {
		$tag_o = $tag;
		if ( is_a( $tag, 'WPCF7_FormTag' ) ) {
			$tag = (array) $tag;
		}
		// valiedates field on submit
		$wpcf7_contact_form = WPCF7_ContactForm::get_current();
		$type = $tag['type'];
		$name = $tag['name'];
		if ($type != 'dynamicdropdown*') {
			return $result;
		}
		$value_found = false;
		if (isset($_POST[$name])) {
			$value = $_POST[$name];
			if (!is_array($value) && trim($value) != '') {
				$value_found = true;
			}
			if (is_array($value) && count($value)) {
				foreach ($value as $item) {
					if (trim($item) != '') {
						$value_found = true;
						break;
					}
				} // end foreach value
			} // end if array && count
		} // end if set
		if ( ! $value_found ) {
			$result->invalidate($tag_o, wpcf7_get_message('invalid_required'));
			//$result['valid'] = false;
			//$result['reason'][$name] = $wpcf7_contact_form->message('invalid_required');
		}
		return $result;
	} // end public function validation_filter

	public static function add_tag_generator() {
		wpcf7_add_tag_generator(
			self::$tag_name,
			__( 'Dynamic Dropdown', 'wpcf7' ),
			'wpcf7-tg-pane-dynamicdropdown',
			array( __CLASS__, 'add_tag_panel' )
		);
	}

	public static function add_tag_panel( $form, $args = '' ) {
		// output the code for CF7 tag generator
		$type = self::$tag_name;
		if ( ! class_exists( 'WPCF7_TagGenerator' ) ) {
			return;
		}
		$args = wp_parse_args( $args, array() );
		$desc = __('Generate a form-tag for a Dynamic Select field. For more details, see %s.');
		$desc_link = '<a href="https://wordpress.org/plugins/contact-form-7-dynamic-select-extension/" target="_blank">'.__( 'Contact Form 7 - Dynamic Select Extension').'</a>';
		?>
			<div class="control-box">
				<fieldset>
					<legend><?php echo sprintf( esc_html( $desc ), $desc_link ); ?></legend>
					<table class="form-table">
						<tbody>
							<tr>
								<th scope="row">
									<label for="<?php echo esc_attr($args['content'].'-required'); ?>"><?php echo esc_html(__('Required field', 'contact-form-7')); ?></label>
								</th>
								<td>
									<input type="checkbox" name="required" id="<?php echo esc_attr($args['content'].'-required' ); ?>" />
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="<?php
											echo esc_attr($args['content'].'-name'); ?>"><?php
											echo esc_html(__('Name', 'contact-form-7')); ?></label>
								</th>
								<td>
									<input type="text" name="name" class="tg-name oneline" id="<?php
											echo esc_attr($args['content'].'-name' ); ?>" />
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="<?php
											echo esc_attr($args['content'].'-id'); ?>"><?php
											echo esc_html(__('Id attribute', 'contact-form-7')); ?></label>
								</th>
								<td>
									<input type="text" name="id" class="idvalue oneline option" id="<?php
											echo esc_attr($args['content'].'-id'); ?>" />
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="<?php
											echo esc_attr($args['content'].'-class'); ?>"><?php
											echo esc_html(__('Class attribute', 'contact-form-7'));?></label>
								</th>
								<td>
									<input type="text" name="class" class="classvalue oneline option" id="<?php
											echo esc_attr($args['content'].'-class'); ?>" />
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="<?php
											echo esc_attr($args['content'].'-values'); ?>"><?php
											echo esc_html(__('Filter')); ?></label>
								</th>
								<td>
									<input type="text" name="values" class="tg-name oneline" id="<?php
											echo esc_attr($args['content'].'-values' ); ?>" /><br />
											<?php
												echo esc_html(__('You can enter any filter. Use single quotes only.
																	See docs &amp; examples.'));
											?>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="<?php
											echo esc_attr($args['content'].'-multiple'); ?>"><?php
											echo esc_html(__('Allow multiple selections', 'contact-form-7')); ?></label>
								</th>
								<td>
									<input type="checkbox" name="multiple" class="multiplevalue option" id="<?php
											echo esc_attr($args['content'].'-multiple' ); ?>" />
								</td>
							</tr>
						</tbody>
					</table>
				</fieldset>
			</div>
			<div class="insert-box">
				<input type="text" name="dynamicdropdown" class="tag code" readonly="readonly" onfocus="this.select()" />
				<div class="submitbox">
					<input type="button" class="button button-primary insert-tag" value="<?php echo esc_attr( __( 'Insert Tag', 'contact-form-7' ) ); ?>" />
				</div>
			</div>
		<?php
	} // end public function tag_pane

} // end class cf7_dynamic_select

WPCF7_Dynamic_Dropdown::init();

endif;