<?php
/*
Plugin Name: ROOO Services Booking
Description: Custom booking plugin for ROOO Services.
Version: 0.1
Author: Codex
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class ROOO_Services_Booking {

    public function __construct() {
        register_activation_hook( __FILE__, array( $this, 'activate' ) );
        add_action( 'init', array( $this, 'register_post_types' ) );
        add_action( 'admin_menu', array( $this, 'add_admin_menus' ) );
        add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
        add_action( 'save_post', array( $this, 'save_post' ), 10, 2 );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'wp_ajax_rooo_submit_booking', array( $this, 'handle_booking' ) );
        add_action( 'wp_ajax_nopriv_rooo_submit_booking', array( $this, 'handle_booking' ) );
        add_shortcode( 'rooo-booking', array( $this, 'render_booking_form' ) );
    }

    public function activate() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'rooo_bookings';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            customer_name varchar(255) NOT NULL,
            email varchar(255) NOT NULL,
            phone varchar(100) DEFAULT '' NOT NULL,
            service_name varchar(255) NOT NULL,
            category varchar(255) NOT NULL,
            booking_date date NOT NULL,
            notes text,
            booking_type varchar(50) NOT NULL,
            status varchar(50) DEFAULT 'pending' NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );

        $this->register_post_types();
        flush_rewrite_rules();
    }

    public function register_post_types() {
        register_post_type( 'rooo_category', array(
            'label' => 'Service Categories',
            'public' => false,
            'show_ui' => true,
            'menu_icon' => 'dashicons-list-view',
            'supports' => array( 'title' )
        ) );

        register_post_type( 'rooo_subservice', array(
            'label' => 'Subservices',
            'public' => false,
            'show_ui' => true,
            'menu_icon' => 'dashicons-hammer',
            'supports' => array( 'title', 'editor' )
        ) );
    }

    public function add_admin_menus() {
        add_menu_page( 'ROOO Services', 'ROOO Services', 'manage_options', 'rooo-services', null, 'dashicons-calendar-alt', 26 );
        add_submenu_page( 'rooo-services', 'Bookings', 'Bookings', 'manage_options', 'rooo-bookings', array( $this, 'bookings_page' ) );
        add_submenu_page( 'rooo-services', 'Categories', 'Categories', 'manage_options', 'edit.php?post_type=rooo_category' );
        add_submenu_page( 'rooo-services', 'Subservices', 'Subservices', 'manage_options', 'edit.php?post_type=rooo_subservice' );
        add_submenu_page( 'rooo-services', 'Settings', 'Settings', 'manage_options', 'rooo-settings', array( $this, 'settings_page' ) );
    }

    public function bookings_page() {
        echo '<div class="wrap"><h1>Bookings</h1><p>Manage bookings here.</p></div>';
    }

    public function settings_page() {
        echo '<div class="wrap"><h1>Settings</h1><p>Configure plugin settings.</p></div>';
    }

    public function add_meta_boxes() {
        add_meta_box( 'rooo_category_meta', 'Category Details', array( $this, 'category_meta_box' ), 'rooo_category', 'normal', 'default' );
        add_meta_box( 'rooo_subservice_meta', 'Subservice Details', array( $this, 'subservice_meta_box' ), 'rooo_subservice', 'normal', 'default' );
    }

    public function category_meta_box( $post ) {
        $icon   = get_post_meta( $post->ID, '_rooo_icon', true );
        $active = get_post_meta( $post->ID, '_rooo_active', true );
        ?>
        <p><label>Icon/Emoji: <input type="text" name="rooo_icon" value="<?php echo esc_attr( $icon ); ?>" /></label></p>
        <p><label><input type="checkbox" name="rooo_active" value="1" <?php checked( $active, '1' ); ?> /> Active</label></p>
        <?php
    }

    public function subservice_meta_box( $post ) {
        $category = get_post_meta( $post->ID, '_rooo_category', true );
        $type     = get_post_meta( $post->ID, '_rooo_type', true );
        $price    = get_post_meta( $post->ID, '_rooo_price', true );
        $duration = get_post_meta( $post->ID, '_rooo_duration', true );
        $active   = get_post_meta( $post->ID, '_rooo_active', true );
        ?>
        <p><label>Category ID: <input type="number" name="rooo_category" value="<?php echo esc_attr( $category ); ?>" /></label></p>
        <p><label>Booking Type:
            <select name="rooo_type">
                <option value="fixed" <?php selected( $type, 'fixed' ); ?>>Fixed</option>
                <option value="quote" <?php selected( $type, 'quote' ); ?>>Quote</option>
            </select>
        </label></p>
        <p><label>Price: <input type="number" name="rooo_price" value="<?php echo esc_attr( $price ); ?>" /></label></p>
        <p><label>Duration: <input type="text" name="rooo_duration" value="<?php echo esc_attr( $duration ); ?>" /></label></p>
        <p><label><input type="checkbox" name="rooo_active" value="1" <?php checked( $active, '1' ); ?> /> Active</label></p>
        <?php
    }

    public function save_post( $post_id, $post ) {
        if ( $post->post_type === 'rooo_category' ) {
            if ( isset( $_POST['rooo_icon'] ) ) {
                update_post_meta( $post_id, '_rooo_icon', sanitize_text_field( $_POST['rooo_icon'] ) );
            }
            $active = isset( $_POST['rooo_active'] ) ? '1' : '0';
            update_post_meta( $post_id, '_rooo_active', $active );
        }

        if ( $post->post_type === 'rooo_subservice' ) {
            if ( isset( $_POST['rooo_category'] ) ) {
                update_post_meta( $post_id, '_rooo_category', intval( $_POST['rooo_category'] ) );
            }
            if ( isset( $_POST['rooo_type'] ) ) {
                update_post_meta( $post_id, '_rooo_type', sanitize_text_field( $_POST['rooo_type'] ) );
            }
            if ( isset( $_POST['rooo_price'] ) ) {
                update_post_meta( $post_id, '_rooo_price', floatval( $_POST['rooo_price'] ) );
            }
            if ( isset( $_POST['rooo_duration'] ) ) {
                update_post_meta( $post_id, '_rooo_duration', sanitize_text_field( $_POST['rooo_duration'] ) );
            }
            $active = isset( $_POST['rooo_active'] ) ? '1' : '0';
            update_post_meta( $post_id, '_rooo_active', $active );
        }
    }

    public function enqueue_scripts() {
        wp_enqueue_script( 'rooo-booking', plugins_url( 'assets/js/rooo-booking.js', __FILE__ ), array( 'jquery' ), '1.0', true );
        wp_localize_script( 'rooo-booking', 'rooo_ajax', array( 'ajax_url' => admin_url( 'admin-ajax.php' ) ) );
    }

    public function handle_booking() {
        global $wpdb;
        $table = $wpdb->prefix . 'rooo_bookings';

        $data = array(
            'customer_name' => sanitize_text_field( $_POST['customer_name'] ),
            'email'         => sanitize_email( $_POST['email'] ),
            'phone'         => sanitize_text_field( $_POST['phone'] ?? '' ),
            'service_name'  => get_the_title( intval( $_POST['service_id'] ) ),
            'category'      => '',
            'booking_date'  => sanitize_text_field( $_POST['booking_date'] ),
            'notes'         => sanitize_textarea_field( $_POST['notes'] ?? '' ),
            'booking_type'  => sanitize_text_field( $_POST['booking_type'] ?? '' ),
            'status'        => 'pending',
        );

        $wpdb->insert( $table, $data );

        wp_send_json_success( array( 'message' => 'Booking received!' ) );
    }

    public function render_booking_form() {
        $categories = get_posts( array(
            'post_type'   => 'rooo_category',
            'numberposts' => -1,
            'meta_key'    => '_rooo_active',
            'meta_value'  => '1'
        ) );

        $subservices = get_posts( array(
            'post_type'   => 'rooo_subservice',
            'numberposts' => -1,
            'meta_key'    => '_rooo_active',
            'meta_value'  => '1'
        ) );

        ob_start();
        ?>
        <div id="rooo-booking-app">
            <div class="rooo-categories">
            <?php foreach ( $categories as $cat ) : ?>
                <button class="rooo-category" data-id="<?php echo $cat->ID; ?>">
                    <?php echo esc_html( $cat->post_title ); ?>
                </button>
            <?php endforeach; ?>
            </div>

            <?php foreach ( $categories as $cat ) : ?>
                <div class="rooo-subservices" data-category="<?php echo $cat->ID; ?>" style="display:none;">
                    <?php foreach ( $subservices as $sub ) :
                        $scat = get_post_meta( $sub->ID, '_rooo_category', true );
                        if ( intval( $scat ) !== $cat->ID ) continue;
                        ?>
                        <button class="rooo-subservice" data-id="<?php echo $sub->ID; ?>">
                            <?php echo esc_html( $sub->post_title ); ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>

            <form class="rooo-booking-form" style="display:none;">
                <input type="hidden" name="action" value="rooo_submit_booking" />
                <input type="hidden" id="rooo_service_id" name="service_id" value="" />
                <p><label>Name:<br><input type="text" name="customer_name" required></label></p>
                <p><label>Email:<br><input type="email" name="email" required></label></p>
                <p><label>Phone:<br><input type="text" name="phone"></label></p>
                <p><label>Date:<br><input type="date" name="booking_date" required></label></p>
                <p><label>Notes:<br><textarea name="notes"></textarea></label></p>
                <p><button type="submit">Submit</button></p>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
}

new ROOO_Services_Booking();
