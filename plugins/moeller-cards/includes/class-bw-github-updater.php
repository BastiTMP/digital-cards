<?php

if (!defined('ABSPATH')) exit;

if (!class_exists('BW_Digital_Cards_GitHub_Updater')) {
final class BW_Digital_Cards_GitHub_Updater {
    private string $plugin_file;
    private string $plugin_basename;
    private string $slug;
    private string $asset_name;
    private string $current_version;
    private string $repository = 'BastiTMP/digital-cards';

    public function __construct(string $plugin_file, string $current_version, string $slug, string $asset_name) {
        $this->plugin_file = $plugin_file;
        $this->plugin_basename = plugin_basename($plugin_file);
        $this->current_version = $current_version;
        $this->slug = $slug;
        $this->asset_name = $asset_name;
        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_for_update']);
        add_filter('plugins_api', [$this, 'plugin_information'], 20, 3);
    }

    public function check_for_update($transient) {
        if (!is_object($transient) || empty($transient->checked[$this->plugin_basename])) return $transient;
        $release = $this->release();
        if (!$release) return $transient;
        $new_version = ltrim((string)($release['tag_name'] ?? ''), 'vV');
        $package = $this->package_url($release);
        if (!$new_version || !$package || !version_compare($new_version, $this->current_version, '>')) return $transient;

        $transient->response[$this->plugin_basename] = (object)[
            'id' => 'github.com/' . $this->repository . '/' . $this->slug,
            'slug' => $this->slug,
            'plugin' => $this->plugin_basename,
            'new_version' => $new_version,
            'url' => (string)($release['html_url'] ?? 'https://github.com/' . $this->repository),
            'package' => $package,
            'icons' => [],
            'banners' => [],
            'tested' => '',
            'requires_php' => '8.0',
        ];
        return $transient;
    }

    public function plugin_information($result, $action, $args) {
        if ($action !== 'plugin_information' || empty($args->slug) || $args->slug !== $this->slug) return $result;
        $release = $this->release();
        if (!$release) return $result;
        $version = ltrim((string)($release['tag_name'] ?? ''), 'vV');
        return (object)[
            'name' => ucwords(str_replace('-', ' ', $this->slug)),
            'slug' => $this->slug,
            'version' => $version,
            'author' => '<a href="https://boldwerk.de/">boldwerk</a>',
            'homepage' => 'https://github.com/' . $this->repository,
            'download_link' => $this->package_url($release),
            'requires' => '6.0',
            'requires_php' => '8.0',
            'sections' => [
                'description' => 'Digitale Mitarbeiter-Visitenkarten mit QR-Code, VCard, PWA und Teilen-Funktion.',
                'changelog' => nl2br(esc_html((string)($release['body'] ?? 'Aktualisierung über GitHub Releases.'))),
            ],
        ];
    }

    private function release(): ?array {
        $cache_key = 'bw_cards_release_' . md5($this->repository);
        $cached = get_site_transient($cache_key);
        if (is_array($cached)) return $cached;
        $response = wp_remote_get('https://api.github.com/repos/' . $this->repository . '/releases/latest', [
            'timeout' => 12,
            'headers' => ['Accept' => 'application/vnd.github+json', 'User-Agent' => 'boldwerk-digital-cards'],
        ]);
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) return null;
        $release = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($release)) return null;
        set_site_transient($cache_key, $release, 6 * HOUR_IN_SECONDS);
        return $release;
    }

    private function package_url(array $release): string {
        foreach (($release['assets'] ?? []) as $asset) {
            if (($asset['name'] ?? '') === $this->asset_name) return esc_url_raw((string)($asset['browser_download_url'] ?? ''));
        }
        return '';
    }
}
}
