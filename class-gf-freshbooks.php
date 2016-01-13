<?php

GFForms::include_feed_addon_framework();

class GFFreshBooks extends GFFeedAddOn {

	protected $_version = GF_FRESHBOOKS_VERSION;
	protected $_min_gravityforms_version = '1.8.17';
	protected $_slug = 'gravityformsfreshbooks';
	protected $_path = 'gravityformsfreshbooks/freshbooks.php';
	protected $_full_path = __FILE__;
	protected $_url = 'http://www.gravityforms.com';
	protected $_title = 'FreshBooks Add-On';
	protected $_short_title = 'FreshBooks';

	// Members plugin integration
	protected $_capabilities = array( 'gravityforms_freshbooks', 'gravityforms_freshbooks_uninstall' );

	// Permissions
	protected $_capabilities_settings_page = 'gravityforms_freshbooks';
	protected $_capabilities_form_settings = 'gravityforms_freshbooks';
	protected $_capabilities_uninstall = 'gravityforms_freshbooks_uninstall';
	protected $_enable_rg_autoupgrade = true;

	private static $_instance = null;

	public static function get_instance() {
		if ( self::$_instance == null ) {
			self::$_instance = new GFFreshBooks();
		}

		return self::$_instance;
	}

	public function init_admin() {
		parent::init_admin();

		$this->ensure_upgrade();

		add_filter( 'gform_addon_navigation', array( $this, 'maybe_create_menu' ) );
	}

	public function init_frontend() {
		parent::init_frontend();

		add_action( 'gform_post_payment_completed', array( $this, 'create_payment' ), 10, 2 );
	}

	//------- AJAX FUNCTIONS ------------------//

	public function init_ajax() {
		parent::init_ajax();

		add_action( 'wp_ajax_gf_dismiss_freshbooks_menu', array( $this, 'ajax_dismiss_menu' ) );

	}

	public function maybe_create_menu( $menus ) {
		$current_user            = wp_get_current_user();
		$dismiss_freshbooks_menu = get_metadata( 'user', $current_user->ID, 'dismiss_freshbooks_menu', true );
		if ( $dismiss_freshbooks_menu != '1' ) {
			$menus[] = array( 'name'       => $this->_slug,
			                  'label'      => $this->get_short_title(),
			                  'callback'   => array( $this, 'temporary_plugin_page' ),
			                  'permission' => $this->_capabilities_form_settings
			);
		}

		return $menus;
	}

	public function ajax_dismiss_menu() {

		$current_user = wp_get_current_user();
		update_metadata( 'user', $current_user->ID, 'dismiss_freshbooks_menu', '1' );
	}

	public function temporary_plugin_page() {
		$current_user = wp_get_current_user();
		?>
		<script type="text/javascript">
			function dismissMenu() {
				jQuery('#gf_spinner').show();
				jQuery.post(ajaxurl, {
						action: "gf_dismiss_freshbooks_menu"
					},
					function (response) {
						document.location.href = '?page=gf_edit_forms';
						jQuery('#gf_spinner').hide();
					}
				);

			}
		</script>

		<div class="wrap about-wrap">
			<h1><?php _e( 'FreshBooks Add-On v2.0', 'gravityformsfreshbooks' ) ?></h1>

			<div
				class="about-text"><?php _e( 'Thank you for updating! The new version of the Gravity Forms FreshBooks Add-On makes changes to how you manage your FreshBooks integration.', 'gravityformsfreshbooks' ) ?></div>
			<div class="changelog">
				<hr/>
				<div class="feature-section col two-col">
					<div class="col-1">
						<h3><?php _e( 'Manage FreshBooks Contextually', 'gravityformsfreshbooks' ) ?></h3>

						<p><?php _e( 'FreshBooks Feeds are now accessed via the FreshBooks sub-menu within the Form Settings for the Form with which you would like to integrate FreshBooks.', 'gravityformsfreshbooks' ) ?></p>
					</div>
					<div class="col-2 last-feature">
						<img src="http://gravityforms.s3.amazonaws.com/webimages/AddonNotice/NewFreshBooks2.png">
					</div>
				</div>

				<hr/>

				<form method="post" id="dismiss_menu_form" style="margin-top: 20px;">
					<input type="checkbox" name="dismiss_freshbooks_menu" value="1" onclick="dismissMenu();">
					<label><?php _e( 'I understand this change, dismiss this message!', 'gravityformsfreshbooks' ) ?></label>
					<img id="gf_spinner" src="<?php echo GFCommon::get_base_url() . '/images/spinner.gif'?>"
					     alt="<?php _e( 'Please wait...', 'gravityformsfreshbooks' ) ?>" style="display:none;"/>
				</form>

			</div>
		</div>
	<?php
	}

	// ------- Plugin settings -------
	public function plugin_settings_fields() {
		return array(
			array(
				'title'       => __( 'FreshBooks Account Information', 'gravityformsfreshbooks' ),
				'description' => sprintf( __( 'FreshBooks is a fast, painless way to track time and invoice your clients. Use Gravity Forms to collect customer information and automatically create FreshBooks client profiles as well as invoices and estimates. If you don\'t have a FreshBooks account, you can %1$s sign up for one here.%2$s', 'gravityformsfreshbooks' ),
					'<a href="http://www.freshbooks.com/" target="_blank">', '</a>' ),
				'fields'      => array(
					array(
						'name'              => 'siteName',
						'label'             => __( 'Site Name', 'gravityformsfreshbooks' ),
						'type'              => 'site_name',
						'class'             => 'small',
						'feedback_callback' => array( $this, 'is_valid_credentials' )
					),
					array(
						'name'              => 'authToken',
						'label'             => __( 'Authorization Token', 'gravityformsfreshbooks' ),
						'type'              => 'text',
						'class'             => 'medium',
						'feedback_callback' => array( $this, 'is_valid_credentials' )
					),
				)
			),
		);
	}

	public function settings_site_name( $field, $echo = true ) {

		$site_name_field = $this->settings_text( $field, false );

		if ( $echo ) {
			echo $site_name_field . '.freshbooks.com';
		}

		return $site_name_field . '.freshbooks.com';

	}

	//-------- Form Settings ---------
	public function feed_edit_page( $form, $feed_id ) {

		// ensures valid credentials were entered in the settings page
		if ( ! $this->is_valid_credentials() ) {
			?>
			<div><?php echo sprintf( __( 'We are unable to login to FreshBooks with the provided username and password. Please make sure they are valid in the %sSettings Page%s', 'gravityformsfreshbooks' ),
					"<a href='" . esc_url( $this->get_plugin_settings_url() ) . "'>", '</a>' ); ?>
			</div>

			<?php
			return;
		}

		parent::feed_edit_page( $form, $feed_id );
	}


	public function feed_settings_fields() {
		return array(
			array(
				'title'       => __( 'Freshbooks Feed Settings', 'gravityformsfreshbooks' ),
				'description' => '',
				'fields'      => array(
					array(
						'name'     => 'feedName',
						'label'    => __( 'Name', 'gravityformsfreshbooks' ),
						'type'     => 'text',
						'required' => true,
						'class'    => 'medium',
						'tooltip'  => '<h6>' . __( 'Name', 'gravityformsfreshbooks' ) . '</h6>' . __( 'Enter a feed name to uniquely identify this setup.', 'gravityformsfreshbooks' ),
					),
				),
			),
			array(
				'title'       => __( 'Client Settings', 'gravityformsfreshbooks' ),
				'description' => '',
				'fields'      => array(
					array(
						'name'     => 'email',
						'label'    => __( 'Email', 'gravityformsfreshbooks' ),
						'type'     => 'email',
						'required' => true,
					),
					array(
						'name'     => 'firstName',
						'label'    => __( 'First Name', 'gravityformsfreshbooks' ),
						'type'     => 'select',
						'choices'  => $this->get_field_map_choices( rgget( 'id' ) ),
						'required' => true,
					),
					array(
						'name'     => 'lastName',
						'label'    => __( 'Last Name', 'gravityformsfreshbooks' ),
						'type'     => 'select',
						'choices'  => $this->get_field_map_choices( rgget( 'id' ) ),
						'required' => true,
					),
					array(
						'name'     => 'organization',
						'label'    => __( 'Organization', 'gravityformsfreshbooks' ),
						'type'     => 'select',
						'choices'  => $this->get_field_map_choices( rgget( 'id' ) ),
						'required' => false,
					),
					array(
						'name'     => 'address',
						'label'    => __( 'Address', 'gravityformsfreshbooks' ),
						'type'     => 'select',
						'choices'  => $this->get_field_map_choices( rgget( 'id' ) ),
						'required' => false,
					),
					array(
						'name'     => 'phone',
						'label'    => __( 'Phone', 'gravityformsfreshbooks' ),
						'type'     => 'select',
						'choices'  => $this->get_field_map_choices( rgget( 'id' ) ),
						'required' => false,
					),
					array(
						'name'     => 'fax',
						'label'    => __( 'Fax', 'gravityformsfreshbooks' ),
						'type'     => 'select',
						'choices'  => $this->get_field_map_choices( rgget( 'id' ) ),
						'required' => false,
					),
					array(
						'name'  => 'notes',
						'label' => __( 'Notes', 'gravityformsfreshbooks' ),
						'type'  => 'textarea',
						'class' => 'medium merge-tag-support mt-position-right',
					),
				),
			),
			array(
				'title'       => __( 'Invoice/Estimate Settings', 'gravityformsfreshbooks' ),
				'description' => '',
				'fields'      => array(
					array(
						'name'          => 'alsoCreate',
						'label'         => __( 'Also Create', 'gravityformsfreshbooks' ),
						'type'          => 'radio',
						'choices'       => array(
							array( 'id' => 'invoice', 'label' => 'invoice', 'value' => 'invoice' ),
							array( 'id' => 'estimate', 'label' => 'estimate', 'value' => 'estimate' ),
							array( 'id' => 'neither', 'label' => 'neither', 'value' => 'neither' ),
						),
						'horizontal'    => true,
						'default_value' => 'neither',
						'tooltip'       => '<h6>' . __( 'Also Create', 'gravityformsfreshbooks' ) . '</h6>' . __( 'Select invoice or estimate to automatically create them in your FreshBooks account in addition to creating the client.', 'gravityformsfreshbooks' ),
						'onchange'      => 'jQuery(this).parents("form").submit();',
					),
					array(
						'name'       => 'sendByEmail',
						'label'      => '',
						'type'       => 'checkbox',
						'dependency' => array( 'field' => 'alsoCreate', 'values' => array( 'invoice', 'estimate' ) ),
						'choices'    => array(
							array(
								'label'   => __( 'Send Invoice/Estimate By Email', 'gravityformsfreshbooks' ),
								'name'    => 'sendByEmail',
								'tooltip' => '<h6>' . __( 'Send Invoice/Estimate By Email', 'gravityformsfreshbooks' ) . '</h6>' . __( 'By checking this option, the invoice/estimate will automatically be emailed to the client, instead of left in Draft form.', 'gravityformsfreshbooks' ),
							),
						)
					),
					array(
						'name'       => 'createPayment',
						'label'      => '',
						'type'       => 'checkbox',
						'dependency' => array( $this, 'maybe_show_create_payment' ),
						'choices'    => array(
							array(
								'label'   => __( 'Mark Invoice as Paid', 'gravityformsfreshbooks' ),
								'name'    => 'createPayment',
								'tooltip' => '<h6>' . __( 'Mark Invoice as Paid', 'gravityformsfreshbooks' ) . '</h6>' . __( 'By checking this option, once the user has completed his/her payment transaction, a Freshbooks payment for the invoice amount will automatically be created, changing the invoice status to Paid.', 'gravityformsfreshbooks' ),
							),
						)
					),
					array(
						'name'       => 'poNumber',
						'label'      => __( 'PO Number', 'gravityformsfreshbooks' ),
						'type'       => 'select',
						'choices'    => $this->get_field_map_choices( rgget( 'id' ) ),
						'tooltip'    => '<h6>' . __( 'PO Number', 'gravityformsfreshbooks' ) . '</h6>' . __( 'Map the PO number to the appropriate form field. The data will be truncated to 25 characters per a requirement by FreshBooks.', 'gravityformsfreshbooks' ),
						'dependency' => array( 'field' => 'alsoCreate', 'values' => array( 'invoice', 'estimate' ) ),
					),
					array(
						'name'                => 'discount',
						'label'               => __( 'Discount', 'gravityformsfreshbooks' ),
						'type'                => 'discount',
						'tooltip'             => '<h6>' . __( 'Discount', 'gravityformsfreshbooks' ) . '</h6>' . __( 'When creating an invoice or estimate, this discount will be applied to the total invoice/estimate cost.', 'gravityformsfreshbooks' ),
						'dependency'          => array(
							'field'  => 'alsoCreate',
							'values' => array( 'invoice', 'estimate' ),
						),
						'validation_callback' => array( $this, 'validate_discount' ),
					),
					array(
						'name'       => 'lineItems',
						'label'      => __( 'Line Items', 'gravityformsfreshbooks' ),
						'type'       => 'line_items',
						'tooltip'    => '<h6>' . __( 'Line Items', 'gravityformsfreshbooks' ) . '</h6>' . __( 'Create one or more line item(s) for your invoice or estimate.', 'gravityformsfreshbooks' ),
						'onchange'   => 'ResetLineItemValues(this);jQuery(this).parents("form").submit();',
						'dependency' => array( 'field' => 'alsoCreate', 'values' => array( 'invoice', 'estimate' ) ),
					),
					array(
						'name'       => 'fixedCosts',
						'type'       => 'fixed_costs',
						'label'      => '',
						'dependency' => array( 'field' => 'lineItems', 'values' => array( 'fixed', 'dynamic' ) ),
					),
					array(
						'name'       => 'notes2',
						'label'      => __( 'Notes', 'gravityformsfreshbooks' ),
						'type'       => 'textarea',
						'class'      => 'medium merge-tag-support mt-position-right',
						'dependency' => array( 'field' => 'alsoCreate', 'values' => array( 'invoice', 'estimate' ) ),
					),
					array(
						'name'       => 'terms',
						'label'      => __( 'Terms', 'gravityformsfreshbooks' ),
						'type'       => 'textarea',
						'class'      => 'medium',
						'dependency' => array( 'field' => 'alsoCreate', 'values' => array( 'invoice', 'estimate' ) ),
					),
				),
			),
			array(
				'title'       => __( 'Other Settings', 'gravityformsfreshbooks' ),
				'description' => '',
				'fields'      => array(
					array(
						'name'    => 'optin',
						'label'   => __( 'Export Condition', 'gravityformsfreshbooks' ),
						'type'    => 'feed_condition',
						'tooltip' => '<h6>' . __( 'Export Condition', 'gravityformsfreshbooks' ) . '</h6>' . __( 'When the export condition is enabled, form submissions will only be exported to FreshBooks when the condition is met. When disabled all form submissions will be exported.', 'gravityformsfreshbooks' )
					),
				),
			),

		);

	}

	/**
	 * Only show the createPayment setting if the form has an active Product & Services type feed and if an invoice is being created.
	 *
	 * @return bool
	 */
	public function maybe_show_create_payment() {

		return $this->get_setting( 'alsoCreate' ) == 'invoice' && $this->has_product_feed();
	}

	public function settings_email( $field, $echo = true ) {

		$field['type']    = 'select';
		$field['choices'] = $this->get_field_map_choices( rgget( 'id' ) );
		$html             = $this->settings_select( $field, false );
		$html             = str_replace( '<div', '<span', $html );
		$html             = str_replace( '</div>', '</span>', $html );

		$field2            = array();
		$field2['type']    = 'checkbox';
		$field2['name']    = 'updateClient';
		$tooltip_content   = '<h6>' . __( 'Update existing client', 'gravityformsfreshbooks' ) . '</h6>' . __( 'When this box is checked and a client already exists in your FreshBooks account, it will be updated with the newly entered information. When this box is unchecked, a new client will be created for every form submission.', 'gravityformsfreshbooks' );
		$options           = array(
			array(
				'label'   => __( 'Update an existing client if email addresses match', 'gravityformsfreshbooks' ),
				'name'    => 'updateClient',
				'tooltip' => $tooltip_content,
			),
		);
		$field2['choices'] = $options;
		$html2             = $this->settings_checkbox( $field2, false );

		if ( $echo ) {
			echo $html . $html2;
		}

		return $html . $html2;

	}

	public function settings_discount( $field, $echo = true ) {

		$field['type']  = 'text';
		$field['class'] = 'small';
		$html           = $this->settings_text( $field, false );

		if ( $echo ) {
			echo $html . '<span style="margin-left:10px">%</span>';
		}

		return $html . '<span style="margin-left:10px">%</span>';

	}

	public function settings_line_items( $field, $echo = true ) {

		$field['type']       = 'radio';
		$field['horizontal'] = true;

		$fixed_tooltip_content   = '<h6>' . __( 'Fixed Cost and Quantity', 'gravityformsfreshbooks' ) . '</h6>' . __( 'Enter fixed cost and quantity for your line items.', 'gravityformsfreshbooks' );
		$pricing_tooltip_content = '<h6>' . __( 'Use Pricing Fields', 'gravityformsfreshbooks' ) . '</h6>' . __( 'Use Product fields on form as line items.', 'gravityformsfreshbooks' );
		$dynamic_tooltip_content = '<h6>' . __( 'Dynamic Cost and Quantity', 'gravityformsfreshbooks' ) . '</h6>' . __( 'Allow line item cost and quantity to be populated from a form field.', 'gravityformsfreshbooks' );
		$options                 = array(
			array(
				'label'   => __( 'Fixed Costs and Quantities', 'gravityformsfreshbooks' ),
				'id'      => 'fixed',
				'value'   => 'fixed',
				'tooltip' => $fixed_tooltip_content,
			),
			array(
				'label'   => __( 'Use Pricing Fields', 'gravityformsfreshbooks' ),
				'id'      => 'pricing',
				'value'   => 'pricing',
				'tooltip' => $pricing_tooltip_content,
			),
			array(
				'label'   => $this->enable_dynamic_costs() ? __( 'Pull Costs and Quantities from Form Fields', 'gravityformsfreshbooks' ) : '',
				'id'      => 'dynamic',
				'value'   => 'dynamic',
				'tooltip' => $this->enable_dynamic_costs() ? $dynamic_tooltip_content : '',
				'style'   => $this->enable_dynamic_costs() ? 'display:inline-block' : 'display:none',
			),

		);
		$field['choices']        = $options;
		$field['default_value']  = 'pricing';

		$html = $this->settings_radio( $field, false );
		//script needed to clear out values in line items when switching across the different types
		$script = '<script type="text/javascript">
			function ResetLineItemValues(element){
				line_item_description = jQuery("[name^=\'_gaddon_setting_description\']");
				jQuery.each(line_item_description, function()
						{
							//remove value
							line_item_description.val("");
						}
				);

				line_item_cost = jQuery("[name^=\'_gaddon_setting_cost\']");
				jQuery.each(line_item_cost, function()
						{
							//remove value
							line_item_cost.val("");
						}
				);

				line_item_quantity = jQuery("[name^=\'_gaddon_setting_quantity\']");
				jQuery.each(line_item_quantity, function()
						{
							//remove value
							line_item_quantity.val("");
						}
				);

			}
			</script>';

		if ( $echo ) {
			echo $script . $html;
		}

		return $script . $html;

	}

	public function enable_dynamic_costs() {
		$enable_dynamic = apply_filters( 'gform_freshbooks_enable_dynamic_field_mapping', false );

		return $enable_dynamic;
	}

	public function settings_fixed_costs( $field, $echo = true ) {

		$script = '<script type="text/javascript">
			function AddLineItem(element){
				//get number of line items so the id/name of new row has the correct array index
				line_items = jQuery("[name^=\'_gaddon_setting_description\']");
				line_item_count = line_items.length;

				var new_row = "<tr class=\'gf_freshbooks_lineitem_row gf_freshbooks_new_row\'>" + jQuery(\'.gf_freshbooks_lineitem_row:first\').html() + "</tr>";
				jQuery(element).parents(\'.gf_freshbooks_lineitem_row\').after(new_row);
				jQuery(\'.gf_freshbooks_new_row input, .gf_freshbooks_new_row select\').val(\'\');

				//because the first row is copied, the id/name of the fields need to be updated using the correct array index, otherwise you have multiple rows using array index zero and all is broken
				//update id/name for item field
				line_item = jQuery("[name^=\'_gaddon_setting_item[0]\']:last"); //get the last item in the array, will be the row just added
				line_item.attr("name", "_gaddon_setting_item[" + line_item_count + "]");
				line_item.attr("id", "item[" + line_item_count + "]");

				//update id/name for description field
				line_item_description = jQuery("[name^=\'_gaddon_setting_description[0]\']:last");
				line_item_description.attr("name", "_gaddon_setting_description[" + line_item_count + "]");
				line_item_description.attr("id", "description[" + line_item_count + "]");

				//update id/name for cost field
				line_item_cost = jQuery("[name^=\'_gaddon_setting_cost[0]\']:last");
				line_item_cost.attr("name", "_gaddon_setting_cost[" + line_item_count + "]");
				line_item_cost.attr("id", "cost[" + line_item_count + "]");

				//update id/name for quantity field
				line_item_quantity = jQuery("[name^=\'_gaddon_setting_quantity[0]\']:last");
				line_item_quantity.attr("name", "_gaddon_setting_quantity[" + line_item_count + "]");
				line_item_quantity.attr("id", "quantity[" + line_item_count + "]");

				jQuery(\'.gf_freshbooks_new_row\').removeClass(\'gf_freshbooks_new_row\');
			}

			function DeleteLineItem(element){

				//don\'t allow deleting the last item
				if(jQuery(\'.gf_freshbooks_lineitem_row\').length == 1)
					return;

				jQuery(element).parents(\'.gf_freshbooks_lineitem_row\').remove();
			}
			</script>';


		$html = "<table class='gf_freshbooks_lineitem_table'>
					<tr>
						<td>" . __( 'Line Item', 'gravityformsfreshbooks' ) . '</td>
						<td>' . __( 'Description', 'gravityformsfreshbooks' ) . '</td>
						<td>' . __( 'Unit Cost', 'gravityformsfreshbooks' ) . '</td>
						<td>' . __( 'Quantity', 'gravityformsfreshbooks' ) . '</td>
					</tr>';

		$items = $this->get_setting( 'item' );

		$feed         = $this->get_current_feed(); //needed to check fixed cost setting
		$form         = $this->get_current_form(); //needed to build fields drop down
		$show_dynamic = false;
		if ( ! empty( $_POST['_gaddon_setting_lineItems'] ) ) {
			if ( $_POST['_gaddon_setting_lineItems'] == 'dynamic' ) {
				$show_dynamic = true;
			}
		} else {
			if ( rgar( $feed['meta'], 'lineItems' ) == 'dynamic' ) {
				$show_dynamic = true;
			}
		}

		//adding one blank item
		if ( ! $items ) {
			$items = array( array() );
			foreach ( $items as $item ) {

				$html .= "<tr class='gf_freshbooks_lineitem_row'>";


				$html .= '<td>' . $this->settings_select( array(
						'name'    => 'item[0]',
						'type'    => 'select',
						'choices' => $this->get_freshbooks_items()

					), false ) . '</td>';

				$html .= '<td>' . $this->settings_text( array(
						'name'  => 'description[0]',
						'type'  => 'text',
						'class' => 'small',
					), false ) . '</td>';

				if ( $show_dynamic ) {
					//make costs drop down
					$html .= '<td>' . $this->settings_select( array(
							'name'    => 'cost[0]',
							'type'    => 'select',
							'choices' => $this->get_form_fields_as_choices( $form ),
						), false ) . '</td>';

					//make quantity drop down
					$html .= '<td>' . $this->settings_select( array(
							'name'    => 'quantity[0]',
							'type'    => 'select',
							'choices' => $this->get_form_fields_as_choices( $form ),
						), false ) . '</td>';
				} else {
					$html .= '<td>' . $this->settings_text( array(
							'name'  => 'cost[0]',
							'type'  => 'text',
							'class' => 'small',
						), false ) . '</td>';

					$html .= '<td>' . $this->settings_text( array(
							'name'  => 'quantity[0]',
							'type'  => 'text',
							'class' => 'small',
						), false ) . '</td>';
				}
				$html .= "<td>
							<input type='image' src='" . $this->get_base_url() . "/images/remove.png' onclick='DeleteLineItem(this); return false;' alt='Delete' title='Delete' />
							<input type='image' src='" . $this->get_base_url() . "/images/add.png' onclick='AddLineItem(this); return false;' alt='Add line item' title='Add line item' />
						  </td></tr>";

			}
		} else {
			$i            = 0;
			$descriptions = $this->get_setting( 'description' );
			$costs        = $this->get_setting( 'cost' );
			$quantities   = $this->get_setting( 'quantity' );
			$choices      = $this->get_freshbooks_items();
			foreach ( $items as $item ) {

				$html .= "<tr class='gf_freshbooks_lineitem_row'>";

				$html .= '<td>' . $this->settings_select( array(
						'name'          => 'item[' . $i . ']',
						'type'          => 'select',
						'choices'       => $choices,
						'default_value' => $item,

					), false ) . '</td>';

				$html .= '<td>' . $this->settings_text( array(
						'name'  => 'description[' . $i . ']',
						'type'  => 'text',
						'class' => 'small',
						'value' => $descriptions[ $i ],
					), false ) . '</td>';

				if ( $show_dynamic ) {
					//make costs drop down
					$html .= '<td>' . $this->settings_select( array(
							'name'          => 'cost[' . $i . ']',
							'type'          => 'select',
							'choices'       => $this->get_form_fields_as_choices( $form ),
							'default_value' => is_array( $costs ) ? $costs[ $i ] : '',
						), false ) . '</td>';

					//make quantity drop down
					$html .= '<td>' . $this->settings_select( array(
							'name'          => 'quantity[' . $i . ']',
							'type'          => 'select',
							'choices'       => $this->get_form_fields_as_choices( $form ),
							'default_value' => is_array( $quantities ) ? $quantities[ $i ] : '',
						), false ) . '</td>';
				} else {
					$html .= '<td>' . $this->settings_text( array(
							'name'          => 'cost[' . $i . ']',
							'type'          => 'text',
							'class'         => 'small',
							'value'         => is_array( $costs ) ? $costs[ $i ] : '',
							'default_value' => is_array( $costs ) ? $costs[ $i ] : '',
						), false ) . '</td>';

					$html .= '<td>' . $this->settings_text( array(
							'name'          => 'quantity[' . $i . ']',
							'type'          => 'text',
							'class'         => 'small',
							'value'         => is_array( $quantities ) ? $quantities[ $i ] : '',
							'default_value' => is_array( $quantities ) ? $quantities[ $i ] : '',
						), false ) . '</td>';
				}
				$html .= "<td>
							<input type='image' src='" . $this->get_base_url() . "/images/remove.png' onclick='DeleteLineItem(this); return false;' alt='Delete' title='Delete' />
							<input type='image' src='" . $this->get_base_url() . "/images/add.png' onclick='AddLineItem(this); return false;' alt='Add line item' title='Add line item' />
						  </td></tr>";
				$i ++;
			}
		}

		$html .= '</table>';

		if ( $echo ) {
			echo $script . $html;
		}

		return $script . $html;
	}

	public function get_freshbooks_items() {

		//non persistent cache
		$fb_items = GFCache::get( 'freshbooks_items' );

		if ( empty( $fb_items ) ) {
			$this->init_api();
			$items = new FreshBooks_Item();

			$result      = array();
			$result_info = array();

			$current_page = 1;

			$fb_items = array();
			do {
				$items->listing( $result, $result_info, $current_page, 100 );
				$pages = $result_info['pages'];

				foreach ( $result as $line_item ) {
					$fb_items[] = array( 'value' => $line_item->itemId, 'label' => $line_item->name );
				}

				$current_page ++;
			} while ( $current_page <= $pages );

			GFCache::set( 'freshbooks_items', $fb_items );
		}

		return $fb_items;
	}

	// ------- Plugin list page -------
	public function feed_list_columns() {
		return array(
			'feedName'     => __( 'Name', 'gravityformsfreshbooks' ),
			'listInvoice'  => __( 'Invoice', 'gravityformsfreshbooks' ),
			'listEstimate' => __( 'Estimate', 'gravityformsfreshbooks' )
		);
	}

	public function get_column_value_listInvoice( $feed ) {
		return $feed['meta']['alsoCreate'] == 'invoice' ? "<img src='" . $this->get_base_url() . "/images/tick.png' />" : '';
	}

	public function get_column_value_listEstimate( $feed ) {
		return $feed['meta']['alsoCreate'] == 'estimate' ? "<img src='" . $this->get_base_url() . "/images/tick.png' />" : '';
	}

	private function get_api() {
		if ( ! class_exists( 'MCAPI' ) ) {
			require_once( 'api/MCAPI.class.php' );
		}

		//global freshbooks settings
		$settings = get_option( 'gf_freshbooks_settings' );
		if ( ! empty( $settings['username'] ) && ! empty( $settings['password'] ) ) {
			$api = new MCAPI( $settings['username'], $settings['password'] );

			if ( $api->errorCode ) {
				return null;
			}
		}

		return $api;
	}

	public function has_pricing_field( $form ) {
		$fields = GFCommon::get_fields_by_type( $form, array( 'product' ) );

		return count( $fields ) > 0;
	}

	public function process_feed( $feed, $entry, $form ) {

		if ( ! $this->is_valid_credentials() ) {
			return;
		}

		$this->export_feed( $entry, $form, $feed );

	}

	public function export_feed( $entry, $form, $settings ) {

		$name_fields = array();
		foreach ( $form['fields'] as $field ) {
			if ( RGFormsModel::get_input_type( $field ) == 'name' ) {
				$name_fields[] = $field;
			}
		}

		//Creating client
		$this->log_debug( __METHOD__ . '(): Checking to see if client exists or a new client needs to be created.' );
		$client = $this->get_client( $form, $entry, $settings, $name_fields );

		//if client could not be created, ignore invoice and estimate
		if ( ! $client ) {
			$this->log_debug( __METHOD__ . '(): Unable to create client, not creating invoice/estimate.' );

			return;
		}

		$type = rgars( $settings, 'meta/alsoCreate' );
		if ( $type == 'invoice' ) {
			$invoice_estimate = new FreshBooks_Invoice();
		} elseif ( $type == 'estimate' ) {
			$invoice_estimate = new FreshBooks_Estimate();
		} else {
			return;
		} //don't create invoice or estimate

		if ( ! empty( $settings['meta']['poNumber'] ) ) {
			$po_number = esc_html( $this->get_entry_value( $settings['meta']['poNumber'], $entry, $name_fields ) );
			//trim po_number to 25 characters because FreshBooks only allows 25 and more will cause invoice/estimate to not be created
			$invoice_estimate->poNumber = substr( $po_number, 0, 25 );
		}
		$invoice_estimate->discount = $settings['meta']['discount'];
		$invoice_estimate->notes    = esc_html( GFCommon::replace_variables( $settings['meta']['notes2'], $form, $entry, false, false, false, 'text' ) );
		$invoice_estimate->terms    = esc_html( $settings['meta']['terms'] );

		$total = 0;
		$lines = array();
		if ( $settings['meta']['lineItems'] == 'pricing' ) {

			$this->log_debug( __METHOD__ . '(): Creating line items based on pricing fields.' );

			//creating line items based on pricing fields
			$products = GFCommon::get_product_fields( $form, $entry, true, false );

			foreach ( $products['products'] as $product ) {
				$product_name = $product['name'];
				$price        = GFCommon::to_number( $product['price'] );
				if ( ! empty( $product['options'] ) ) {
					$product_name .= ' (';
					$options = array();
					foreach ( $product['options'] as $option ) {
						$price += GFCommon::to_number( $option['price'] );
						$options[] = $option['option_name'];
					}
					$product_name .= implode( ', ', $options ) . ')';
				}
				$subtotal = floatval( $product['quantity'] ) * $price;
				$total += $subtotal;

				$lines[] = array(
					'name'        => esc_html( $product['name'] ),
					'description' => esc_html( $product_name ),
					'unitCost'    => $price,
					'quantity'    => $product['quantity'],
					'amount'      => $subtotal,
				);
			}
			//adding shipping if form has shipping
			if ( ! empty( $products['shipping']['name'] ) ) {
				$total += floatval( $products['shipping']['price'] );
				$lines[] = array(
					'name'        => esc_html( $products['shipping']['name'] ),
					'description' => esc_html( $products['shipping']['name'] ),
					'unitCost'    => $products['shipping']['price'],
					'quantity'    => 1,
					'amount'      => $products['shipping']['price'],
				);
			}
		} else {
			$i = 0;

			$this->log_debug( __METHOD__ . '(): Creating line items based on fixed costs and quantities.' );

			//creating line items based on fixed cost or mapped fields
			$send_item_id = apply_filters( 'gform_freshbooks_send_item_id_for_fixed_dynamic', false );
			foreach ( $settings['meta']['item'] as $item ) {
				$cost = $settings['meta']['lineItems'] == 'fixed' ? $settings['meta']['cost'][ $i ] : $this->get_entry_value( $settings['meta']['cost'][ $i ], $entry, $name_fields );
				$cost = $this->get_number( $cost );

				$quantity = $settings['meta']['lineItems'] == 'fixed' ? $settings['meta']['quantity'][ $i ] : $this->get_entry_value( $settings['meta']['quantity'][ $i ], $entry, $name_fields );
				$amount   = $quantity * $cost;
				$total += $amount;
				if ( $send_item_id ) {
					//item id is what is saved in the database, use it
					$item_name = $item;
				} else {
					//get item name using saved item id
					$item_name = $this->get_item_name( $item );
					if ( empty( $item_name ) ) {
						//default to item id if no name found
						$item_name = $item;
					}
				}
				$lines[] = array(
					'name'        => $item_name,
					'description' => esc_html( $settings['meta']['description'][ $i ] ),
					'unitCost'    => $cost,
					'quantity'    => $quantity,
					'amount'      => $amount,
				);
				$i ++;
			}
		}

		$invoice_estimate->amount       = $total;
		$invoice_estimate->clientId     = $client->clientId;
		$invoice_estimate->firstName    = $client->firstName;
		$invoice_estimate->lastName     = $client->lastName;
		$invoice_estimate->lines        = $lines;
		$invoice_estimate->organization = $client->organization;
		$invoice_estimate->pStreet1     = $client->pStreet1;
		$invoice_estimate->pStreet2     = $client->pStreet2;
		$invoice_estimate->pCity        = $client->pCity;
		$invoice_estimate->pState       = $client->pState;
		$invoice_estimate->pCode        = $client->pCode;
		$invoice_estimate->pCountry     = $client->pCountry;

		$invoice_estimate = apply_filters( 'gform_freshbooks_args_pre_create', $invoice_estimate, $form, $entry );

		$this->log_debug( __METHOD__ . '(): Creating invoice/estimate => ' . print_r( $invoice_estimate, 1 ) );
		$invoice_estimate->create();
		$lastError = $invoice_estimate->lastError;
		if ( empty( $lastError ) ) {
			$this->log_debug( __METHOD__ . '(): Invoice/estimate created.' );
			$id            = $type == 'invoice' ? $invoice_estimate->invoiceId : $invoice_estimate->estimateId;
			$createPayment = rgar( $settings['meta'], 'createPayment' );
			if ( ! $this->has_product_feed( $form, $entry ) || ! $createPayment ) {
				$this->handle_note_and_send_by_email( rgar( $settings['meta'], 'sendByEmail' ), $type, $id, $entry );
			} elseif ( $createPayment ) {
				gform_update_meta( $entry['id'], 'freshbooks_invoice_id', $id );
			}
		} else {
			$this->log_error( __METHOD__ . "(): The following error occurred when trying to create an invoice/estimate: {$lastError}" );
		}
	}

	public function create_payment( $entry, $action ) {
		if ( ! $this->is_valid_credentials() ) {
			return;
		}

		$feeds = $this->get_feeds_by_entry( $entry['id'] );
		$feed  = $this->get_feed( rgar( $feeds, 0 ) );

		// see if a payment should automatically be created for the invoice.
		if ( rgar( $feed['meta'], 'createPayment' ) ) {
			$id                 = gform_get_meta( $entry['id'], 'freshbooks_invoice_id' );
			$payment            = new FreshBooks_Payment();
			$payment->invoiceId = $id;
			$payment->amount    = rgar( $action, 'amount' );
			$payment->type      = rgar( $action, 'payment_method' ) == 'PayPal' ? 'PayPal' : 'Credit Card';
			$payment->notes     = rgar( $action, 'note' );

			$this->log_debug( __METHOD__ . '(): Creating a payment => ' . print_r( $payment, 1 ) );
			$payment->create();
			$lastError = $payment->lastError;

			if ( empty( $lastError ) ) {
				$this->log_debug( __METHOD__ . '(): Payment created.' );
				$this->handle_note_and_send_by_email( rgar( $feed['meta'], 'sendByEmail' ), 'invoice', $id, $entry );
				gform_delete_meta( $entry['id'], 'freshbooks_invoice_id' );
			} else {
				$this->log_error( __METHOD__ . "(): The following error occurred when trying to create the payment: {$lastError}" );
			}
		}
	}

	public function handle_note_and_send_by_email( $sendByEmail, $type, $id, $entry ) {
		if ( $type == 'invoice' ) {
			$invoice_estimate            = new FreshBooks_Invoice();
			$invoice_estimate->invoiceId = $id;
		} elseif ( $type == 'estimate' ) {
			$invoice_estimate             = new FreshBooks_Estimate();
			$invoice_estimate->estimateId = $id;
		} else { // abort, no need to sendByEmail or add note.
			return;
		}

		// see if invoice/estimate should automatically be emailed.
		if ( $sendByEmail ) {
			$this->log_debug( __METHOD__ . '(): Sending invoice/estimate automatically by email.' );
			$sentByEmail = $invoice_estimate->sendByEmail();
			if ( $sentByEmail ) {
				$this->log_debug( __METHOD__ . '(): The invoice/estimate was successfully scheduled to be automatically sent by FreshBooks.' );
			} else {
				$this->log_error( __METHOD__ . '(): Unable to schedule invoice/estimate to be automatically sent.' );
			}
		}

		// add note to entry.
		$invoice_estimate->get( $id );
		$amount_formatted = GFCommon::to_money( $invoice_estimate->amount, $entry['currency'] );
		$note             = sprintf( __( '%s #%s has been successfully created. Amount: %s. Status: %s.', 'gravityformsfreshbooks' ), ucfirst( $type ), $invoice_estimate->number, $amount_formatted, ucfirst( $invoice_estimate->status ) );
		GFFormsModel::add_note( $entry['id'], 0, $this->_short_title, $note, 'success' );
	}

	/**
	 * Determines if a form has an active feed with the transactionType of product. If an entry is supplied the feed condition is also checked.
	 *
	 * @param array $form
	 * @param array $entry
	 *
	 * @return bool
	 */
	public function has_product_feed( $form = array(), $entry = array() ) {
		if ( empty( $form ) ) {
			$form = $this->get_current_form();
		}

		$feeds = GFAPI::get_feeds( null, $form['id'] );
		foreach ( $feeds as $feed ) {
			if ( rgars( $feed, 'meta/transactionType' ) == 'product' ) {
				if ( ! empty( $entry ) && ! $this->is_feed_condition_met( $feed, $form, $entry ) ) {
					continue;
				}

				return true;
			}
		}

		return false;
	}

	private function init_api() {
		require_once GFFreshBooks::get_base_path() . '/api/Client.php';
		require_once GFFreshBooks::get_base_path() . '/api/Invoice.php';
		require_once GFFreshBooks::get_base_path() . '/api/Estimate.php';
		require_once GFFreshBooks::get_base_path() . '/api/Item.php';
		require_once GFFreshBooks::get_base_path() . '/api/Payment.php';

		$settings  = $this->get_plugin_settings();
		$url       = 'https://' . $settings['siteName'] . '.freshbooks.com/api/2.1/xml-in';
		$authtoken = $settings['authToken'];
		$this->log_debug( __METHOD__ . "(): Initializing API - url: {$url} - token: {$authtoken}" );
		FreshBooks_HttpClient::init( $url, $authtoken );
		$this->log_debug( __METHOD__ . '(): API Initialized.' );
	}

	public function is_valid_credentials() {
		$this->log_debug( __METHOD__ . '(): Validating credentials.' );
		$this->init_api();
		$items = new FreshBooks_Item();

		$dummy      = array();
		$return_val = $items->listing( $dummy, $dummy );
		if ( $return_val ) {
			$this->log_debug( __METHOD__ . '(): Valid site name and authorization token.' );
		} else {
			$this->log_error( __METHOD__ . '(): Invalid site name and/or authorization token.' );
		}

		return $return_val;
	}

	private function get_number( $number ) {

		//Removing all non-numeric characters
		$array        = str_split( $number );
		$clean_number = '';
		foreach ( $array as $char ) {
			if ( ( $char >= '0' && $char <= '9' ) || $char == ',' || $char == '.' ) {
				$clean_number .= $char;
			}
		}

		//Removing thousand separators but keeping decimal point
		$array        = str_split( $clean_number );
		$float_number = '';
		for ( $i = 0, $count = sizeof( $array ); $i < $count; $i ++ ) {
			$char = $array[ $i ];
			if ( $char >= '0' && $char <= '9' ) {
				$float_number .= $char;
			} elseif ( ( $char == '.' || $char == ',' ) && strlen( $clean_number ) - $i <= 3 ) {
				$float_number .= '.';
			}
		}

		return $float_number;

	}

	private function get_entry_value( $field_id, $entry, $name_fields ) {
		foreach ( $name_fields as $name_field ) {
			if ( $field_id == $name_field['id'] ) {
				$value = RGFormsModel::get_lead_field_value( $entry, $name_field );

				return GFCommon::get_lead_field_display( $name_field, $value );
			}
		}

		return rgar( $entry, $field_id );
	}

	private function get_client( $form, $entry, $settings, $name_fields ) {

		$client = new FreshBooks_Client();
		$email  = strtolower( $entry[ $settings['meta']['email'] ] );
		$is_new = true;

		if ( $settings['meta']['updateClient'] ) {
			$existing_clients = '';
			$result_info      = '';

			// is there an existing client with the same email? If so, use it, if not, create one
			$email = $entry[ $settings['meta']['email'] ];

			$client->listing( $existing_clients, $result_info, 1, 1, array( 'email' => $email, 'username' => '' ) );

			if ( ! empty( $existing_clients ) ) {
				$client = $existing_clients[0];
				$is_new = false;
			}

		}

		$client->email        = esc_html( $this->get_entry_value( $settings['meta']['email'], $entry, $name_fields ) );
		$client->firstName    = esc_html( $this->get_entry_value( $settings['meta']['firstName'], $entry, $name_fields ) );
		$client->lastName     = esc_html( $this->get_entry_value( $settings['meta']['lastName'], $entry, $name_fields ) );
		$client->organization = esc_html( $this->get_entry_value( $settings['meta']['organization'], $entry, $name_fields ) );

		$address_field = $settings['meta']['address'];
		if ( ! empty( $address_field ) ) {
			$client->pStreet1 = esc_html( $entry[ $address_field . '.1' ] );
			$client->pStreet2 = esc_html( $entry[ $address_field . '.2' ] );
			$client->pCity    = esc_html( $entry[ $address_field . '.3' ] );
			$client->pState   = esc_html( $entry[ $address_field . '.4' ] );
			$client->pCode    = esc_html( $entry[ $address_field . '.5' ] );
			$client->pCountry = esc_html( $entry[ $address_field . '.6' ] );
		}
		if ( ! empty( $settings['meta']['phone'] ) ) {
			$client->workPhone = esc_html( self::get_entry_value( $settings['meta']['phone'], $entry, $name_fields ) );
		}
		if ( ! empty( $settings['meta']['fax'] ) ) {
			$client->fax = esc_html( self::get_entry_value( $settings['meta']['fax'], $entry, $name_fields ) );
		}
		$client->notes = esc_html( GFCommon::replace_variables( $settings['meta']['notes'], $form, $entry, false, false, false, 'text' ) );

		if ( $is_new ) {
			$this->log_debug( __METHOD__ . "(): Client not found; creating new client with email address {$email}." );
			$client->create();
			$lastError = $client->lastError;
			if ( empty( $lastError ) ) {
				$this->log_debug( __METHOD__ . '(): New client created.' );
			} else {
				$this->log_error( __METHOD__ . "(): The following error occurred when trying to create a new client: {$lastError}" );
			}
		} else {
			$this->log_debug( __METHOD__ . "(): Existing client found with email address {$email}, not creating new one." );
			$id = $client->clientId;
			$client->update();
			$client->clientId = $id;
		}

		return $client;
	}

	// used to upgrade old feeds into new version
	public function upgrade( $previous_version ) {

		$previous_is_pre_addon_framework = empty( $previous_version ) || version_compare( $previous_version, '2.0.dev1', '<' );

		if ( $previous_is_pre_addon_framework ) {
			$old_feeds = $this->get_old_feeds();

			if ( ! $old_feeds ) {
				return;
			}

			$counter = 1;
			foreach ( $old_feeds as $old_feed ) {
				$feed_name = 'Feed ' . $counter;
				$form_id   = $old_feed['form_id'];
				$is_active = $old_feed['is_active'];

				$line_items = '';
				switch ( rgar( $old_feed['meta'], 'is_fixed_cost' ) ) {
					case "1" :
						$line_items = 'fixed';
					case "2" :
						$line_items = 'pricing';
					case "0" :
						$line_items = 'dynamic';
				}

				$new_meta = array(
					'feedName'     => $feed_name,
					'email'        => rgar( $old_feed['meta'], 'email' ),
					'firstName'    => rgar( $old_feed['meta'], 'first_name' ),
					'lastName'     => rgar( $old_feed['meta'], 'last_name' ),
					'organization' => rgar( $old_feed['meta'], 'organization' ),
					'address'      => rgar( $old_feed['meta'], 'address' ),
					'phone'        => rgar( $old_feed['meta'], 'phone' ),
					'fax'          => rgar( $old_feed['meta'], 'fax' ),
					'notes'        => rgar( $old_feed['meta'], 'notes' ),
					'alsoCreate'   => rgar( $old_feed['meta'], 'alsocreate' ),
					'poNumber'     => rgar( $old_feed['meta'], 'ponumber' ),
					'discount'     => rgar( $old_feed['meta'], 'discount' ),
					'notes2'       => rgar( $old_feed['meta'], 'notes2' ),
					'terms'        => rgar( $old_feed['meta'], 'terms' ),
					'updateClient' => rgar( $old_feed['meta'], 'update_client' ),
					'lineItems'    => $line_items,

				);

				$i = 0;
				foreach ( $old_feed['meta']['items'] as $item ) {
					$new_meta['item'][]        = $item['item_id'];
					$new_meta['description'][] = $item['description'];
					$new_meta['cost'][]        = $item['cost'];
					$new_meta['quantity'][]    = $item['quantity'];
				}

				$optin_enabled = rgar( $old_feed['meta'], 'optin_enabled' );
				if ( $optin_enabled ) {
					$new_meta['feed_condition_conditional_logic']        = 1;
					$new_meta['feed_condition_conditional_logic_object'] = array(
						'conditionalLogic' => array(
							'actionType' => 'show',
							'logicType'  => 'all',
							'rules'      => array(
								array(
									'fieldId'  => $old_feed['meta']['optin_field_id'],
									'operator' => $old_feed['meta']['optin_operator'],
									'value'    => $old_feed['meta']['optin_value'],
								),
							)
						)
					);
				} else {
					$new_meta['feed_condition_conditional_logic'] = 0;
				}

				$this->insert_feed( $form_id, $is_active, $new_meta );
				$counter ++;

			}

			$new_settings = array(
				'siteName'  => get_option( 'gf_freshbooks_site_name' ),
				'authToken' => get_option( 'gf_freshbooks_auth_token' )
			);

			parent::update_plugin_settings( $new_settings );
		}

	}

	public function ensure_upgrade() {

		if ( get_option( 'gf_freshbooks_upgrade' ) ) {
			return false;
		}

		$feeds = $this->get_feeds();
		if ( empty( $feeds ) ) {

			//Force Add-On framework upgrade
			$this->upgrade( '1.0' );
		}

		update_option( 'gf_freshbooks_upgrade', 1 );
	}

	public function get_old_feeds() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'rg_freshbooks';

		$form_table_name = RGFormsModel::get_form_table_name();
		$sql             = "SELECT s.id, s.is_active, s.form_id, s.meta, f.title as form_title
				FROM $table_name s
				INNER JOIN $form_table_name f ON s.form_id = f.id";

		$results = $wpdb->get_results( $sql, ARRAY_A );

		$count = sizeof( $results );
		for ( $i = 0; $i < $count; $i ++ ) {
			$results[ $i ]['meta'] = maybe_unserialize( $results[ $i ]['meta'] );
		}

		return $results;
	}

	public function get_item_name( $item_id ) {
		$this->init_api();
		$item = new FreshBooks_Item();
		$item->get( $item_id );
		$item_name = $item->name;

		return $item_name;
	}

	public function validate_discount( $field ) {

		$settings = $this->get_posted_settings();
		$discount = $settings['discount'];

		if ( $discount ) {
			if ( ! is_numeric( $discount ) || ( $discount < 0 || $discount > 100 ) ) {
				$this->set_field_error( array( 'name' => 'discount' ), __( 'Please enter a number between 0 and 100.', 'gravityformsfreshbooks' ) );
			}
		}

	}

}