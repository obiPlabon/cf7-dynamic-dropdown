<?php
/*
Plugin Name: CF7 Dynamic Dropdown
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

	public function __construct() {
		add_action( 'plugins_loaded', array( $this, 'init' ), 20 );
	}

	public function init() {
		if ( ! class_exists( 'WPCF7' ) ) {
			add_action( 'admin_notices', array( $this, 'add_cf7_required_notice' ) );
			return;
		}

		if ( ! version_compare( WPCF7_VERSION, '4.6', '>=' ) ) {
			add_action( 'admin_notices', array( $this, 'add_cf7_version_notice' ) );
			return;
		}

		if ( function_exists( 'wpcf7_add_form_tag' ) ) {
			wpcf7_add_form_tag( 'dynamicdropdown', array( $this, 'add_form_tag' ), true );
			wpcf7_add_form_tag( 'dynamicdropdown*', array( $this, 'add_form_tag' ), true );
		}

		add_filter( 'wpcf7_validate_dynamicdropdown', array( $this, 'validate' ), 10, 2 );
		add_filter( 'wpcf7_validate_dynamicdropdown*', array( $this, 'validate' ), 10, 2 );
		add_action( 'admin_init', array( $this, 'add_tag_generator' ), 25 );
	}

	public function add_cf7_required_notice() {
		?>
		<div class="notice notice-error">
			<p><?php printf( __( '%1$s is depended on %2$s. It seems %2$s is inactive or not installed. Please activate or install %2$s.', 'cf7-dd' ), '<strong>CF7 Dynamic Dropdown</strong>', '<strong>Contact Form 7</strong>' ); ?></p>
		</div>
		<?php
	}

	public function add_cf7_version_notice() {
		?>
		<div class="notice notice-error">
			<p><?php printf( __( '%1$s is incompatible with your installed %2$s. %1$s requires at least 4.6 but your installed version is %3$s', 'cf7-dd' ), '<strong>CF7 Dynamic Dropdown</strong>', '<strong>Contact Form 7</strong>', WPCF7_VERSION ); ?></p>
		</div>
		<?php
	}

	public function pre( $content ) {
		echo '<pre>';
		print_r( $content );
		echo '<pre>';
	}
	public function add_form_tag( $tag ) {
		// generates html for form field
		if ( is_a( $tag, 'WPCF7_FormTag' ) ) {
			$tag = (array) $tag;
		}

		$this->pre( $tag );

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

		$atts         = '';
		$name_att     = $name;
		$id_att       = '';
		$class_att    = '';
		$multiple_att = '';
		$tabindex_att = '';

		$class_att .= ' wpcf7-select';

		if ( $type == 'dynamicdropdown*' ) {
			$class_att .= ' wpcf7-validates-as-required';
			$atts .= ' aria-required="true"';
		}

		$multiple     = false;
		$returnlabels = false;

		if ( count( $options ) ) {
			foreach ( $options as $option ) {
				if ( 'multiple' === $option ) {
					$multiple_att = ' multiple="multiple"';
					$multiple = true;
				} elseif( 'returnlabels' === $option ) {
					$returnlabels = true;
				} elseif ( preg_match('%^id:([-0-9a-zA-Z_]+)$%', $option, $matches ) ) {
					$id_att = $matches[1];
				} elseif ( preg_match( '%^class:([-0-9a-zA-Z_]+)$%', $option, $matches ) ) {
					$class_att .= ' ' . $matches[1];
				} elseif ( preg_match( '%^tabindex:(\d+)$%', $option, $matches ) ) {
					$tabindex_att = intval( $matches[1] );
				}
			} // end foreach options
		} // end if count $options

		if ( $multiple ) {
			$name_att .= '[]';
		}

		$atts .= ' name="' . $name_att . '"';
		if ( $id_att ) {
			$atts .= ' id="'. trim( $id_att ) . '"';
		}
		if ( $class_att ) {
			$atts .= ' class="'.trim($class_att).'"';
		}
		if ($tabindex_att) {
			$atts .= ' tabindex="'.$tabindex_att.'"';
		}
		$atts .= ' '.$multiple_att;

		$value = '';
		if ( is_a( $wpcf7_contact_form, 'WPCF7_ContactForm' ) && $wpcf7_contact_form->is_posted() ) {
			if ( isset( $_POST['_wpcf7_mail_sent'] ) && $_POST['_wpcf7_mail_sent']['ok'] ) {
				$value = '';
			} else {
				$value = stripslashes_deep( $_POST[$name] );
			}
		} else {
			if ( isset( $_GET[ $name ] ) ) {
				$value = stripslashes_deep( $_GET[ $name ] );
			}
		}

		$filter        = '';
		$filter_args   = array();
		$filter_string = '';
		if ( isset( $values[0] ) ) {
			$filter_string = $values[0];
		}

		if ( '' != $filter_string ) {
			$filter_parts = explode( ' ', $filter_string );
			$filter = trim( $filter_parts[0] );
			$count = count( $filter_parts );
			for ( $i = 1; $i < $count; $i++ ) {
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

		if ( '' === $filter ) {
			return $filter;
		}

		$field_options = apply_filters( 'wpcf7_dd_' . $name, array(), $filter_args );

		if ( ! is_array( $field_options ) || ! count( $field_options ) ) {
			return '';
		}

		$validation_error = '';
		if ( is_a( $wpcf7_contact_form, 'WPCF7_ContactForm' )) {
			$validation_error = $wpcf7_contact_form->validation_error( $name );
		}
		$invalid = 'false';
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
						foreach ( $field_options as $option_label => $option_value ) {
							?>
								<option value="<?php echo esc_attr( $option_value ); ?>"<?php
											if ( ! $use_default ) {
												if ( ! is_array( $value ) && $value == $option_value ) {
													echo ' selected="selected"';
												} elseif ( is_array( $value ) && in_array( $option_value, $value ) ) {
													echo ' selected="selected"';
												}
											} else {
												if ( in_array( $option_value, $default ) ) {
													echo ' selected="selected"';
												}
											}
										?>><?php echo esc_html( $option_label ); ?></option>
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

	public function validate( $result, $tag ) {
		$tag_o = $tag;
		if (is_a($tag, 'WPCF7_FormTag')) {
			$tag = (array)$tag;
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
		if (!$value_found) {
			$result->invalidate($tag_o, wpcf7_get_message('invalid_required'));
			//$result['valid'] = false;
			//$result['reason'][$name] = $wpcf7_contact_form->message('invalid_required');
		}
		return $result;
	} // end public function validation_filter

	public function add_tag_generator() {
		wpcf7_add_tag_generator(
			'dynamicdropdown',
			__( 'Dynamic Dropdown', 'wpcf7' ),
			'wpcf7-tg-pane-dynamicdropdown',
			array( $this, 'add_tag_panel' )
		);
	}

	public function add_tag_panel($form, $args = '') {
		// output the code for CF7 tag generator
		$type='dynamicdropdown';
		if (class_exists('WPCF7_TagGenerator')) {
			// tag generator for CF7 >= v4.2
			$args = wp_parse_args( $args, array() );
			$desc = __('Generate a form-tag for a Dynamic Select field. For more details, see %s.');
			$desc_link = '<a href="https://wordpress.org/plugins/contact-form-7-dynamic-select-extension/" target="_blank">'.__( 'Contact Form 7 - Dynamic Select Extension').'</a>';
			?>
				<div class="control-box">
					<fieldset>
						<legend><?php echo sprintf(esc_html($desc), $desc_link); ?></legend>
						<table class="form-table">
							<tbody>
								<tr>
									<th scope="row">
										<label for="<?php
												echo esc_attr($args['content'].'-required'); ?>"><?php
												echo esc_html(__('Required field', 'contact-form-7')); ?></label>
									</th>
									<td>
										<input type="checkbox" name="required" id="<?php
												echo esc_attr($args['content'].'-required' ); ?>" />
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
								<!--
								<tr>
									<th scope="row">
										<label for="<?php
												echo esc_attr($args['content'].'-returnlabels'); ?>"><?php
												echo esc_html(__('Return Label(s)', 'contact-form-7')); ?></label>
									</th>
									<td>
										<input type="checkbox" name="returnlabels" class="returnlabelsvalue option" id="<?php
												echo esc_attr($args['content'].'-returnlabel' ); ?>" />
										Check this box to return labels instead of values.
									</td>
								</tr>
								-->
							</tbody>
						</table>
					</fieldset>
				</div>
				<div class="insert-box">
					<input type="text" name="dynamicdropdown" class="tag code" readonly="readonly" onfocus="this.select()" />
					<div class="submitbox">
						<input type="button" class="button button-primary insert-tag" value="<?php
								echo esc_attr(__('Insert Tag', 'contact-form-7')); ?>" />
					</div>
				</div>
			<?php
		} else {
			// tag generator for CF7 <v4.2
			// but modified slightly so it will still work with with >= v4.2
			?>
				<div id="wpcf7-tg-pane-<?php echo $type; ?>" class="control-box">
					<form action="">
						<table>
							<tr>
								<td>
									<input type="checkbox" name="required" />
									<?php echo esc_html(__('Required field?', 'contact-form-7')); ?>
								</td>
							</tr>
							<tr>
								<td>
									<?php echo esc_html(__( 'Name', 'contact-form-7')); ?><br />
									<input type="text" name="name" class="tg-name oneline" />
								</td>
								<td></td>
							</tr>
						</table>
						<table>
							<tr>
								<td>
									<code>id</code> (<?php echo esc_html(__('optional', 'contact-form-7')); ?>)<br />
									<input type="text" name="id" class="idvalue oneline option" />
								</td>
								<td>
									<code>class</code> (<?php echo esc_html(__('optional', 'contact-form-7')); ?>)<br />
									<input type="text" name="class" class="classvalue oneline option" />
								</td>
							</tr>
							<tr>
								<td>
									<?php echo esc_html(__('Filter')); ?><br />
										<input type="text" name="values" class="oneline" /><br />
										<?php echo esc_html(__('You can enter any filter. Use single quotes only. See docs &amp; examples.')); ?>
								</td>
								<td>
									<br />
									<input class="option" type="checkbox" name="multiple">
									Allow multiple selections?
								</td>
							</tr>
						</table>
						<div class="tg-tag">
							<?php echo esc_html(__('Copy this code and paste it into the form left.', 'contact-form-7')); ?><br />
							<input type="text" name="<?php
									echo $type; ?>" class="tag" readonly="readonly" onfocus="this.select()" style="width:100%;" />
						</div>
						<div class="tg-mail-tag">
							<?php echo esc_html(__('And, put this code into the Mail fields below.', 'contact-form-7')); ?><br />
							<input type="text" class="mail-tag" readonly="readonly" onfocus="this.select()" style="width:100%;" />
						</div>
					</form>
				</div>
			<?php
		}
	} // end public function tag_pane

} // end class cf7_dynamic_select

new WPCF7_Dynamic_Dropdown();

endif;