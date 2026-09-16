<?php
/**
 * Plugin Name: Möller Cards
 * Description: Mobile digitale Mitarbeiter-Visitenkarten mit VCard, QR-Code, PWA und GA4-Events.
 * Version: 1.0.0
 * Author: Möller Mobility
 * Update URI: https://github.com/BastiTMP/digital-cards
 */

if (!defined('ABSPATH')) exit;
require_once __DIR__ . '/includes/class-bw-github-updater.php';

if (!class_exists('MM_Digital_Cards')) {
final class MM_Digital_Cards {
    const POST_TYPE = 'mm_digital_card';
    const VERSION = '1.0.0';
    const FIELDS = [
        'first_name' => 'Vorname', 'last_name' => 'Nachname', 'job_title' => 'Jobtitel',
        'phone' => 'Direktwahl (optional; sonst Zentrale)', 'mobile' => 'Mobilnummer (optional)',
        'whatsapp' => 'WhatsApp (optional)', 'email' => 'E-Mail-Adresse',
        'share_app' => 'WhatsApp-App zum Teilen',
        'extra_links' => 'Zusätzliche Links (je Zeile: Titel|URL)'
    ];
    const COMPANY_FIELDS = [
        'company' => 'Unternehmen', 'company_phone' => 'Telefon Zentrale',
        'website' => 'Website', 'logo_url' => 'Logo URL', 'street' => 'Straße', 'zip' => 'PLZ', 'city' => 'Ort',
        'inventory_url' => 'Fahrzeugbestand URL', 'configurator_url' => 'Konfigurator URL'
    ];

    public function __construct() {
        add_action('init', [$this, 'register'], 4);
        add_action('init', [$this, 'maybe_upgrade'], 5);
        add_action('admin_menu', [$this, 'company_menu']);
        add_action('admin_init', [$this, 'register_company_settings']);
        add_action('add_meta_boxes', [$this, 'metabox']);
        add_action('save_post_' . self::POST_TYPE, [$this, 'save']);
        add_action('template_redirect', [$this, 'route'], 0);
        add_action('admin_notices', [$this, 'wallet_notice']);
        add_filter('manage_' . self::POST_TYPE . '_posts_columns', [$this, 'columns']);
        add_action('manage_' . self::POST_TYPE . '_posts_custom_column', [$this, 'column'], 10, 2);
    }

    public static function activate() {
        $self = new self();
        $self->register();
        if (!$self->card_by_slug('sebastian')) {
            $id = wp_insert_post(['post_type'=>self::POST_TYPE,'post_status'=>'publish','post_title'=>'Sebastian Möller']);
            if ($id && !is_wp_error($id)) {
                $seed = [
                    'slug'=>'sebastian','active'=>'1','first_name'=>'Sebastian','last_name'=>'Möller',
                    'job_title'=>'Geschäftsführer','phone'=>'','mobile'=>'0173 5848857',
                    'whatsapp'=>'0173 5848857','email'=>'sebastian.moeller@ah-moeller.de','share_app'=>'business'
                ];
                foreach($seed as $key=>$value) update_post_meta($id, '_mm_'.$key, $value);
            }
        }
        flush_rewrite_rules();
    }

    public function register() {
        register_post_type(self::POST_TYPE, [
            'labels' => ['name' => 'Digitale Visitenkarten', 'singular_name' => 'Digitale Visitenkarte', 'add_new_item' => 'Visitenkarte hinzufügen', 'edit_item' => 'Visitenkarte bearbeiten'],
            'public' => false, 'show_ui' => true, 'show_in_menu' => true, 'menu_icon' => 'dashicons-id',
            'supports' => ['title', 'thumbnail'], 'capability_type' => 'post', 'map_meta_cap' => true,
        ]);
    }

    public function maybe_upgrade() {
        $current = get_option('mm_cards_company');
        if (is_array($current) && $current) {
            if (empty($current['logo_url'])) {
                $current['logo_url'] = 'https://moeller-mobility.de/wp-content/uploads/2024/04/weblogo.png';
                update_option('mm_cards_company', $current);
            }
            return;
        }
        update_option('mm_cards_company', [
            'company'=>'Autohaus Möller GmbH & Co. KG', 'company_phone'=>'03691 77227',
            'website'=>'https://moeller-mobility.de/', 'logo_url'=>'https://moeller-mobility.de/wp-content/uploads/2024/04/weblogo.png', 'street'=>'Bleichrasen 20',
            'zip'=>'99817', 'city'=>'Eisenach',
            'inventory_url'=>'https://moeller-mobility.de/auto-kaufen-eisenach/',
            'configurator_url'=>'https://moeller-mobility.de/konfigurator/'
        ]);
        foreach (get_posts(['post_type'=>self::POST_TYPE,'post_status'=>'any','numberposts'=>-1]) as $card) {
            if (preg_replace('/\D/', '', get_post_meta($card->ID, '_mm_phone', true)) === '0369177227') delete_post_meta($card->ID, '_mm_phone');
        }
    }

    public function company_menu() {
        add_submenu_page('edit.php?post_type='.self::POST_TYPE, 'Möller Cards Einstellungen', 'Einstellungen', 'manage_options', 'mm-cards-settings', [$this, 'company_settings_page']);
    }

    public function register_company_settings() {
        register_setting('mm_cards_company_group', 'mm_cards_company', ['type'=>'array','sanitize_callback'=>[$this,'sanitize_company']]);
    }

    public function sanitize_company($input) {
        $out = [];
        foreach (self::COMPANY_FIELDS as $key=>$label) {
            $raw = $input[$key] ?? '';
            $out[$key] = (str_ends_with($key, '_url') || $key === 'website') ? esc_url_raw($raw) : sanitize_text_field($raw);
        }
        return $out;
    }

    public function company_settings_page() {
        if (!current_user_can('manage_options')) return;
        wp_enqueue_media();
        $values = wp_parse_args(get_option('mm_cards_company', []), array_fill_keys(array_keys(self::COMPANY_FIELDS), ''));
        echo '<div class="wrap"><h1>Möller Cards – Unternehmensdaten</h1><p>Diese Angaben gelten zentral für alle digitalen Visitenkarten.</p><form method="post" action="options.php">';
        settings_fields('mm_cards_company_group');
        echo '<table class="form-table" role="presentation">';
        foreach (self::COMPANY_FIELDS as $key=>$label) {
            echo '<tr><th scope="row"><label for="mmc_'.$key.'">'.esc_html($label).'</label></th><td><input class="regular-text" id="mmc_'.$key.'" name="mm_cards_company['.$key.']" value="'.esc_attr($values[$key]).'">';
            if ($key === 'logo_url') echo ' <button type="button" class="button" id="mmc_choose_logo">Logo aus Mediathek wählen</button> <button type="button" class="button-link-delete" id="mmc_remove_logo">Entfernen</button><div><img id="mmc_logo_preview" src="'.esc_url($values[$key]).'" alt="" style="max-width:260px;max-height:100px;margin-top:12px;'.($values[$key] ? '' : 'display:none;').'"></div>';
            echo '</td></tr>';
        }
        echo '</table>'; submit_button('Unternehmensdaten speichern'); echo '</form><script>jQuery(function($){let frame;$("#mmc_choose_logo").on("click",function(e){e.preventDefault();if(frame){frame.open();return;}frame=wp.media({title:"Logo auswählen",button:{text:"Logo verwenden"},multiple:false});frame.on("select",function(){const item=frame.state().get("selection").first().toJSON();$("#mmc_logo_url").val(item.url);$("#mmc_logo_preview").attr("src",item.url).show();});frame.open();});$("#mmc_remove_logo").on("click",function(){ $("#mmc_logo_url").val("");$("#mmc_logo_preview").hide();});});</script></div>';
    }

    public function metabox() {
        add_meta_box('mm_card_data', 'Kontaktdaten', [$this, 'metabox_html'], self::POST_TYPE, 'normal', 'high');
        add_meta_box('mm_card_status', 'Veröffentlichung', [$this, 'status_html'], self::POST_TYPE, 'side');
    }

    public function metabox_html($post) {
        wp_enqueue_media();
        wp_nonce_field('mm_card_save', 'mm_card_nonce');
        echo '<style>.mm-card-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.mm-card-grid label{display:block;font-weight:600;margin-bottom:5px}.mm-card-grid input,.mm-card-grid textarea,.mm-card-grid select{width:100%}@media(max-width:782px){.mm-card-grid{grid-template-columns:1fr}}</style><div class="mm-card-grid">';
        foreach (self::FIELDS as $key => $label) {
            $value = get_post_meta($post->ID, '_mm_' . $key, true);
            echo '<div><label for="mm_' . esc_attr($key) . '">' . esc_html($label) . '</label>';
            if ($key === 'extra_links') echo '<textarea rows="4" id="mm_' . esc_attr($key) . '" name="mm_' . esc_attr($key) . '">' . esc_textarea($value) . '</textarea>';
            elseif ($key === 'share_app') echo '<select id="mm_share_app" name="mm_share_app"><option value="standard" ' . selected($value, 'standard', false) . '>WhatsApp Standard</option><option value="business" ' . selected($value, 'business', false) . '>WhatsApp Business</option></select>';
            else {
                echo '<input id="mm_' . esc_attr($key) . '" name="mm_' . esc_attr($key) . '" value="' . esc_attr($value) . '" type="' . ($key === 'email' ? 'email' : 'text') . '">';
                if ($key === 'photo_url') echo '<p><button type="button" class="button" id="mm_choose_photo">Foto aus Mediathek wählen</button> <button type="button" class="button-link-delete" id="mm_remove_photo">Entfernen</button></p><img id="mm_photo_preview" src="'.esc_url($value).'" alt="" style="max-width:160px;max-height:160px;object-fit:cover;border-radius:50%;'.($value ? '' : 'display:none;').'">';
            }
            echo '</div>';
        }
        $slug = get_post_meta($post->ID, '_mm_slug', true);
        echo '<div><label for="mm_slug">Permanenter Slug</label><input required id="mm_slug" name="mm_slug" value="' . esc_attr($slug) . '" placeholder="sebastian"><p class="description">Öffentliche URL: ' . esc_html(home_url('/')) . '<strong>' . esc_html($slug ?: 'slug') . '</strong></p></div></div><script>jQuery(function($){let frame;$("#mm_choose_photo").on("click",function(e){e.preventDefault();if(frame){frame.open();return;}frame=wp.media({title:"Porträtfoto auswählen",button:{text:"Foto verwenden"},multiple:false});frame.on("select",function(){const item=frame.state().get("selection").first().toJSON();$("#mm_photo_url").val(item.url);$("#mm_photo_preview").attr("src",item.url).show();});frame.open();});$("#mm_remove_photo").on("click",function(){ $("#mm_photo_url").val("");$("#mm_photo_preview").hide();});});</script>';
    }

    public function status_html($post) {
        $active = get_post_meta($post->ID, '_mm_active', true);
        echo '<label><input type="checkbox" name="mm_active" value="1" ' . checked($active, '1', false) . '> Karte aktiv</label>';
        if ($active && ($slug = get_post_meta($post->ID, '_mm_slug', true))) {
            echo '<p><a class="button button-secondary" target="_blank" href="' . esc_url(home_url('/' . $slug . '/')) . '">Karte ansehen</a></p>';
            echo '<p><a class="button button-secondary" target="_blank" href="' . esc_url(add_query_arg('owner', '1', home_url('/' . $slug . '/'))) . '">Eigene QR-Ansicht</a></p>';
            echo '<p><a class="button button-secondary" target="_blank" href="' . esc_url(home_url('/?mm_card_qr=' . $slug)) . '">QR-Code (druckfähig)</a></p>';
        }
    }

    public function save($post_id) {
        if (!isset($_POST['mm_card_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mm_card_nonce'])), 'mm_card_save')) return;
        if (!current_user_can('edit_post', $post_id) || wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) return;
        foreach (self::FIELDS as $key => $label) {
            $raw = isset($_POST['mm_' . $key]) ? wp_unslash($_POST['mm_' . $key]) : '';
            if ($key === 'email') $value = sanitize_email($raw);
            elseif (str_ends_with($key, '_url') || $key === 'website') $value = esc_url_raw($raw);
            elseif ($key === 'extra_links') $value = sanitize_textarea_field($raw);
            elseif ($key === 'share_app') $value = in_array($raw, ['standard','business'], true) ? $raw : 'standard';
            else $value = sanitize_text_field($raw);
            update_post_meta($post_id, '_mm_' . $key, $value);
        }
        $slug = sanitize_title(wp_unslash($_POST['mm_slug'] ?? ''));
        if ($slug) update_post_meta($post_id, '_mm_slug', $slug);
        update_post_meta($post_id, '_mm_active', isset($_POST['mm_active']) ? '1' : '0');
    }

    private function card_by_slug($slug) {
        $posts = get_posts(['post_type' => self::POST_TYPE, 'post_status' => 'publish', 'numberposts' => 1, 'meta_query' => [
            ['key' => '_mm_slug', 'value' => sanitize_title($slug)], ['key' => '_mm_active', 'value' => '1']
        ]]);
        return $posts ? $posts[0] : null;
    }

    private function data($post) {
        $d = ['id' => $post->ID, 'slug' => get_post_meta($post->ID, '_mm_slug', true), 'photo' => get_the_post_thumbnail_url($post->ID, 'medium_large') ?: ''];
        foreach (self::FIELDS as $key => $label) $d[$key] = get_post_meta($post->ID, '_mm_' . $key, true);
        if (!$d['share_app']) $d['share_app'] = in_array($d['slug'], ['sebastian','daniel'], true) ? 'business' : 'standard';
        $company = get_option('mm_cards_company', []);
        foreach (self::COMPANY_FIELDS as $key=>$label) $d[$key] = $company[$key] ?? '';
        if (!$d['phone']) $d['phone'] = $d['company_phone'];
        $d['url'] = home_url('/' . $d['slug'] . '/');
        return $d;
    }

    public function route() {
        if (isset($_GET['mm_card_vcf'])) return $this->vcard(sanitize_title(wp_unslash($_GET['mm_card_vcf'])));
        if (isset($_GET['mm_card_manifest'])) return $this->manifest(sanitize_title(wp_unslash($_GET['mm_card_manifest'])));
        if (isset($_GET['mm_card_qr'])) return $this->qr(sanitize_title(wp_unslash($_GET['mm_card_qr'])));
        if (isset($_GET['mm_card_sw'])) return $this->service_worker(sanitize_title(wp_unslash($_GET['mm_card_sw'])));
        $path = trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
        if (!$path || str_contains($path, '/')) return;
        $card = $this->card_by_slug($path);
        if (!$card) return;
        status_header(200); nocache_headers(); header('X-Robots-Tag: noindex, follow', true);
        $this->render($this->data($card)); exit;
    }

    private function vcard($slug) {
        $post = $this->card_by_slug($slug); if (!$post) return;
        $d = $this->data($post);
        $e = fn($v) => str_replace(["\\", ";", ",", "\r", "\n"], ["\\\\", "\\;", "\\,", '', "\\n"], (string)$v);
        $lines = ['BEGIN:VCARD','VERSION:3.0','N:' . $e($d['last_name']) . ';' . $e($d['first_name']) . ';;;','FN:' . $e(trim($d['first_name'].' '.$d['last_name'])),'ORG:' . $e($d['company']),'TITLE:' . $e($d['job_title'])];
        if ($d['phone']) $lines[] = 'TEL;TYPE=WORK,VOICE:' . $e($d['phone']);
        if ($d['mobile']) $lines[] = 'TEL;TYPE=CELL,VOICE:' . $e($d['mobile']);
        if ($d['email']) $lines[] = 'EMAIL;TYPE=WORK:' . $e($d['email']);
        if ($d['website']) $lines[] = 'URL:' . $e($d['website']);
        if (trim($d['street'].$d['zip'].$d['city'])) $lines[] = 'ADR;TYPE=WORK:;;' . $e($d['street']) . ';' . $e($d['city']) . ';;' . $e($d['zip']) . ';Deutschland';
        $lines[] = 'END:VCARD';
        header('Content-Type: text/vcard; charset=utf-8'); header('Content-Disposition: attachment; filename="' . $slug . '.vcf"');
        echo implode("\r\n", $lines); exit;
    }

    private function manifest($slug) {
        $post = $this->card_by_slug($slug); if (!$post) return;
        $d = $this->data($post); header('Content-Type: application/manifest+json; charset=utf-8');
        $icon = get_site_icon_url(512) ?: get_site_icon_url(192);
        echo wp_json_encode(['name'=>'Meine Visitenkarte – '.trim($d['first_name'].' '.$d['last_name']),'short_name'=>$d['first_name'] ?: 'Visitenkarte','start_url'=>$d['url'].'?owner=1','scope'=>$d['url'],'display'=>'standalone','background_color'=>'#0b0c0e','theme_color'=>'#0b0c0e','icons'=>$icon ? [['src'=>$icon,'sizes'=>'any','type'=>'image/png','purpose'=>'any']] : []], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); exit;
    }

    private function qr($slug) {
        $post = $this->card_by_slug($slug); if (!$post) return;
        $url = home_url('/'.$slug.'/'); $cache = 'mm_card_qr_' . md5($url); $svg = get_transient($cache);
        if (!$svg) { $r = wp_remote_get('https://api.qrserver.com/v1/create-qr-code/?format=png&size=1000x1000&margin=24&data=' . rawurlencode($url), ['timeout'=>12]); if (!is_wp_error($r) && wp_remote_retrieve_response_code($r) === 200) { $svg = wp_remote_retrieve_body($r); set_transient($cache, $svg, WEEK_IN_SECONDS); } }
        if (!$svg) { status_header(503); echo 'QR-Code vorübergehend nicht verfügbar.'; exit; }
        header('Content-Type: image/png'); header('Content-Disposition: inline; filename="'.$slug.'-qr.png"'); echo $svg; exit;
    }

    private function service_worker($slug) {
        $post = $this->card_by_slug($slug); if (!$post) return;
        $d = $this->data($post); $owner_url = add_query_arg('owner', '1', $d['url']); header('Content-Type: application/javascript; charset=utf-8'); header('Service-Worker-Allowed: /');
        $cache = 'mm-card-' . self::VERSION . '-' . $slug;
        echo 'const C='.wp_json_encode($cache).',U='.wp_json_encode($owner_url).';self.addEventListener("install",e=>e.waitUntil(caches.open(C).then(c=>c.add(U))));self.addEventListener("activate",e=>e.waitUntil(caches.keys().then(k=>Promise.all(k.filter(x=>x!==C&&x.startsWith("mm-card-")).map(x=>caches.delete(x))))));self.addEventListener("fetch",e=>{if(e.request.mode==="navigate")e.respondWith(fetch(e.request).catch(()=>caches.match(U)));});'; exit;
    }

    private function render($d) {
        $assets = plugin_dir_url(__FILE__) . 'assets/'; $name = trim($d['first_name'].' '.$d['last_name']);
        $owner_view = isset($_GET['owner']) && sanitize_text_field(wp_unslash($_GET['owner'])) === '1';
        $share_title = $name . ' | Möller Mobility';
        $share_description = trim($d['job_title'] . ($d['company'] ? ' bei ' . $d['company'] : ''));
        $share_image = $d['photo'] ?: ($d['logo_url'] ?: get_site_icon_url(512));
        $share_url = add_query_arg('share', '1', $d['url']);
        add_filter('wpseo_title', fn() => $share_title);
        add_filter('wpseo_metadesc', fn() => $share_description);
        add_filter('wpseo_canonical', fn() => $d['url']);
        add_filter('wpseo_opengraph_title', fn() => $share_title);
        add_filter('wpseo_opengraph_desc', fn() => $share_description);
        add_filter('wpseo_opengraph_url', fn() => $d['url']);
        add_filter('wpseo_opengraph_type', fn() => 'profile');
        add_filter('wpseo_opengraph_image', fn() => $share_image);
        add_filter('wpseo_twitter_title', fn() => $share_title);
        add_filter('wpseo_twitter_description', fn() => $share_description);
        add_filter('wpseo_twitter_image', fn() => $share_image);
        add_filter('wpseo_robots', fn() => 'noindex, follow');
        add_filter('wp_robots', function($robots) {
            unset($robots['index']);
            $robots['noindex'] = true;
            $robots['follow'] = true;
            return $robots;
        });
        $tel = preg_replace('/[^0-9+]/', '', $d['mobile'] ?: $d['phone']);
        $wa = preg_replace('/\D/', '', $d['whatsapp']); if ($wa && str_starts_with($wa, '0')) $wa = '49'.substr($wa,1);
        $address = trim($d['street'].' '.$d['zip'].' '.$d['city']);
        $route = $address ? 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode($address) : '';
        $site_icon = get_site_icon_url(180) ?: $d['logo_url'];
        header('Content-Type: text/html; charset=' . get_bloginfo('charset'));
?><!doctype html><html lang="de"><head><meta charset="<?php bloginfo('charset'); ?>"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="theme-color" content="#0b0c0e"><meta name="robots" content="noindex,follow"><title><?php echo esc_html($name.' – '.$d['company']); ?></title><meta name="description" content="Digitale Visitenkarte von <?php echo esc_attr($name.', '.$d['job_title'].' bei '.$d['company']); ?>"><link rel="canonical" href="<?php echo esc_url($d['url']); ?>"><link rel="manifest" href="<?php echo esc_url(home_url('/?mm_card_manifest='.$d['slug'])); ?>"><link rel="apple-touch-icon" href="<?php echo esc_url($site_icon); ?>"><?php wp_head(); ?><link rel="stylesheet" href="<?php echo esc_url($assets.'card.css?ver='.self::VERSION); ?>"></head><body data-owner="<?php echo esc_attr($d['slug']); ?>" class="<?php echo $owner_view ? 'owner-view' : 'contact-view'; ?>"><main class="card"><section class="hero"><?php if(!empty($d['logo_url'])): ?><img class="brand-logo" src="<?php echo esc_url($d['logo_url']); ?>" alt="<?php echo esc_attr($d['company']); ?>"><?php else: ?><div class="brand">MÖLLER <span>MOBILITY</span></div><?php endif; ?><?php if($d['photo']): ?><img class="portrait" src="<?php echo esc_url($d['photo']); ?>" alt="<?php echo esc_attr($name); ?>" width="180" height="180"><?php else: ?><div class="portrait placeholder" aria-hidden="true"><?php echo esc_html(mb_substr($d['first_name'],0,1).mb_substr($d['last_name'],0,1)); ?></div><?php endif; ?><h1><?php echo esc_html($name); ?></h1><?php if($d['job_title']): ?><p class="role"><?php echo esc_html($d['job_title']); ?></p><?php endif; ?><?php if($d['company']): ?><p class="company"><?php echo esc_html($d['company']); ?></p><?php endif; ?></section>
        <?php if ($owner_view): ?>
        <section class="owner-intro"><span>MEINE VISITENKARTE</span><h2>QR-Code zeigen</h2><p>Der Kunde scannt den Code und kann deine Kontaktdaten direkt speichern.</p></section>
        <section class="qr"><img src="<?php echo esc_url(home_url('/?mm_card_qr='.$d['slug'])); ?>" alt="QR-Code zur digitalen Visitenkarte" width="260" height="260"><p>QR-Code scannen</p></section>
        <?php else: ?>
        <p class="welcome">Ihr persönlicher Ansprechpartner bei Möller Mobility.</p>
        <a class="primary track" data-event="digital_card_contact_save" href="<?php echo esc_url(home_url('/?mm_card_vcf='.$d['slug'])); ?>">Kontakt speichern</a>
        <section class="quick" aria-label="Schnellaktionen">
        <?php if($tel): ?><a class="track" data-event="digital_card_call" href="tel:<?php echo esc_attr($tel); ?>"><b>☎</b><span>Anrufen</span></a><?php endif; ?>
        <?php if($wa): ?><a class="track" data-event="digital_card_whatsapp" href="https://wa.me/<?php echo esc_attr($wa); ?>"><b>WA</b><span>WhatsApp</span></a><?php endif; ?>
        <?php if($d['email']): ?><a class="track" data-event="digital_card_email" href="mailto:<?php echo esc_attr($d['email']); ?>"><b>✉</b><span>E-Mail</span></a><?php endif; ?>
        <?php if($route): ?><a class="track" data-event="digital_card_route" href="<?php echo esc_url($route); ?>"><b>⌖</b><span>Route</span></a><?php endif; ?></section>
        <?php endif; ?>
        <section class="links">
        <?php if (!$owner_view): ?>
        <?php if($d['inventory_url']): ?><a class="track" data-event="digital_card_inventory" href="<?php echo esc_url($d['inventory_url']); ?>">Unsere Fahrzeuge <span>›</span></a><?php endif; ?>
        <?php if($d['configurator_url']): ?><a class="track" data-event="digital_card_configurator" href="<?php echo esc_url($d['configurator_url']); ?>">MG konfigurieren <span>›</span></a><?php endif; ?>
        <?php if($d['website']): ?><a class="track" data-event="digital_card_website" href="<?php echo esc_url($d['website']); ?>">Möller Mobility <span>›</span></a><?php endif; ?>
        <button id="share" class="track" data-event="digital_card_share">Visitenkarte teilen <span>›</span></button>
        <?php else: ?>
        <button id="share-whatsapp" class="track" data-event="digital_card_share_whatsapp" data-app="<?php echo esc_attr($d['share_app']); ?>"><?php echo $d['share_app'] === 'business' ? 'Mit WhatsApp Business teilen' : 'Mit WhatsApp teilen'; ?> <span>›</span></button>
        <button id="share" class="track" data-event="digital_card_share">Kontaktlink teilen <span>›</span></button>
        <button id="install">Zum Homescreen hinzufügen <span>›</span></button>
        <?php endif; ?>
        </section>
        <?php if ($owner_view): ?>
        <section id="install-help" class="install-help" hidden><button class="close" aria-label="Schließen">×</button><h2>Auf dem Homescreen speichern</h2><p class="ios">Tippe unten auf <strong>Teilen</strong> und danach auf <strong>Zum Home-Bildschirm</strong>.</p><p class="android">Öffne das Browser-Menü und tippe auf <strong>App installieren</strong> oder <strong>Zum Startbildschirm hinzufügen</strong>.</p></section>
        <?php endif; ?>
        <?php if($d['company'] || $address): ?><footer><?php if($d['company']) echo esc_html($d['company']); ?><?php if($d['company'] && $address): ?><br><?php endif; ?><?php if($address) echo esc_html(trim($d['street'].', '.$d['zip'].' '.$d['city'])); ?></footer><?php endif; ?></main><div id="toast" role="status" aria-live="polite"></div><script>window.MM_CARD=<?php echo wp_json_encode(['url'=>$d['url'],'name'=>$name,'owner'=>$d['slug'],'sw'=>home_url('/?mm_card_sw='.$d['slug'])]); ?>;</script><script src="<?php echo esc_url($assets.'card.js?ver='.self::VERSION); ?>" defer></script><?php wp_footer(); ?></body></html><?php exit;
    }

    public function columns($cols) { $cols['mm_url']='Permanente URL'; $cols['mm_active']='Status'; return $cols; }
    public function column($col,$id) { if($col==='mm_url'){ $s=get_post_meta($id,'_mm_slug',true); echo $s?'<a target="_blank" href="'.esc_url(home_url('/'.$s.'/')).'">'.esc_html(home_url('/'.$s.'/')).'</a>':'—'; } if($col==='mm_active') echo get_post_meta($id,'_mm_active',true)==='1'?'Aktiv':'Inaktiv'; }
    public function wallet_notice() { $screen=get_current_screen(); if($screen && $screen->post_type===self::POST_TYPE) echo '<div class="notice notice-info"><p><strong>Wallet:</strong> Apple- und Google-Wallet werden aktiviert, sobald Pass Type ID/Zertifikat bzw. Google-Wallet-Issuer und Service Account vorliegen. Bis dahin werden keine funktionslosen Wallet-Buttons angezeigt.</p></div>'; }
}

register_activation_hook(__FILE__, ['MM_Digital_Cards','activate']);
new MM_Digital_Cards();
new BW_Digital_Cards_GitHub_Updater(__FILE__, MM_Digital_Cards::VERSION, 'moeller-cards', 'moeller-cards.zip');
}
