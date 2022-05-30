<?php

/**
 * AccordRx Theme functions and definitions
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package AccordRx
 * @since 1.0.0
 */

/**
 * Define Constants
 */
define('CHILD_THEME_ACCORDRX_VERSION', '1.0.0');

/**
 * Enqueue styles
 */
function child_enqueue_styles()
{

	wp_enqueue_style('accordrx-theme-css', get_stylesheet_directory_uri() . '/style.css', array('astra-theme-css'), CHILD_THEME_ACCORDRX_VERSION, 'all');
}

add_action('wp_enqueue_scripts', 'child_enqueue_styles', 15);


add_action('init', 'accrx_session_start');
function accrx_session_start()
{
	if (!session_id()) session_start();
}
//NPI Number: 1093711483
add_action('gform_post_paging', 'accrx_post_page', 10, 3);
function accrx_post_page($form, $source_page, $current_page)
{
	if ($source_page == 1) {
		$npi_number = rgpost('input_1');
		$url = 'https://npiregistry.cms.hhs.gov/api/?version=2.0&number=' . $npi_number;
		$request = new WP_Http;
		$results = $request->request($url);
		$defaultValues = accrx_mapping_fields($results['body']);
		$_SESSION['defaultValues'] = $defaultValues;
	}
}

//add_filter('gform_field_value', 'accrx_field_value', 10, 11);
function accrx_field_value($value, $field, $name)
{
	return $_SESSION['defaultValues'][$name] ?? $value;
}

function accrx_mapping_fields($reqBody)
{
	$response = json_decode($reqBody);
	if (!isset($response->result_count) || $response->result_count <= 0) {
		return null;
	}
	$resFields = $response->results[0];
	$fieldsValues = [
		'input_11_2_3' => $resFields->basic->authorized_official_first_name,
		'input_11_2_6' => $resFields->basic->authorized_official_last_name,
		'input_11_16' => $resFields->basic->authorized_official_telephone_number,
		'input_11_17' => $resFields->basic->name,
		'input_11_18' => $resFields->other_names[0]->organization_name,
		'input_11_24' => $resFields->taxonomies[0]->license,
		'last_update' => $resFields->basic->last_update,
		'input_11_25_1' => date('m', strtotime($resFields->basic->last_update)),
		'input_11_25_2' => date('d', strtotime($resFields->basic->last_update)),
		'input_11_25_3' => date('Y', strtotime($resFields->basic->last_update)),
		//'input_11_26' => $resFields->taxonomies[0]->state,
	];
	$address = [];
	foreach ($resFields->addresses as $adrs) {
		if ($adrs->address_purpose == 'LOCATION') {
			$address = [
				'input_11_19_1' => $adrs->address_1,
				'input_11_19_2' => $adrs->address_2,
				'input_11_19_3' => $adrs->city,
				//'input_11_19_4' => $adrs->state,
				'input_11_19_5' => $adrs->postal_code,
			];
		}
	}
	return array_merge($fieldsValues, $address);
}
function vdarr($arr)
{
	echo '<pre>';
	print_r($arr);
	echo '</pre>';
}

add_action('wp_ajax_get_default_values', 'accrx_get_default_values');
add_action('wp_ajax_nopriv_get_default_values', 'accrx_get_default_values');
function accrx_get_default_values()
{
	$defaultValues = $_SESSION['defaultValues'] ?? [];
	echo json_encode($defaultValues);
	wp_die();
}


add_action('wp_enqueue_scripts', 'accrx_scripts');
function accrx_scripts()
{
	wp_enqueue_script(
		'accrx_script',
		get_stylesheet_directory_uri() . '/js/script.js',
		['jquery'],
		'1'
	);

	wp_localize_script('accrx_script', 'accrx', [
		'ajaxurl' => admin_url('admin-ajax.php')
	]);
}

add_action('gform_advancedpostcreation_post_after_creation_2', 'add_new_feature_image', 10, 4);
function get_leading_zero_id($id)
{

	$new_id = $id;
	$str_len = strlen($id);
	if ($str_len < 11) {
		$max_count = 11 - $str_len;
		$str_prefix = "";
		if ($str_len < 11) {
			for ($i = 0; $i < $max_count; $i++) {
				$str_prefix .= "0";
			}
			$new_id = $str_prefix . $id;
		}
	}

	return $new_id;
}
function get_featured_image($post_id, $new_id)
{
	$image_url = "https://transitfuncstorage.blob.core.windows.net/drug-image-container/nlm/" . $new_id . ".jpg";
	if (!getimagesize($image_url)) {
		$upload_dir   = wp_upload_dir();
		$image_url = $upload_dir["baseurl"] . "/default_accordrx.png";
	}
	fifu_dev_set_image($post_id, $image_url);
}
function add_new_feature_image($post_id, $feed, $entry, $form)
{

	$sku_id = "";
	$gf_advance = new GF_Advanced_Post_Creation();
	$meta_fields = $gf_advance->get_generic_map_fields($feed, 'postMetaFields', $form, $entry);
	//$meta_fields = get_generic_map_fields($feed, 'postMetaFields', $form, $entry);
	foreach ($meta_fields as $meta_key => $meta_value) {

		if ($meta_key == "_sku") {
			$meta_value = get_leading_zero_id($meta_value);
			$sku_id = $meta_value;
			#GFCommon::send_email('mudassar66@gmail.com', 'mudassar66@gmail.com', '', '', 'New Post', "meta_key=" . $meta_key . " meta valaue" . $meta_value);
			update_post_meta($post_id, $meta_key, $meta_value);
		}
	}
	// Featured Image //
	get_featured_image($post_id, $sku_id);
}
add_filter('gform_submit_button', 'form_submit_button', 10, 2);
function form_submit_button($button, $form)
{
	$button_input = $button;
	//  return "<button class='button gform_button' id='gform_submit_button_{$form['id']}'><span>Submit</span></button>";
	$form_id      = absint($form['id']);
	$button_input = GFFormDisplay::get_form_button($form['id'], "gform_submit_button_{$form['id']}", $button, __('Submit', 'gravityforms'), 'gform_next_button', __('Submit', 'gravityforms'), 0);
	//$button_input = gf_apply_filters(array('gform_submit_button', $form_id), $button_input, $form);
	return $button_input;
}
