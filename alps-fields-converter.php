<?php
/**
 * Plugin Name: ALPS Fields Converter
 * Description: This will convert your fields from Piklist to Carbon Fields format. This only runs once when it is activated. 
 */
defined( 'ABSPATH' ) or die( 'No direct access allowed.' );

register_activation_hook( __FILE__, 'alps_convert_fields' );

add_action( 'admin_notices', 'alps_admin_notice__success' );

function alps_admin_notice__success() {
  if ( get_transient( 'alps_fields_converted' ) ) {
?>
<div class="notice notice-success is-dismissible">
    <p><?php _e( 'Your Piklist fields have been converted. The conversion will only run once, so if you are reactivating this plugin, nothing was done. You can now remove the ALPS Fields Converter plugin.', '' ); ?></p>
</div>
<?php
   delete_transient( 'alps_fields_converted' );
  }
}

function alps_convert_fields() {
  set_transient( 'alps_fields_converted', true, 5 );
  global $wpdb;
  $already_updated = get_option( 'alps_cf_converted' );
  if ( $already_updated ) {
    // OUR WORK HERE IS DONE
    
  } else {
    /* *******************************************************************************
      THEME OPTIONS
    ******************************************************************************* */
    $alps_options = get_option( 'alps_theme_settings' );

    if ( $alps_options )  {

      foreach ( $alps_options as $opt_key =>  $opt_val ) {
        // IS THIS A COMPLEX / REPEATER STYLE FIELD?
        if ( is_array( $opt_val ) ) {
          // PROCESS ARRAY
          // INSERT CF VALUE FLAG / INDICATOR - _$opt_key|||0|value
          add_option( '_' .$opt_key. '|||0|value', '_' );
          foreach ( $opt_val as $opt_subkey => $opt_subval ) {
            $opt_newkey = '_' .$opt_key. '|'. $opt_subkey . '|0|0|value';
            add_option ( $opt_newkey, $opt_subval );
          }
        } else {
          // HANDLE CATEGORY CONVERSION
          if ( 'category' == $opt_key ) {
            if ( $opt_val ) {
              // CARBON FIELDS WANTS IT THIS WAY
              add_option( '_category|||0|value', 'term:category:'. $opt_val );
              add_option( '_category|||0|type', 'term' );
              add_option( '_category|||0|subtype', 'category' );
              add_option( '_category|||0|id', $opt_val );
            }
          } else {
            // WE HAVE A SIMPLE FIELD / VALUE
            add_option( '_' .$opt_key, $opt_val );
          }
        }
      } // IF ALPS OPTIONS
    }
    /* *******************************************************************************
      PAGE FIELDS
    ******************************************************************************* */
   $update_fields = array(
      'hide_featured_image',
      'video_url',
      'video_caption',
      'carousel_type',
      'carousel_slides',
      'display_title',
      'kicker',
      'subtitle',
      'intro',
      'header_background_image',
      'header_block_text',
      'header_block_title',
      'header_block_subtitle',
      'header_block_image',
      'sb_title',
      'sb_subtitle',
      'sb_background_image',
      'sb_thumbnail',
      'sb_side_image',
      'is_video',
      'sb_body',
      'sb_url',
      'sb_cta',
      'content_block',
      'content_block_freeform',
      'grid_two_columns',
      'content_block_relationship',
      'hide_featured_image',
      'video_url',
      'video_caption',
      'related',
      'make_the_image_round',
      'primary_structured_content'
    );

    foreach ( $update_fields as $current_field ) {
      $query =  "SELECT * FROM ".$wpdb->postmeta." WHERE meta_key = '".$current_field."'";
      $match = $wpdb->get_results ( $query, OBJECT );	
      if ( isset( $match ) ) {
        foreach ( $match as $item ) {
          $meta_value = $item->meta_value;
          $post_id = $item->post_id; 
          $data = @unserialize( $meta_value );
          if ($data !== false) {
            // PROCESS SERIALIZED ========================================
            $repeater_field = $current_field;
            $cnt = 0;
            foreach ( $data as $sub_array ) {
              foreach ( $sub_array as $sub_field => $sub_value ) {
                // CF FORMAT FOLLOWS -
                //_repeater_field_name|sub_field|0|0|value
                // HANDLE IMAGES IN SERIALIZED DATA
                $img_fields = array( 
                  'carousel_image', 
                  'content_block_freeform_image',
                  'content_block_image_file',
                  'content_block_grid_file_1',
                  'content_block_grid_file_2',
                  'content_block_grid_file_3',
                );
                if ( in_array( $sub_field, $img_fields ) ) {
                  $sub_value = $sub_value[0];
                }
                $cf_field =  '_' . $repeater_field .'|'. $sub_field .'|' . $cnt . '|0|value';
                add_post_meta( $post_id, $cf_field, $sub_value );
              }
              $cnt++;
            }
          } else {
            // WE HAVE A SIMPLE FIELD / VALUE
            add_post_meta( $post_id, '_' .$current_field, $meta_value );
          }
        } // FOREACH FIELD
      } // IF FIELDS TO CONVERT
    }

   add_option( 'alps_cf_converted', TRUE );
   }
  }
