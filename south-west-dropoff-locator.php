<?php
/**
 * Plugin Name: South West Drop-Off Locator Pro
 * Description: Version 8.2 - Fixed popup jump/disappearing bug by isolating active markers from clusters, replaced degree-based lat offset with pixel projection offset, and added security nonces.
 * Version: 8.2
 * Author: South West Team
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// 1. REGISTER CUSTOM POST TYPE
add_action('init', function() {
    register_post_type('sw_location', [
        'public'      => true,
        'label'       => 'Drop-Off Locations',
        'menu_icon'   => 'dashicons-location-alt',
        'supports'    => ['title'],
        'show_in_rest'=> true,
    ]);
});

// 2. ADMIN COLUMNS
add_filter('manage_sw_location_posts_columns', function($columns) {
    $columns['map_status'] = 'Map Status';
    return $columns;
});

add_action('manage_sw_location_posts_custom_column', function($column, $post_id) {
    if ($column === 'map_status') {
        $lat = get_post_meta($post_id, '_sw_lat', true);
        echo $lat ? '<span style="color:#1a7a4a;font-weight:bold;">✅ Active Pin</span>' : '<span style="color:#d63638;">❌ Missing Pin</span>';
    }
}, 10, 2);

// 3. ADMIN META BOX (MAIN CONTENT AREA)
add_action('add_meta_boxes', function() {
    add_meta_box(
        'sw_details_main',
        'Location Details & Daily Hours', 
        'sw_render_metabox', 
        'sw_location', 
        'normal',
        'high'
    );
});

function sw_render_metabox($post) {
    wp_nonce_field('sw_save_location_data', 'sw_location_nonce');

    $days   = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
    $addr   = get_post_meta($post->ID, '_sw_addr', true);
    $phone  = get_post_meta($post->ID, '_sw_phone', true);
    $mobile = get_post_meta($post->ID, '_sw_mobile', true);
    $cat    = get_post_meta($post->ID, '_sw_cat', true);
    $lat    = get_post_meta($post->ID, '_sw_lat', true);
    $lng    = get_post_meta($post->ID, '_sw_lng', true);
    $notice = get_post_meta($post->ID, '_sw_notice', true);
    ?>
    <div style="padding: 10px 0;">
        <p>
            <label style="font-size: 14px; font-weight: 600;">Address</label><br>
            <div style="display: flex; gap: 10px; margin-top: 5px;">
                <input type="text" id="sw_addr_field" name="sw_addr" value="<?php echo esc_attr($addr); ?>" style="flex: 1; padding: 6px 10px;" placeholder="e.g. 59 High Street, Christchurch, BH23 1AS">
                <button type="button" id="sw_geocode_btn" class="button button-primary" style="white-space: nowrap;">📍 Get Coordinates</button>
            </div>
            <span style="color: #666; font-size: 12px; display: block; margin-top: 4px;">Enter the full address including town and postcode, then click "Get Coordinates" to auto-populate Lat/Lng.</span>
        </p>

        <p style="margin-top: 15px;">
            <label style="font-size: 14px; font-weight: 600; color: #b45309;">⚠️ Holiday / Exceptional Hours Notice (Optional)</label><br>
            <input type="text" name="sw_notice" value="<?php echo esc_attr($notice); ?>" style="width: 100%; padding: 6px 10px; margin-top: 5px; border-color: #f59e0b;" placeholder="e.g. Closed Easter Monday or Temporary early closing at 4 PM">
            <span style="color: #666; font-size: 12px; display: block; margin-top: 4px;">Displays as an active warning banner on the location card and map popup.</span>
        </p>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 20px;">
            <div>
                <label style="font-size: 14px; font-weight: 600;">Telephone Number (Optional)</label><br>
                <input type="text" name="sw_phone" value="<?php echo esc_attr($phone); ?>" style="width:100%; padding: 6px 10px; margin-top: 5px;" placeholder="e.g. 01275 123456">
            </div>
            <div>
                <label style="font-size: 14px; font-weight: 600;">Mobile Number (Optional)</label><br>
                <input type="text" name="sw_mobile" value="<?php echo esc_attr($mobile); ?>" style="width:100%; padding: 6px 10px; margin-top: 5px;" placeholder="e.g. 07123 456789">
            </div>
            <div>
                <label style="font-size: 14px; font-weight: 600;">Category</label><br>
                <input type="text" name="sw_cat" value="<?php echo esc_attr($cat); ?>" style="width:100%; padding: 6px 10px; margin-top: 5px;" placeholder="Partner Store">
            </div>
        </div>
        
        <div style="display: flex; gap: 20px; background: #f6f7f7; padding: 15px; border-radius: 6px; border: 1px solid #c3c4c7; margin: 20px 0;">
            <div style="flex: 1;">
                <label style="font-weight: 600;">Latitude</label>
                <input type="text" id="sw_lat_field" name="sw_lat" value="<?php echo esc_attr($lat); ?>" style="width:100%; margin-top: 5px; padding: 6px 10px;">
            </div>
            <div style="flex: 1;">
                <label style="font-weight: 600;">Longitude</label>
                <input type="text" id="sw_lng_field" name="sw_lng" value="<?php echo esc_attr($lng); ?>" style="width:100%; margin-top: 5px; padding: 6px 10px;">
            </div>
        </div>

        <h4 style="font-size: 15px; margin-top: 25px; border-bottom: 1px solid #dcdcde; padding-bottom: 8px;">Opening Hours</h4>
        <p style="font-size: 12px; color: #666; margin-bottom: 10px;">Supports formats like <code>09:00 - 17:00</code>, split shifts like <code>10AM - 1PM/2PM - 5PM</code>, or <code>Variable</code>.</p>
        <div style="display: grid; grid-template-columns: repeat( auto-fit, minmax(200px, 1fr) ); gap: 15px; margin-top: 15px;">
            <?php foreach($days as $day): 
                $val = get_post_meta($post->ID, '_sw_h_' . $day, true); ?>
                <div>
                    <label style="text-transform: capitalize; font-weight: 600; font-size: 13px;"><?php echo esc_html($day); ?></label>
                    <input type="text" name="sw_h_<?php echo esc_attr($day); ?>" value="<?php echo esc_attr($val); ?>" style="width:100%; margin-top: 4px; padding: 5px 8px;" placeholder="e.g. 10AM - 1PM/2PM - 5PM or Variable">
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var btn = document.getElementById('sw_geocode_btn');
        if (!btn) return;

        btn.addEventListener('click', function() {
            var address = document.getElementById('sw_addr_field').value.trim();
            if (!address) {
                alert('Please enter an address first.');
                return;
            }

            btn.textContent = 'Searching...';
            btn.disabled = true;

            var url = 'https://nominatim.openstreetmap.org/search?format=json&q=' + encodeURIComponent(address) + '&limit=1';

            fetch(url, { headers: { 'User-Agent': 'WP-DropOff-Locator' } })
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    if (data && data.length > 0) {
                        document.getElementById('sw_lat_field').value = data[0].lat;
                        document.getElementById('sw_lng_field').value = data[0].lon;
                        btn.textContent = '✅ Found!';
                        setTimeout(function() { btn.textContent = '📍 Get Coordinates'; btn.disabled = false; }, 2000);
                    } else {
                        alert('Could not find coordinates for this address. Try adding a postcode or town name.');
                        btn.textContent = '📍 Get Coordinates';
                        btn.disabled = false;
                    }
                })
                .catch(function() {
                    alert('Geocoding service error. Check connection.');
                    btn.textContent = '📍 Get Coordinates';
                    btn.disabled = false;
                });
        });
    });
    </script>
    <?php
}

// 4. GEOCODING & SAVE LOGIC
function sw_get_coords_from_address($address) {
    $url = "https://nominatim.openstreetmap.org/search?format=json&q=".urlencode($address)."&limit=1";
    $response = wp_remote_get($url, ['user-agent' => 'WP-Locator-Pro', 'timeout' => 5]);
    if (is_wp_error($response)) return false;
    $data = json_decode(wp_remote_retrieve_body($response), true);
    return (!empty($data)) ? ['lat' => $data[0]['lat'], 'lng' => $data[0]['lon']] : false;
}

add_action('save_post', function($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!isset($_POST['sw_location_nonce']) || !wp_verify_nonce($_POST['sw_location_nonce'], 'sw_save_location_data')) return;
    if (!current_user_can('edit_post', $post_id)) return;
    if (!isset($_POST['sw_addr'])) return;

    update_post_meta($post_id, '_sw_addr', sanitize_text_field($_POST['sw_addr']));
    update_post_meta($post_id, '_sw_phone', sanitize_text_field($_POST['sw_phone'] ?? ''));
    update_post_meta($post_id, '_sw_mobile', sanitize_text_field($_POST['sw_mobile'] ?? ''));
    update_post_meta($post_id, '_sw_cat', sanitize_text_field($_POST['sw_cat'] ?? ''));
    update_post_meta($post_id, '_sw_notice', sanitize_text_field($_POST['sw_notice'] ?? ''));

    foreach(['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'] as $day) {
        if (isset($_POST['sw_h_' . $day])) {
            update_post_meta($post_id, '_sw_h_' . $day, sanitize_text_field($_POST['sw_h_' . $day]));
        }
    }

    if(!empty($_POST['sw_lat']) && !empty($_POST['sw_lng'])) {
        update_post_meta($post_id, '_sw_lat', sanitize_text_field($_POST['sw_lat']));
        update_post_meta($post_id, '_sw_lng', sanitize_text_field($_POST['sw_lng']));
    } else {
        $coords = sw_get_coords_from_address($_POST['sw_addr']);
        if ($coords) {
            update_post_meta($post_id, '_sw_lat', $coords['lat']);
            update_post_meta($post_id, '_sw_lng', $coords['lng']);
        }
    }
});

// 5. BULK IMPORT & EXPORT (CSV TOOLS)
add_action('admin_menu', function() {
    add_submenu_page('edit.php?post_type=sw_location', 'CSV Tools', 'CSV Import / Export', 'manage_options', 'sw-csv-tools', 'sw_render_csv_tools_page');
});

// CSV Export Handler
add_action('admin_init', function() {
    if (isset($_POST['sw_export_nonce']) && wp_verify_nonce($_POST['sw_export_nonce'], 'sw_export_action')) {
        if (!current_user_can('manage_options')) return;

        $filename = 'drop-off-locations-' . date('Y-m-d-His') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);

        $output = fopen('php://output', 'w');

        fputcsv($output, [
            'Title', 'Address', 'Phone', 'Mobile', 'Category', 
            'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun', 
            'Lat', 'Lng', 'Notice'
        ]);

        $query = new WP_Query([
            'post_type'      => 'sw_location',
            'posts_per_page' => -1,
            'post_status'    => 'publish'
        ]);

        while ($query->have_posts()) {
            $query->the_post();
            $id = get_the_ID();

            fputcsv($output, [
                get_the_title(),
                get_post_meta($id, '_sw_addr', true),
                get_post_meta($id, '_sw_phone', true),
                get_post_meta($id, '_sw_mobile', true),
                get_post_meta($id, '_sw_cat', true) ?: 'Partner Store',
                get_post_meta($id, '_sw_h_mon', true) ?: 'Closed',
                get_post_meta($id, '_sw_h_tue', true) ?: 'Closed',
                get_post_meta($id, '_sw_h_wed', true) ?: 'Closed',
                get_post_meta($id, '_sw_h_thu', true) ?: 'Closed',
                get_post_meta($id, '_sw_h_fri', true) ?: 'Closed',
                get_post_meta($id, '_sw_h_sat', true) ?: 'Closed',
                get_post_meta($id, '_sw_h_sun', true) ?: 'Closed',
                get_post_meta($id, '_sw_lat', true),
                get_post_meta($id, '_sw_lng', true),
                get_post_meta($id, '_sw_notice', true)
            ]);
        }
        wp_reset_postdata();

        fclose($output);
        exit;
    }
});

function sw_render_csv_tools_page() {
    if (isset($_POST['sw_import_nonce']) && wp_verify_nonce($_POST['sw_import_nonce'], 'sw_import_action')) {
        if (!current_user_can('manage_options')) return;

        if (!empty($_FILES['sw_csv']['tmp_name'])) {
            $handle = fopen($_FILES['sw_csv']['tmp_name'], 'r');
            fgetcsv($handle);
            $updated_count = 0;
            $inserted_count = 0;

            while (($data = fgetcsv($handle)) !== FALSE) {
                if (empty($data[0])) continue;

                $title = sanitize_text_field($data[0]);

                $existing = get_posts([
                    'post_type'   => 'sw_location',
                    'title'       => $title,
                    'post_status' => 'any',
                    'numberposts' => 1,
                    'fields'      => 'ids',
                ]);

                if (!empty($existing)) {
                    $post_id = $existing[0];
                    $updated_count++;
                } else {
                    $post_id = wp_insert_post([
                        'post_title'  => $title,
                        'post_type'   => 'sw_location',
                        'post_status' => 'publish'
                    ]);
                    $inserted_count++;
                }

                if ($post_id) {
                    update_post_meta($post_id, '_sw_addr', sanitize_text_field($data[1] ?? ''));
                    update_post_meta($post_id, '_sw_phone', sanitize_text_field($data[2] ?? ''));
                    update_post_meta($post_id, '_sw_mobile', sanitize_text_field($data[3] ?? ''));
                    update_post_meta($post_id, '_sw_cat', sanitize_text_field($data[4] ?? 'Partner Store'));
                    update_post_meta($post_id, '_sw_h_mon', sanitize_text_field($data[5] ?? 'Closed'));
                    update_post_meta($post_id, '_sw_h_tue', sanitize_text_field($data[6] ?? 'Closed'));
                    update_post_meta($post_id, '_sw_h_wed', sanitize_text_field($data[7] ?? 'Closed'));
                    update_post_meta($post_id, '_sw_h_thu', sanitize_text_field($data[8] ?? 'Closed'));
                    update_post_meta($post_id, '_sw_h_fri', sanitize_text_field($data[9] ?? 'Closed'));
                    update_post_meta($post_id, '_sw_h_sat', sanitize_text_field($data[10] ?? 'Closed'));
                    update_post_meta($post_id, '_sw_h_sun', sanitize_text_field($data[11] ?? 'Closed'));

                    if (!empty($data[12]) && !empty($data[13])) {
                        update_post_meta($post_id, '_sw_lat', sanitize_text_field($data[12]));
                        update_post_meta($post_id, '_sw_lng', sanitize_text_field($data[13]));
                    }

                    if (isset($data[14])) {
                        update_post_meta($post_id, '_sw_notice', sanitize_text_field($data[14]));
                    }
                }
            }
            fclose($handle);
            echo "<div class='updated'><p><strong>Import Complete!</strong> Added $inserted_count new locations and updated $updated_count existing locations.</p></div>";
        }
    }
    ?>
    <div class="wrap">
        <h1>Drop-Off Locations CSV Tools</h1>
        
        <div style="display: flex; gap: 30px; margin-top: 20px;">
            <div style="flex: 1; background: #fff; border: 1px solid #c3c4c7; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <h2>📥 Export Locations to CSV</h2>
                <p>Download a spreadsheet containing all published locations with addresses, phone numbers, opening hours, latitude, longitude, and holiday notices.</p>
                <form method="post">
                    <?php wp_nonce_field('sw_export_action', 'sw_export_nonce'); ?>
                    <?php submit_button('Download CSV Spreadsheet', 'primary', 'sw_submit_export', false); ?>
                </form>
            </div>

            <div style="flex: 1; background: #fff; border: 1px solid #c3c4c7; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <h2>📤 Bulk Import / Upsert Locations</h2>
                <p>Upload a CSV file to create or update existing locations by title.</p>
                <p style="font-size: 12px; color: #666;"><strong>Expected Columns:</strong> Title, Address, Phone, Mobile, Category, Mon, Tue, Wed, Thu, Fri, Sat, Sun, Lat, Lng, Notice.</p>
                <form method="post" enctype="multipart/form-data">
                    <?php wp_nonce_field('sw_import_action', 'sw_import_nonce'); ?>
                    <input type="file" name="sw_csv" accept=".csv" required style="margin-bottom:15px; display:block;">
                    <?php submit_button('Run Import', 'secondary', 'sw_submit_import', false); ?>
                </form>
            </div>
        </div>
    </div>
    <?php
}

// 6. FRONTEND SHORTCODE [dropoff_map]
add_action('wp_enqueue_scripts', function() {
    // Leaflet Core
    wp_enqueue_style('leaflet-css', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css');
    wp_enqueue_script('leaflet-js', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', [], null, true);

    // Leaflet MarkerCluster
    wp_enqueue_style('leaflet-cluster-css', 'https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css');
    wp_enqueue_style('leaflet-cluster-default-css', 'https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css');
    wp_enqueue_script('leaflet-cluster-js', 'https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js', ['leaflet-js'], null, true);
});

add_shortcode('dropoff_map', function() {
    $query = new WP_Query(['post_type' => 'sw_location', 'posts_per_page' => -1]);
    $locations = [];
    while ($query->have_posts()) {
        $query->the_post();
        $id  = get_the_ID();
        $lat = get_post_meta($id, '_sw_lat', true);
        $lng = get_post_meta($id, '_sw_lng', true);
        if ($lat && $lng) {
            $locations[] = [
                'id'       => $id,
                'name'     => get_the_title(),
                'lat'      => (float)$lat,
                'lng'      => (float)$lng,
                'address'  => get_post_meta($id, '_sw_addr', true),
                'phone'    => get_post_meta($id, '_sw_phone', true),
                'mobile'   => get_post_meta($id, '_sw_mobile', true),
                'category' => get_post_meta($id, '_sw_cat', true) ?: 'Partner Store',
                'notice'   => get_post_meta($id, '_sw_notice', true),
                'hours'    => [
                    'Mon' => get_post_meta($id, '_sw_h_mon', true) ?: 'Closed',
                    'Tue' => get_post_meta($id, '_sw_h_tue', true) ?: 'Closed',
                    'Wed' => get_post_meta($id, '_sw_h_wed', true) ?: 'Closed',
                    'Thu' => get_post_meta($id, '_sw_h_thu', true) ?: 'Closed',
                    'Fri' => get_post_meta($id, '_sw_h_fri', true) ?: 'Closed',
                    'Sat' => get_post_meta($id, '_sw_h_sat', true) ?: 'Closed',
                    'Sun' => get_post_meta($id, '_sw_h_sun', true) ?: 'Closed',
                ]
            ];
        }
    }
    wp_reset_postdata();

    ob_start(); ?>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Syne:wght@600;700;800&display=swap');

        .sw-container * {
            box-sizing: border-box !important;
        }

        .sw-container {
            padding: 20px 0;
            background-color: #f9fafb;
            font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, sans-serif !important;
            color: #111827;
            line-height: 1.4;
            max-width: 100% !important;
            margin: 0 auto;
        }

        .sw-search-wrap {
            display: grid;
            grid-template-columns: 1fr auto auto;
            gap: 10px;
            background: #ffffff;
            padding: 8px;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            border: 1px solid #e5e7eb;
            margin-bottom: 12px;
        }

        .sw-search-input-container {
            position: relative;
            display: flex;
            align-items: center;
            background: #f3f4f6;
            border-radius: 8px;
            padding: 0 14px;
            border: 1px solid transparent;
        }

        .sw-search-input-container input {
            width: 100%;
            padding: 10px 0 !important;
            border: none !important;
            outline: none !important;
            background: transparent !important;
            font-family: 'DM Sans', sans-serif !important;
            font-size: 0.95rem !important;
            font-weight: 500 !important;
            color: #111827 !important;
            box-shadow: none !important;
        }

        .sw-search-btn {
            background: #1a7a4a !important;
            color: #fff !important;
            font-family: 'Syne', sans-serif !important;
            font-weight: 700 !important;
            padding: 0 24px !important;
            border-radius: 8px !important;
            border: none !important;
            cursor: pointer;
            font-size: 0.9rem !important;
            transition: all 0.2s ease;
        }

        .sw-search-btn:hover {
            background: #0f4d2e !important;
        }

        .sw-locate-btn {
            background: #fff !important;
            color: #111827 !important;
            font-family: 'DM Sans', sans-serif !important;
            font-weight: 600 !important;
            padding: 0 16px !important;
            border-radius: 8px !important;
            border: 1px solid #e5e7eb !important;
            cursor: pointer;
            font-size: 0.88rem !important;
        }

        /* Filter Pills */
        .sw-filters-wrap {
            display: flex;
            gap: 8px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .sw-filter-pill {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 20px;
            padding: 6px 14px;
            font-size: 0.82rem;
            font-weight: 600;
            color: #4b5563;
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .sw-filter-pill:hover {
            border-color: #1a7a4a;
            color: #1a7a4a;
        }

        .sw-filter-pill.active {
            background: #1a7a4a;
            border-color: #1a7a4a;
            color: #fff;
        }

        /* Open/Closed/Variable Badges & Notice Banner */
        .sw-badge {
            display: inline-flex !important;
            align-items: center !important;
            gap: 4px !important;
            font-size: 0.7rem !important;
            font-weight: 700 !important;
            padding: 3px 8px !important;
            border-radius: 12px !important;
            text-transform: uppercase !important;
            letter-spacing: 0.02em !important;
            white-space: nowrap !important;
            line-height: 1.2 !important;
            flex-shrink: 0 !important;
            max-width: fit-content !important;
        }

        .sw-badge-open {
            background: #d1fae5 !important;
            color: #065f46 !important;
            border: 1px solid #a7f3d0 !important;
        }

        .sw-badge-closed {
            background: #fee2e2 !important;
            color: #991b1b !important;
            border: 1px solid #fecaca !important;
        }

        .sw-badge-variable {
            background: #fef3c7 !important;
            color: #92400e !important;
            border: 1px solid #fde68a !important;
        }

        .sw-notice-banner {
            background: #fffbeb;
            border: 1px solid #fde68a;
            color: #92400e;
            padding: 6px 10px;
            border-radius: 6px;
            font-size: 0.78rem;
            font-weight: 600;
            display: flex;
            align-items: flex-start;
            gap: 6px;
            margin-top: 4px;
            margin-bottom: 4px;
            line-height: 1.3;
        }

        .sw-copy-btn {
            background: #f3f4f6;
            border: 1px solid #d1d5db;
            color: #374151;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            margin-top: 4px;
            transition: all 0.15s ease;
        }

        .sw-copy-btn:hover {
            background: #e5e7eb;
            color: #111827;
        }

        .sw-layout {
            display: grid;
            grid-template-columns: 340px 1fr;
            gap: 20px;
            height: 600px;
        }

        .sw-sidebar {
            background: #ffffff;
            border-radius: 14px;
            border: 1px solid #e5e7eb;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }

        .sw-sidebar-header {
            padding: 12px 16px;
            background: #fff;
            border-bottom: 1px solid #e5e7eb;
        }

        .sw-sidebar-header h3 {
            font-family: 'Syne', sans-serif !important;
            font-weight: 700 !important;
            font-size: 1.1rem !important;
            color: #111827 !important;
            margin: 0 !important;
            padding: 0 !important;
            line-height: 1.2 !important;
        }

        .sw-results-list {
            flex: 1;
            overflow-y: auto;
            padding: 10px;
            background: #f9fafb;
        }

        .sw-results-list::-webkit-scrollbar { width: 5px; }
        .sw-results-list::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 10px; }

        .sw-card {
            width: 100%;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 12px 14px;
            margin-bottom: 8px;
            text-align: left;
            cursor: pointer;
            transition: all 0.15s ease;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .sw-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 8px;
            flex-wrap: wrap;
        }

        .sw-card:hover {
            border-color: #1a7a4a;
            background: #f0fdf4;
        }

        .sw-card.active {
            border-color: #1a7a4a;
            background: #f0fdf4;
            box-shadow: inset 3px 0 0 #1a7a4a;
        }

        .sw-card h4 {
            font-family: 'Syne', sans-serif !important;
            font-weight: 700 !important;
            font-size: 0.95rem !important;
            color: #111827 !important;
            margin: 0 !important;
            padding: 0 !important;
            line-height: 1.3 !important;
            flex: 1 1 140px;
            word-break: break-word;
        }

        .sw-card p {
            font-size: 0.82rem !important;
            color: #6b7280 !important;
            margin: 0 !important;
            padding: 0 !important;
            line-height: 1.3 !important;
        }

        .sw-card-phone-wrap {
            display: flex;
            flex-direction: column;
            gap: 2px;
            margin-top: 2px;
        }

        .sw-card-phone {
            font-size: 0.8rem !important;
            color: #1a7a4a !important;
            font-weight: 600 !important;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            text-decoration: none;
        }

        .sw-map-container {
            border-radius: 14px;
            overflow: hidden;
            border: 1px solid #e5e7eb;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
            background: #e5e7eb;
            position: relative;
        }

        #sw-map {
            width: 100%;
            height: 100%;
            min-height: 400px;
            z-index: 1;
        }

        .leaflet-popup {
            margin-bottom: 30px;
        }

        .leaflet-popup-content-wrapper {
            border-radius: 10px;
            padding: 4px;
        }

        .leaflet-popup-content b {
            font-family: 'Syne', sans-serif !important;
            font-size: 0.95rem !important;
            display: block;
            margin-bottom: 4px;
        }

        .sw-hours-table {
            width: 100%;
            font-size: 11px;
            border-collapse: collapse;
            margin-top: 6px;
        }

        .sw-hours-table td {
            padding: 2px 0;
            vertical-align: top;
            line-height: 1.3;
        }

        .sw-hours-table td.sw-day-label {
            font-weight: 600;
            color: #1a7a4a;
            width: 35px;
            padding-right: 6px;
        }

        .sw-hours-table td.sw-time-val {
            text-align: right;
            word-break: break-word;
            color: #374151;
        }

        @media (max-width: 900px) {
            .sw-layout {
                grid-template-columns: 1fr;
                height: auto;
            }
            .sw-sidebar {
                height: 380px;
                order: 2;
            }
            .sw-map-container {
                height: 400px;
                order: 1;
            }
        }
    </style>

    <div class="sw-container">
        <div class="sw-search-wrap">
            <div class="sw-search-input-container">
                <input type="text" id="sw-search-input" placeholder="Search town, postcode, or store name...">
            </div>
            <button class="sw-search-btn" id="sw-search-btn">Search</button>
            <button class="sw-locate-btn" id="sw-locate-btn">📍 Near Me</button>
        </div>

        <div class="sw-filters-wrap">
            <button class="sw-filter-pill active" data-filter="all">All Locations</button>
            <button class="sw-filter-pill" data-filter="open">🟢 Open Now</button>
            <button class="sw-filter-pill" data-filter="variable">🟡 Variable</button>
            <button class="sw-filter-pill" data-filter="closed">🔴 Closed</button>
        </div>

        <div class="sw-layout">
            <div class="sw-sidebar">
                <div class="sw-sidebar-header">
                    <h3>Locations (<span id="sw-count">0</span>)</h3>
                </div>
                <div class="sw-results-list" id="sw-results-list"></div>
            </div>

            <div class="sw-map-container">
                <div id="sw-map"></div>
            </div>
        </div>
    </div>

    <script>
    function swCopyText(text, btnElement) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function() {
                var oldText = btnElement.innerHTML;
                btnElement.innerHTML = '✅ Copied!';
                setTimeout(function() { btnElement.innerHTML = oldText; }, 2000);
            });
        } else {
            var tempInput = document.createElement('input');
            tempInput.value = text;
            document.body.appendChild(tempInput);
            tempInput.select();
            document.execCommand('copy');
            document.body.removeChild(tempInput);
            var oldText = btnElement.innerHTML;
            btnElement.innerHTML = '✅ Copied!';
            setTimeout(function() { btnElement.innerHTML = oldText; }, 2000);
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        if (typeof L === 'undefined') return;

        var rawData = <?php echo json_encode($locations); ?>;
        var currentStatusFilter = 'all';

        // ADVANCED OPEN / CLOSED / VARIABLE LOGIC
        function getOpenStatus(hoursObj) {
            var daysMap = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            var now = new Date();
            var dayName = daysMap[now.getDay()];
            var todayHours = hoursObj[dayName] ? hoursObj[dayName].trim() : '';

            if (!todayHours || todayHours.toLowerCase() === 'closed') {
                return { isOpen: false, isVariable: false, text: 'Closed Today' };
            }

            if (todayHours.toLowerCase().includes('variable')) {
                return { isOpen: false, isVariable: true, text: 'Times Vary' };
            }

            if (todayHours.toLowerCase().includes('24 hours') || todayHours.toLowerCase().includes('24/7')) {
                return { isOpen: true, isVariable: false, text: 'Open 24 Hours' };
            }

            function parseTimeString(timeStr) {
                timeStr = timeStr.trim().toLowerCase();
                var isPM = timeStr.includes('pm');
                var isAM = timeStr.includes('am');
                var clean = timeStr.replace(/(am|pm)/g, '').trim();
                var timeParts = clean.split(':');
                var hours = parseInt(timeParts[0], 10);
                var minutes = timeParts[1] ? parseInt(timeParts[1], 10) : 0;

                if (isNaN(hours)) return null;

                if (isPM && hours < 12) hours += 12;
                if (isAM && hours === 12) hours = 0;

                return hours * 60 + minutes;
            }

            var currentMins = now.getHours() * 60 + now.getMinutes();
            var shiftWindows = todayHours.split(/[\/,]/);
            var isCurrentlyOpen = false;

            for (var i = 0; i < shiftWindows.length; i++) {
                var rangeParts = shiftWindows[i].split('-');
                if (rangeParts.length === 2) {
                    var openMins = parseTimeString(rangeParts[0]);
                    var closeMins = parseTimeString(rangeParts[1]);

                    if (openMins !== null && closeMins !== null) {
                        if (currentMins >= openMins && currentMins < closeMins) {
                            isCurrentlyOpen = true;
                            break;
                        }
                    }
                }
            }

            if (isCurrentlyOpen) {
                return { isOpen: true, isVariable: false, text: 'Open Now' };
            } else {
                return { isOpen: false, isVariable: false, text: 'Closed' };
            }
        }

        function getBadgeHTML(status) {
            if (status.isVariable) {
                return '<span class="sw-badge sw-badge-variable">🟡 ' + status.text + '</span>';
            }
            if (status.isOpen) {
                return '<span class="sw-badge sw-badge-open">🟢 ' + status.text + '</span>';
            }
            return '<span class="sw-badge sw-badge-closed">🔴 ' + status.text + '</span>';
        }
        
        var map = L.map('sw-map').setView([51.15, -2.9], 9);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        // LEAFLET MARKER CLUSTER GROUP
        var clusterGroup = L.markerClusterGroup ? L.markerClusterGroup() : null;
        if (clusterGroup) {
            map.addLayer(clusterGroup);
        }

        var activeStandaloneMarker = null;

        function restoreActiveMarkerToCluster() {
            if (activeStandaloneMarker && clusterGroup) {
                if (map.hasLayer(activeStandaloneMarker)) {
                    map.removeLayer(activeStandaloneMarker);
                }
                clusterGroup.addLayer(activeStandaloneMarker);
                activeStandaloneMarker = null;
            }
        }

        function isolateMarkerFromCluster(marker) {
            if (!clusterGroup) return;
            if (activeStandaloneMarker && activeStandaloneMarker !== marker) {
                restoreActiveMarkerToCluster();
            }
            if (clusterGroup.hasLayer(marker)) {
                clusterGroup.removeLayer(marker);
                marker.addTo(map);
                activeStandaloneMarker = marker;
            }
        }

        map.on('popupopen', function(e) {
            var marker = e.popup._source;
            if (marker) {
                isolateMarkerFromCluster(marker);
            }

            var closeBtn = e.popup._container.querySelector('.leaflet-popup-close-button');
            if (closeBtn) {
                closeBtn.addEventListener('click', function(ev) {
                    ev.preventDefault();
                    ev.stopPropagation();
                    map.closePopup(e.popup);
                });
            }
        });

        map.on('popupclose', function(e) {
            if (e.popup._source === activeStandaloneMarker) {
                restoreActiveMarkerToCluster();
            }
            document.querySelectorAll('.sw-card').forEach(function(c) {
                c.classList.remove('active');
            });
        });

        var markers = [];

        function buildPopupElement(loc, status) {
            var container = document.createElement('div');
            
            var header = document.createElement('div');
            header.style.cssText = 'display:flex; justify-content:space-between; align-items:flex-start; gap:8px; margin-bottom:6px; flex-wrap:wrap;';
            header.innerHTML = '<b>' + loc.name + '</b>' + getBadgeHTML(status);
            container.appendChild(header);

            if (loc.notice && loc.notice.trim() !== '') {
                var notice = document.createElement('div');
                notice.className = 'sw-notice-banner';
                notice.innerHTML = '<span>⚠️</span><span>' + loc.notice + '</span>';
                container.appendChild(notice);
            }

            var addrP = document.createElement('p');
            addrP.style.cssText = 'margin:0 0 4px 0; color:#6b7280; font-size:12px;';
            addrP.textContent = loc.address;
            container.appendChild(addrP);

            var copyBtn = document.createElement('button');
            copyBtn.className = 'sw-copy-btn';
            copyBtn.textContent = '📋 Copy Address';
            copyBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                swCopyText(loc.address, copyBtn);
            });
            container.appendChild(copyBtn);

            var hasPhone = loc.phone && loc.phone.trim() !== '';
            var hasMobile = loc.mobile && loc.mobile.trim() !== '';

            if (hasPhone || hasMobile) {
                var phoneWrap = document.createElement('div');
                phoneWrap.style.cssText = 'margin-top:8px; margin-bottom:8px; font-size:12px; display:flex; flex-direction:column; gap:2px;';
                if (hasPhone) {
                    phoneWrap.innerHTML += '<a href="tel:' + loc.phone + '" style="color:#1a7a4a; text-decoration:none; font-weight:600;">📞 Tel: ' + loc.phone + '</a>';
                }
                if (hasMobile) {
                    phoneWrap.innerHTML += '<a href="tel:' + loc.mobile + '" style="color:#1a7a4a; text-decoration:none; font-weight:600;">📱 Mob: ' + loc.mobile + '</a>';
                }
                container.appendChild(phoneWrap);
            }

            var table = document.createElement('table');
            table.className = 'sw-hours-table';
            var tBody = '';
            for (var day in loc.hours) {
                tBody += '<tr><td class="sw-day-label">' + day + '</td><td class="sw-time-val">' + loc.hours[day] + '</td></tr>';
            }
            table.innerHTML = tBody;
            container.appendChild(table);

            return container;
        }

        function renderList(data, shouldAutoPan) {
            var listEl = document.getElementById('sw-results-list');
            document.getElementById('sw-count').textContent = data.length;
            listEl.innerHTML = '';

            if (activeStandaloneMarker) {
                if (map.hasLayer(activeStandaloneMarker)) {
                    map.removeLayer(activeStandaloneMarker);
                }
                activeStandaloneMarker = null;
            }

            if (clusterGroup) {
                clusterGroup.clearLayers();
            } else {
                markers.forEach(function(m) { map.removeLayer(m.marker); });
            }
            markers = [];

            var bounds = [];

            data.forEach(function(loc) {
                var status = getOpenStatus(loc.hours);

                var popupOptions = {
                    autoPan: true,
                    autoPanPadding: L.point(30, 30),
                    keepInView: false,
                    closeButton: true
                };

                var marker = L.marker([loc.lat, loc.lng]);
                var popup = L.popup(popupOptions).setContent(buildPopupElement(loc, status));
                marker.bindPopup(popup);

                if (clusterGroup) {
                    clusterGroup.addLayer(marker);
                } else {
                    marker.addTo(map);
                }

                markers.push({ id: loc.id, marker: marker });
                bounds.push([loc.lat, loc.lng]);

                var card = document.createElement('div');
                card.className = 'sw-card';

                var cardHeader = document.createElement('div');
                cardHeader.className = 'sw-card-header';
                cardHeader.innerHTML = '<h4>' + loc.name + '</h4>' + getBadgeHTML(status);
                card.appendChild(cardHeader);

                if (loc.notice && loc.notice.trim() !== '') {
                    var notice = document.createElement('div');
                    notice.className = 'sw-notice-banner';
                    notice.innerHTML = '<span>⚠️</span><span>' + loc.notice + '</span>';
                    card.appendChild(notice);
                }

                var cardAddr = document.createElement('p');
                cardAddr.textContent = loc.address;
                card.appendChild(cardAddr);

                var btnWrap = document.createElement('div');
                var copyBtn = document.createElement('button');
                copyBtn.className = 'sw-copy-btn';
                copyBtn.textContent = '📋 Copy Address';
                copyBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    swCopyText(loc.address, copyBtn);
                });
                btnWrap.appendChild(copyBtn);
                card.appendChild(btnWrap);

                var hasPhone = loc.phone && loc.phone.trim() !== '';
                var hasMobile = loc.mobile && loc.mobile.trim() !== '';

                if (hasPhone || hasMobile) {
                    var phoneWrap = document.createElement('div');
                    phoneWrap.className = 'sw-card-phone-wrap';
                    if (hasPhone) {
                        phoneWrap.innerHTML += '<a href="tel:' + loc.phone + '" class="sw-card-phone" onclick="event.stopPropagation();">📞 Tel: ' + loc.phone + '</a>';
                    }
                    if (hasMobile) {
                        phoneWrap.innerHTML += '<a href="tel:' + loc.mobile + '" class="sw-card-phone" onclick="event.stopPropagation();">📱 Mob: ' + loc.mobile + '</a>';
                    }
                    card.appendChild(phoneWrap);
                }
                
                card.addEventListener('click', function() {
                    document.querySelectorAll('.sw-card').forEach(function(c) {
                        c.classList.remove('active');
                    });
                    card.classList.add('active');

                    // Pull marker out of cluster immediately so it cannot be swallowed
                    isolateMarkerFromCluster(marker);

                    var targetZoom = map.getZoom() < 14 ? 14 : map.getZoom();

                    // Exact pixel offset so tall popup fits cleanly above the pin at any zoom
                    var targetPoint = map.project([loc.lat, loc.lng], targetZoom).subtract([0, 90]);
                    var targetLatLng = map.unproject(targetPoint, targetZoom);

                    map.setView(targetLatLng, targetZoom, { animate: true });

                    setTimeout(function() {
                        marker.openPopup();
                    }, 250);
                });

                listEl.appendChild(card);
            });

            if (shouldAutoPan && bounds.length > 0) {
                var groupBounds = L.latLngBounds(bounds);
                if (!map.getBounds().contains(groupBounds)) {
                    map.fitBounds(groupBounds, { padding: [50, 50], maxZoom: 13 });
                }
            }
        }

        renderList(rawData, false);

        function doSearch(isUserAction) {
            var query = document.getElementById('sw-search-input').value.toLowerCase().trim();
            
            var filtered = rawData.filter(function(loc) {
                var matchesQuery = !query || 
                       loc.name.toLowerCase().includes(query) || 
                       loc.address.toLowerCase().includes(query) || 
                       (loc.phone && loc.phone.toLowerCase().includes(query)) ||
                       (loc.mobile && loc.mobile.toLowerCase().includes(query)) ||
                       (loc.notice && loc.notice.toLowerCase().includes(query));

                var status = getOpenStatus(loc.hours);
                var matchesFilter = true;
                if (currentStatusFilter === 'open') matchesFilter = status.isOpen;
                if (currentStatusFilter === 'variable') matchesFilter = status.isVariable;
                if (currentStatusFilter === 'closed') matchesFilter = (!status.isOpen && !status.isVariable);

                return matchesQuery && matchesFilter;
            });

            renderList(filtered, isUserAction === true);
        }

        document.getElementById('sw-search-btn').addEventListener('click', function() { doSearch(true); });
        document.getElementById('sw-search-input').addEventListener('keyup', function(e) {
            if (e.key === 'Enter') doSearch(true);
        });

        document.querySelectorAll('.sw-filter-pill').forEach(function(btn) {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.sw-filter-pill').forEach(function(b) {
                    b.classList.remove('active');
                });
                btn.classList.add('active');
                currentStatusFilter = btn.getAttribute('data-filter');
                doSearch(false);
            });
        });

        document.getElementById('sw-locate-btn').addEventListener('click', function() {
            if (!navigator.geolocation) {
                alert('Geolocation is not supported by your browser.');
                return;
            }
            navigator.geolocation.getCurrentPosition(function(pos) {
                var uLat = pos.coords.latitude;
                var uLng = pos.coords.longitude;
                map.flyTo([uLat, uLng], 12);
                L.circleMarker([uLat, uLng], { radius: 8, color: '#1a7a4a', fillColor: '#d1fae5', fillOpacity: 0.9 }).addTo(map).bindPopup('<b>Your Location</b>').openPopup();
            }, function() {
                alert('Unable to retrieve your location.');
            });
        });
    });
    </script>

    <?php
    return ob_get_clean();
});

// 7. DASHBOARD WIDGET: QUICK ADD LOCATION
add_action('wp_dashboard_setup', function() {
    wp_add_dashboard_widget(
        'sw_quick_add_widget',
        '📍 Quick Add Drop-Off Location',
        'sw_render_dashboard_widget'
    );
});

function sw_render_dashboard_widget() {
    if (isset($_POST['sw_quick_add_nonce']) && wp_verify_nonce($_POST['sw_quick_add_nonce'], 'sw_quick_add_action')) {
        if (!current_user_can('edit_posts')) {
            echo '<div class="notice notice-error inline" style="margin:0 0 15px 0;"><p>❌ Permission denied.</p></div>';
            return;
        }

        $title  = sanitize_text_field($_POST['sw_quick_title'] ?? '');
        $addr   = sanitize_text_field($_POST['sw_quick_addr'] ?? '');
        $phone  = sanitize_text_field($_POST['sw_quick_phone'] ?? '');
        $mobile = sanitize_text_field($_POST['sw_quick_mobile'] ?? '');
        $cat    = sanitize_text_field($_POST['sw_quick_cat'] ?? 'Partner Store');
        $lat    = sanitize_text_field($_POST['sw_quick_lat'] ?? '');
        $lng    = sanitize_text_field($_POST['sw_quick_lng'] ?? '');
        $notice = sanitize_text_field($_POST['sw_quick_notice'] ?? '');

        if (!empty($title) && !empty($addr)) {
            $post_id = wp_insert_post([
                'post_title'  => $title,
                'post_type'   => 'sw_location',
                'post_status' => 'publish'
            ]);

            if ($post_id) {
                update_post_meta($post_id, '_sw_addr', $addr);
                update_post_meta($post_id, '_sw_phone', $phone);
                update_post_meta($post_id, '_sw_mobile', $mobile);
                update_post_meta($post_id, '_sw_cat', $cat);
                update_post_meta($post_id, '_sw_notice', $notice);
                
                if (!empty($lat) && !empty($lng)) {
                    update_post_meta($post_id, '_sw_lat', $lat);
                    update_post_meta($post_id, '_sw_lng', $lng);
                } else {
                    $coords = sw_get_coords_from_address($addr);
                    if ($coords) {
                        update_post_meta($post_id, '_sw_lat', $coords['lat']);
                        update_post_meta($post_id, '_sw_lng', $coords['lng']);
                    }
                }

                $days = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
                foreach ($days as $day) {
                    $hours = sanitize_text_field($_POST['sw_quick_h_' . $day] ?? 'Closed');
                    update_post_meta($post_id, '_sw_h_' . $day, $hours);
                }

                echo '<div class="notice notice-success inline" style="margin:0 0 15px 0;"><p>✅ <strong>' . esc_html($title) . '</strong> published successfully!</p></div>';
            }
        } else {
            echo '<div class="notice notice-error inline" style="margin:0 0 15px 0;"><p>❌ Please fill in both Location Name and Address.</p></div>';
        }
    }
    ?>
    <form method="post" style="display: flex; flex-direction: column; gap: 12px;">
        <?php wp_nonce_field('sw_quick_add_action', 'sw_quick_add_nonce'); ?>

        <div>
            <label style="font-weight: 600; display: block; margin-bottom: 4px;">Location Name *</label>
            <input type="text" name="sw_quick_title" placeholder="e.g. Clevedon Library" style="width:100%;" required>
        </div>

        <div>
            <label style="font-weight: 600; display: block; margin-bottom: 4px;">Address *</label>
            <div style="display: flex; gap: 6px;">
                <input type="text" id="sw_dash_addr" name="sw_quick_addr" placeholder="e.g. 37 Old Church Rd, Clevedon, BS21 6NN" style="flex:1;" required>
                <button type="button" id="sw_dash_geocode_btn" class="button button-secondary" style="white-space:nowrap;">📍 Find Coords</button>
            </div>
        </div>

        <div>
            <label style="font-weight: 600; display: block; margin-bottom: 4px; color: #b45309;">⚠️ Exceptional Hours Notice (Optional)</label>
            <input type="text" name="sw_quick_notice" placeholder="e.g. Closed Easter Monday" style="width:100%; border-color: #f59e0b;">
        </div>

        <div style="display: flex; gap: 10px;">
            <div style="flex: 1;">
                <label style="font-weight: 600; display: block; margin-bottom: 4px;">Telephone (Optional)</label>
                <input type="text" name="sw_quick_phone" placeholder="e.g. 01275 123456" style="width:100%;">
            </div>
            <div style="flex: 1;">
                <label style="font-weight: 600; display: block; margin-bottom: 4px;">Mobile (Optional)</label>
                <input type="text" name="sw_quick_mobile" placeholder="e.g. 07123 456789" style="width:100%;">
            </div>
        </div>

        <div>
            <label style="font-weight: 600; display: block; margin-bottom: 4px;">Category</label>
            <input type="text" name="sw_quick_cat" value="Partner Store" style="width:100%;">
        </div>

        <div style="display: flex; gap: 10px;">
            <div style="flex: 1;">
                <label style="font-weight: 600; display: block; margin-bottom: 4px;">Latitude</label>
                <input type="text" id="sw_dash_lat" name="sw_quick_lat" placeholder="Auto-filled" style="width:100%;">
            </div>
            <div style="flex: 1;">
                <label style="font-weight: 600; display: block; margin-bottom: 4px;">Longitude</label>
                <input type="text" id="sw_dash_lng" name="sw_quick_lng" placeholder="Auto-filled" style="width:100%;">
            </div>
        </div>

        <details style="background: #f6f7f7; padding: 10px 12px; border-radius: 6px; border: 1px solid #c3c4c7;">
            <summary style="font-weight: 600; cursor: pointer; user-select: none;">🕒 Opening Hours (Click to edit)</summary>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-top: 10px;">
                <div><label style="font-size: 11px; font-weight: 600;">Mon</label><input type="text" name="sw_quick_h_mon" value="09:00 - 17:00" style="width:100%;"></div>
                <div><label style="font-size: 11px; font-weight: 600;">Tue</label><input type="text" name="sw_quick_h_tue" value="09:00 - 17:00" style="width:100%;"></div>
                <div><label style="font-size: 11px; font-weight: 600;">Wed</label><input type="text" name="sw_quick_h_wed" value="09:00 - 17:00" style="width:100%;"></div>
                <div><label style="font-size: 11px; font-weight: 600;">Thu</label><input type="text" name="sw_quick_h_thu" value="09:00 - 17:00" style="width:100%;"></div>
                <div><label style="font-size: 11px; font-weight: 600;">Fri</label><input type="text" name="sw_quick_h_fri" value="09:00 - 17:00" style="width:100%;"></div>
                <div><label style="font-size: 11px; font-weight: 600;">Sat</label><input type="text" name="sw_quick_h_sat" value="Closed" style="width:100%;"></div>
                <div style="grid-column: span 2;"><label style="font-size: 11px; font-weight: 600;">Sun</label><input type="text" name="sw_quick_h_sun" value="Closed" style="width:100%;"></div>
            </div>
        </details>

        <div style="padding-top: 6px; border-top: 1px solid #f0f0f1; display: flex; justify-content: flex-end;">
            <button type="submit" class="button button-primary" style="font-weight: 600;">+ Save Drop-Off Location</button>
        </div>
    </form>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var btn = document.getElementById('sw_dash_geocode_btn');
        if (!btn) return;

        btn.addEventListener('click', function() {
            var addrField = document.getElementById('sw_dash_addr');
            var address = addrField ? addrField.value.trim() : '';

            if (!address) {
                alert('Please enter an address first.');
                return;
            }

            btn.textContent = 'Searching...';
            btn.disabled = true;

            var url = 'https://nominatim.openstreetmap.org/search?format=json&q=' + encodeURIComponent(address) + '&limit=1';

            fetch(url, { headers: { 'User-Agent': 'WP-DropOff-Locator-Dashboard' } })
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    if (data && data.length > 0) {
                        document.getElementById('sw_dash_lat').value = data[0].lat;
                        document.getElementById('sw_dash_lng').value = data[0].lon;
                        btn.textContent = '✅ Found!';
                        setTimeout(function() { btn.textContent = '📍 Find Coords'; btn.disabled = false; }, 2000);
                    } else {
                        alert('Could not locate coordinates. You can submit anyway and backend geocoding will retry.');
                        btn.textContent = '📍 Find Coords';
                        btn.disabled = false;
                    }
                })
                .catch(function() {
                    alert('Geocoding service timeout. Feel free to save, and the backend will process it.');
                    btn.textContent = '📍 Find Coords';
                    btn.disabled = false;
                });
        });
    });
    </script>
    <?php
}