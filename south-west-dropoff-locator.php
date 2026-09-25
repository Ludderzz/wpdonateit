<?php
/**
 * Plugin Name: South West Drop-Off Locator Pro
 * Description: Version 6.5 - Rebuilt with Smart CSV Upsert, Premium UI v2.0 & Mobile Auto-Pan Fixes.
 * Version: 6.5
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

// 3. ADMIN META BOX
add_action('add_meta_boxes', function() {
    add_meta_box('sw_details', 'Location Details & Daily Hours', 'sw_render_metabox', 'sw_location', 'normal', 'high');
});

function sw_render_metabox($post) {
    $days = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
    $addr = get_post_meta($post->ID, '_sw_addr', true);
    $cat  = get_post_meta($post->ID, '_sw_cat', true);
    $lat  = get_post_meta($post->ID, '_sw_lat', true);
    $lng  = get_post_meta($post->ID, '_sw_lng', true);
    ?>
    <p><label><strong>Address</strong></label><br>
    <input type="text" name="sw_addr" value="<?php echo esc_attr($addr); ?>" style="width:100%;"></p>
    
    <p><label><strong>Category</strong></label><br>
    <input type="text" name="sw_cat" value="<?php echo esc_attr($cat); ?>" style="width:100%;"></p>
    
    <div style="display: flex; gap: 15px; background: #f0f0f1; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
        <div style="flex: 1;"><label><strong>Latitude</strong></label><input type="text" name="sw_lat" value="<?php echo esc_attr($lat); ?>" style="width:100%;"></div>
        <div style="flex: 1;"><label><strong>Longitude</strong></label><input type="text" name="sw_lng" value="<?php echo esc_attr($lng); ?>" style="width:100%;"></div>
    </div>

    <h4>Opening Hours</h4>
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
        <?php foreach($days as $day): 
            $val = get_post_meta($post->ID, '_sw_h_' . $day, true); ?>
            <p><label style="text-transform: capitalize;"><strong><?php echo $day; ?></strong></label><br>
            <input type="text" name="sw_h_<?php echo $day; ?>" value="<?php echo esc_attr($val); ?>" style="width:100%;"></p>
        <?php endforeach; ?>
    </div>
    <?php
}

// 4. GEOCODING & SAVE LOGIC
function sw_get_coords_from_address($address) {
    $url = "https://nominatim.openstreetmap.org/search?format=json&q=".urlencode($address)."&limit=1";
    $response = wp_remote_get($url, ['user-agent' => 'WP-Locator-Pro']);
    if (is_wp_error($response)) return false;
    $data = json_decode(wp_remote_retrieve_body($response), true);
    return (!empty($data)) ? ['lat' => $data[0]['lat'], 'lng' => $data[0]['lon']] : false;
}

add_action('save_post', function($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!isset($_POST['sw_addr'])) return;

    update_post_meta($post_id, '_sw_addr', sanitize_text_field($_POST['sw_addr']));
    update_post_meta($post_id, '_sw_cat', sanitize_text_field($_POST['sw_cat']));

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

// 5. BULK IMPORT (WITH SMART UPSERT UPDATE LOGIC)
add_action('admin_menu', function() {
    add_submenu_page('edit.php?post_type=sw_location', 'Bulk Import', 'Bulk Import', 'manage_options', 'sw-import', 'sw_render_import_page');
});

function sw_render_import_page() {
    if (isset($_POST['sw_import_nonce']) && wp_verify_nonce($_POST['sw_import_nonce'], 'sw_import_action')) {
        if (!empty($_FILES['sw_csv']['tmp_name'])) {
            $handle = fopen($_FILES['sw_csv']['tmp_name'], 'r');
            fgetcsv($handle); // Skip header row
            $updated_count = 0;
            $inserted_count = 0;

            while (($data = fgetcsv($handle)) !== FALSE) {
                if (empty($data[0])) continue;

                $title = sanitize_text_field($data[0]);

                // Check if location already exists by title
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
                    update_post_meta($post_id, '_sw_cat', sanitize_text_field($data[2] ?? 'Partner Store'));
                    update_post_meta($post_id, '_sw_h_mon', sanitize_text_field($data[3] ?? 'Closed'));
                    update_post_meta($post_id, '_sw_h_tue', sanitize_text_field($data[4] ?? 'Closed'));
                    update_post_meta($post_id, '_sw_h_wed', sanitize_text_field($data[5] ?? 'Closed'));
                    update_post_meta($post_id, '_sw_h_thu', sanitize_text_field($data[6] ?? 'Closed'));
                    update_post_meta($post_id, '_sw_h_fri', sanitize_text_field($data[7] ?? 'Closed'));
                    update_post_meta($post_id, '_sw_h_sat', sanitize_text_field($data[8] ?? 'Closed'));
                    update_post_meta($post_id, '_sw_h_sun', sanitize_text_field($data[9] ?? 'Closed'));

                    if (!empty($data[10]) && !empty($data[11])) {
                        update_post_meta($post_id, '_sw_lat', sanitize_text_field($data[10]));
                        update_post_meta($post_id, '_sw_lng', sanitize_text_field($data[11]));
                    }
                }
            }
            fclose($handle);
            echo "<div class='updated'><p><strong>Import Complete!</strong> Added $inserted_count new locations and updated $updated_count existing locations.</p></div>";
        }
    }
    ?>
    <div class="wrap">
        <h1>Bulk Import Locations</h1>
        <p>Upload your CSV file below. If a location already exists, its metadata will be updated without creating duplicates.</p>
        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('sw_import_action', 'sw_import_nonce'); ?>
            <input type="file" name="sw_csv" accept=".csv" required style="margin-bottom:15px; display:block;">
            <?php submit_button('Run Import'); ?>
        </form>
    </div>
    <?php
}

// 6. FRONTEND SHORTCODE [dropoff_map]
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style('leaflet-css', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css');
    wp_enqueue_script('leaflet-js', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', [], null, true);
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
                'category' => get_post_meta($id, '_sw_cat', true) ?: 'Partner Store',
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

    /* BASE OVERRIDES TO STOP THEME INHERITANCE */
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

    /* SEARCH BAR TIGHTENING */
    .sw-search-wrap {
        display: grid;
        grid-template-columns: 1fr auto auto;
        gap: 10px;
        background: #ffffff;
        padding: 8px;
        border-radius: 12px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        border: 1px solid #e5e7eb;
        margin-bottom: 20px;
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

    /* MAIN LAYOUT */
    .sw-layout {
        display: grid;
        grid-template-columns: 340px 1fr; /* Tighter sidebar */
        gap: 20px;
        height: 600px; /* Reduced total height */
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

    /* FORCED HEADER OVERRIDES TO OVERRULE THEME DENSITY */
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

    /* TIGHT CARDS */
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
        gap: 4px;
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
    }

    .sw-card p {
        font-size: 0.82rem !important;
        color: #6b7280 !important;
        margin: 0 !important;
        padding: 0 !important;
        line-height: 1.3 !important;
    }

    /* MAP CONTAINER */
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

    /* POPUP FIXES */
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
document.addEventListener('DOMContentLoaded', function() {
    if (typeof L === 'undefined') return;

    var rawData = <?php echo json_encode($locations); ?>;
    
    // Configure Map with auto-pan top padding
    var map = L.map('sw-map', {
        autoPanPaddingTopLeft: L.point(30, 100),
        autoPanPaddingBottomRight: L.point(30, 30)
    }).setView([51.15, -2.9], 9);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);

    // PREVENT #close HASH JUMP ON POPUP CLOSE
    map.on('popupopen', function(e) {
        var closeBtn = e.popup._container.querySelector('.leaflet-popup-close-button');
        if (closeBtn) {
            closeBtn.addEventListener('click', function(ev) {
                ev.preventDefault();
                ev.stopPropagation();
                map.closePopup(e.popup);
            });
        }
    });

    var markers = [];

    function buildPopupHTML(loc) {
        var html = '<b>' + loc.name + '</b>';
        html += '<p style="margin:0 0 8px 0; color:#6b7280; font-size:12px;">' + loc.address + '</p>';
        html += '<table style="width:100%; font-size:12px; border-collapse:collapse;">';
        for (var day in loc.hours) {
            html += '<tr><td style="padding:2px 6px 2px 0; font-weight:600; color:#1a7a4a;">' + day + '</td><td style="padding:2px 0; text-align:right;">' + loc.hours[day] + '</td></tr>';
        }
        html += '</table>';
        return html;
    }

    function renderList(data) {
        var listEl = document.getElementById('sw-results-list');
        document.getElementById('sw-count').textContent = data.length;
        listEl.innerHTML = '';

        // Clear map markers
        markers.forEach(function(m) { map.removeLayer(m.marker); });
        markers = [];

        data.forEach(function(loc, index) {
            var popupOptions = {
                autoPan: true,
                autoPanPaddingTopLeft: L.point(20, 110),
                autoPanPaddingBottomRight: L.point(20, 20),
                keepInView: false,
                closeButton: true
            };

            // Add Marker
            var marker = L.marker([loc.lat, loc.lng]).addTo(map);
            var popup = L.popup(popupOptions).setContent(buildPopupHTML(loc));
            marker.bindPopup(popup);
            markers.push({ id: loc.id, marker: marker });

            // Add Sidebar Card
            var card = document.createElement('div');
            card.className = 'sw-card';
            card.innerHTML = '<h4>' + loc.name + '</h4><p>' + loc.address + '</p>';
            
            card.addEventListener('click', function() {
                document.querySelectorAll('.sw-card').forEach(c => c.classList.remove('active'));
                card.classList.add('active');
                
                var offsetLat = loc.lat + 0.0025;
                map.flyTo([offsetLat, loc.lng], 14, { duration: 0.8 });
                
                setTimeout(function() {
                    marker.openPopup();
                }, 250);
            });

            listEl.appendChild(card);
        });
    }

    renderList(rawData);

    // Search Filter
    function doSearch() {
        var query = document.getElementById('sw-search-input').value.toLowerCase().trim();
        if (!query) {
            renderList(rawData);
            return;
        }
        var filtered = rawData.filter(function(loc) {
            return loc.name.toLowerCase().includes(query) || loc.address.toLowerCase().includes(query);
        });
        renderList(filtered);
        if (filtered.length > 0) {
            map.flyTo([filtered[0].lat + 0.0025, filtered[0].lng], 12);
        }
    }

    document.getElementById('sw-search-btn').addEventListener('click', doSearch);
    document.getElementById('sw-search-input').addEventListener('keyup', function(e) {
        if (e.key === 'Enter') doSearch();
    });

    // Geolocation "Near Me"
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