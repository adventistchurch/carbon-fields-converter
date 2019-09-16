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
    } 
    else {
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
                add_option( '_category|||0|value',    'term:category:'. $opt_val );
                add_option( '_category|||0|type',     'term' );
                add_option( '_category|||0|subtype',  'category' );
                add_option( '_category|||0|id',       $opt_val );
              }
            } else {
              // WE HAVE A SIMPLE FIELD / VALUE
              // ADDRESSING WEIRD BUG FOR SINGLE ARRAY VALUES - MOSTLY IMAGES
              $match_fields = [ 
                'logo_desktop', 
                'logo_mobile', 
                'logo_text', 
                'logo', 
                'sabbath_icon',
                'sabbath_background',
                'footer_logo_icon'
              ];

              if ( !defined( ALPS_V3 ) ) {
                if ( in_array( $opt_key,  $match_fields ) ) {
                  $opt_key = 'option_' . $opt_key;
                }
              }
              add_option( '_' .$opt_key, $opt_val );
            }
          }
        } // IF ALPS OPTIONS
      }
      /* *******************************************************************************
        WIDGETS
      ******************************************************************************* */
      // GET CURRENT SIDEBAR / WIDGET CONFIG 
      $alps_sidebar_widgets = get_option( 'sidebars_widgets' );
      if ( $alps_sidebar_widgets ) {
        // FIRST GET PIKLIST WIDGET FIELD DATA
        $piklist_widgets  = get_option( 'widget_piklist-universal-widget-theme' );
        $match_title      = 'piklist-universal-widget-theme';
        // GET SIDEBAR AREAS
        foreach ( $alps_sidebar_widgets as $area => $area_widgets  ) {
          // IF WIDGET AREAS HAVE ASSIGNED WIDGETS
          if ( is_array( $area_widgets ) && !empty( $area_widgets ) ) {
            
            foreach ( $area_widgets as $this_widget_title ) {
            // ONLY MATCH ON PIKLIST WIDGETS
            if ( strpos( $this_widget_title, $match_title ) !== false ) {
                // A MATCH - SO GET WIDGET INFO - GET ID
                $getID        = explode( '-', $area_widget );
                $widget_id    = array_pop( $getID );  
                $this_widget  = $piklist_widgets[ $widget_id ];
                $this_type    = $this_widget[ 'widget' ];
                if ( !defined( ALPS_V3 ) ) {
                // HANDLE WIDGET TYPE
                  switch ( $this_type ) {
                  // UPDATE V2
                  // ===================== SOCIAL ======================================================= 
                    case 'theme_widget_social' :
                      // CARBON FIELDS USES 'YES' INSTEAD OF TRUE
                      if ( $this_widget[ 'horizontal_rule' ] == true ) {
                        $hr = 'yes';
                      }
                      $fields = array(
                        '_facebook_url'    => $this_widget[ 'facebook_url' ],
                        '_twitter_url'     => $this_widget[ 'twitter_url' ],
                        '_flickr_url'      => $this_widget[ 'flickr_url' ],
                        '_youtube_url'     => $this_widget[ 'youtube_url' ],
                        '_vimeo_url'       => $this_widget[ 'vimeo_url' ],
                        '_email_address'   => $this_widget[ 'email_address' ],
                        '_horizontal_rule' => $hr
                      );
                      $social_fields[ $widget_id ] = $fields;
                      // ================== WIDGET FIELDS =======================
                      // GET CURRENT WIDGET DATA, MERGE THIS ITERATION & UPDATE DB
                      $existing_cf_social_widgets = get_option( 'widget_carbon_fields_alps_widget_social' );
                      $merged_cf_social = array_merge_recursive_numeric_keys( $existing_cf_social, $social_fields );
                      update_option( 'widget_carbon_fields_alps_widget_social', $merged_cf_social );
                      // END WIDGET FIELDS ======================================

                      // ================== SIDEBAR AREAS =======================
                      // GET CURRENT SIDEBAR AREA CONFIG & THEN REMOVE PIKLIST WIDGET
                      $wp_sidebar_widgets = wp_get_sidebars_widgets();

                      if ( ( $key = array_search( $area_widget, $wp_sidebar_widgets[ $area ] ) ) !== false ) {
                        // GRAB POSITION IN SIDEBAR BEFORE REMOVING
                        $update_key = $key;
                        unset( $wp_sidebar_widgets[ $area ][ $key ] );
                      }
                      // PREPARE INSERT THIS CF WIDGET INTO SIDEBARS_WIDGETS
                      $update_sidebar = array( 
                        $area => array(
                          $update_key => 'carbon_fields_alps_widget_social-' . $widget_id
                        )
                      );
                      // COMBINE NEW CF WIDGETS WITH EXISTING CONFIGURATION & SET NEW CONFIGURATION
                      $merged_update = array_merge_recursive_numeric_keys( $wp_sidebar_widgets, $update_sidebar );
                      wp_set_sidebars_widgets( $merged_update );
                        break;
                  }
                } // IF V2
                if ( defined( ALPS_V3 ) ) {
                  switch ( $this_type ) {
                    // AUTHOR BOX
                    case 'theme_author_box' :
                      $fields = array(
                        '_text_link_title'      => $this_widget[ 'text_link_title' ],
                      );
                      $box_fields[ $widget_id ] = $fields;
                      // ================== WIDGET FIELDS =======================
                      // GET CURRENT WIDGET DATA, MERGE THIS ITERATION & UPDATE DB
                      $existing_cf_feed = get_option( 'widget_carbon_fields_alps_widget_author_box' );
                      $merged_cf_feed   = array_merge_recursive_numeric_keys( $existing_cf_feed, $feed_fields );
                      update_option( 'widget_carbon_fields_alps_widget_author_box', $merged_cf_feed );
                      // END WIDGET FIELDS ======================================

                      // ================== SIDEBAR AREAS =======================
                      // GET CURRENT SIDEBAR AREA CONFIG & THEN REMOVE PIKLIST WIDGET
                      $wp_sidebar_widgets = wp_get_sidebars_widgets();
                      if ( ( $key = array_search( $this_widget_title, $wp_sidebar_widgets[ $area ] ) ) !== false ) {
                        // GRAB POSITION IN SIDEBAR BEFORE REMOVING
                        $update_key = $key;
                        unset( $wp_sidebar_widgets[ $area ][ $key ] );
                      }
                      // PREPARE INSERT THIS CF WIDGET INTO SIDEBARS_WIDGETS
                      $update_sidebar = array( 
                        $area => array(
                          $update_key => 'carbon_fields_alps_widget_author_box-' . $widget_id
                        )
                      );
                      // COMBINE NEW CF WIDGETS WITH EXISTING CONFIGURATION & SET NEW CONFIGURATION
                      $merged_update = array_merge_recursive_numeric_keys( $wp_sidebar_widgets, $update_sidebar );
                      wp_set_sidebars_widgets( $merged_update );
                        break;
                  }
                }
                // THE FOLLOWING ARE IN V2 AND V3
                // ===================== POST FEED ======================================================= 
                switch ( $this_type ) {
                  case 'theme_widget_post_feed' :
                    // CARBON FIELDS USES 'YES' INSTEAD OF TRUE
                    if ( $this_widget[ 'for_sidebar' ] == true ) {
                      $for_sidebar = 'yes';
                    }
                    $fields = array(
                        '_feed_category_list'      => $this_widget[ 'feed_category_list' ],
                        '_feed_title'              => $this_widget[ 'feed_title' ],
                        '_feed_widget_post_count'  => $this_widget[ 'feed_widget_post_count' ],
                        '_for_sidebar'             => $for_sidebar,
                        '_feed_widget_btn_text'    => $this_widget[ 'feed_widget_btn_text' ],
                        '_feed_widget_btn_link'    => $this_widget[ 'feed_widget_btn_link' ]
                    );
                    $feed_fields[ $widget_id ] = $fields;
                    // ================== WIDGET FIELDS =======================
                    // GET CURRENT WIDGET DATA, MERGE THIS ITERATION & UPDATE DB
                    $existing_cf_feed = get_option( 'widget_carbon_fields_alps_widget_post_feed' );
                    $merged_cf_feed   = array_merge_recursive_numeric_keys( $existing_cf_feed, $feed_fields );
                    update_option( 'widget_carbon_fields_alps_widget_post_feed', $merged_cf_feed );
                    // END WIDGET FIELDS ======================================

                    // ================== SIDEBAR AREAS =======================
                    // GET CURRENT SIDEBAR AREA CONFIG & THEN REMOVE PIKLIST WIDGET
                    $wp_sidebar_widgets = wp_get_sidebars_widgets();
                    if ( ( $key = array_search( $this_widget_title, $wp_sidebar_widgets[ $area ] ) ) !== false ) {
                      // GRAB POSITION IN SIDEBAR BEFORE REMOVING
                      $update_key = $key;
                      unset( $wp_sidebar_widgets[ $area ][ $key ] );
                    }
                    // PREPARE INSERT THIS CF WIDGET INTO SIDEBARS_WIDGETS
                    $update_sidebar = array( 
                      $area => array(
                        $update_key => 'carbon_fields_alps_widget_post_feed-' . $widget_id
                      )
                    );
                    // COMBINE NEW CF WIDGETS WITH EXISTING CONFIGURATION & SET NEW CONFIGURATION
                    $merged_update = array_merge_recursive_numeric_keys( $wp_sidebar_widgets, $update_sidebar );
                    wp_set_sidebars_widgets( $merged_update );
                      break;
                  
                    // ===================== TEXT WITH LINK =======================================================  
                    case 'theme_widget_text_link' :
                      $fields = array(
                        '_title'    => $this_widget[ 'title' ],
                        '_content'  => $this_widget[ 'content' ],
                        '_url'      => $this_widget[ 'url' ],
                        '_url_text' => $this_widget[ 'url_text' ]
                      );
                      $text_fields[ $widget_id ] = $fields;
                      // ================== WIDGET FIELDS =======================
                      // GET CURRENT WIDGET DATA, MERGE THIS ITERATION & UPDATE DB
                      $existing_cf_text = get_option( 'widget_carbon_fields_alps_widget_text_with_link' );
                      $merged_cf_text   = array_merge_recursive_numeric_keys( $existing_cf_text, $text_fields );
                      update_option( 'widget_carbon_fields_alps_widget_text_with_link', $merged_cf_text );
                      // END WIDGET FIELDS ======================================

                      // ================== SIDEBAR AREAS =======================
                      // GET CURRENT SIDEBAR AREA CONFIG & THEN REMOVE PIKLIST WIDGET
                      $wp_sidebar_widgets = wp_get_sidebars_widgets();
                      if ( ( $key = array_search( $this_widget_title, $wp_sidebar_widgets[ $area ] ) ) !== false ) {
                        // GRAB POSITION IN SIDEBAR BEFORE REMOVING
                        $update_key = $key;
                        unset( $wp_sidebar_widgets[ $area ][ $key ] );
                      }
                      // PREPARE INSERT THIS CF WIDGET INTO SIDEBARS_WIDGETS
                      $update_sidebar = array( 
                        $area => array(
                          $update_key => 'carbon_fields_alps_widget_text_with_link-' . $widget_id
                        )
                      );
                      // COMBINE NEW CF WIDGETS WITH EXISTING CONFIGURATION & SET NEW CONFIGURATION
                      $merged_update = array_merge_recursive_numeric_keys( $wp_sidebar_widgets, $update_sidebar );
                      wp_set_sidebars_widgets( $merged_update );
                      // END SIDEBAR AREAS ======================================
                        break;
                } // PIKLIST WIDGET TYPE
              }  // FOREACH PIKLIST WIDGET
            } // FOREACH WIDGET TO CHECK 
          }  // IF WIDGETS TO PROCESS
        } // FOREACH SIDEBAR AREA
      } // IF WE HAVE ANY SIDEBAR CONFIG

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
        'related_custom_value',
        'make_the_image_round',
        'primary_structured_content',
        'hero_type',
        'hero_image',
        'hero_title',
        'hero_kicker',
        'hero_link_url',
        'hero_image_extended',
        'hero_scroll_hint',
        'hero_column',
        'show_hero_featured_post',
        'hero_featured_post',
        'post_feed_list',
        'post_feed_list_title',
        'post_feed_list_link',
        'post_feed_list_round_image',
        'post_feed_list_category_array',
        'post_feed_list_count',
        'post_feed_list_offset',
        'post_feed_list_custom_array',
        'post_feed_full',
        'post_feed_full_title',
        'post_feed_full_link',
        'post_feed_full_featured',
        'post_feed_full_featured_array',
        'post_feed_full_offset',
        'post_feed_full_category_array',
        'post_feed_archive',
        'post_feed_archive_title',
        'post_feed_archive_link',
        'post_feed_archive_offset',
        'post_feed_archive_category_array',
        'featured_image_hero_layout',
        'hide_dropcap',
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
                    'hero_image_column',
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
      } // UPDATE FIELDS
    add_option( 'alps_cf_converted', TRUE );
    }
  } // END alps_convert_fields


// UTILITY FUNCTION TO PRESERVE NUMERIC KEYS IN ARRAYS WHEN MERGING
// array_merge_recursive DOES *NOT* PRESERVE NUMERIC KEYS NEEDED FOR WP
function array_merge_recursive_numeric_keys() {
  $arrays = func_get_args();
  $base   = array_shift( $arrays );
  foreach ( $arrays as $array ) {
    reset ( $base );
    while ( list( $key, $value ) = @each( $array ) ) {
      if ( is_array( $value ) && @is_array( $base[ $key ] )) {
        $base[ $key ] = array_merge_recursive_numeric_keys( $base[ $key ] , $value );
      }
      else {
        $base[ $key ] = $value;
      }
    }
  }
  return $base;
}
