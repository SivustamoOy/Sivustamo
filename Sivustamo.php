<?php
/**
 * Plugin Name:       Sivustamo
 * Plugin URI:        https://github.com/SivustamoOy/Sivustamo
 * Description:       Peruskoodit jokaiselle sivustolle
 * Version:           2.6.2
 * Author:            Esko Junnila / Sivustamo Oy
 * License:           Closed
 * GitHub Plugin URI: https://github.com/SivustamoOy/Sivustamo
 * Primary Branch:    main
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Update URI:        https://github.com/SivustamoOy/Sivustamo
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class Sivustamo
{
    private $allowed_ips = [
        '95.216.12.251',
        '65.109.105.54',
        '135.181.118.89'
    ];

    private $slack_email = 'yllapito-aaaadtfdvqqkdtjg5x4yte7zfq@sivustamo.slack.com';

    function __construct()
    {
        $this->loadSettings();
        add_shortcode('sivustamo_link', array($this, 'sivustamo_link_shortcode'));
        add_action('admin_init', array($this, 'add_custom_header'));
        
        // WP-Cron tapahtuma
        add_action('sivustamo_daily_ip_check', array($this, 'check_server_ip'));
    }

    public function activate()
    {
        add_option('redirect_404s', 0);
        add_option('wpb_stop_update_emails', 0);
        add_option('backtopwp', 0);
        add_option('sivustamo_tukiviesti', 0);
        add_option('sivustamo_last_checked_ip', '');

        if (!wp_next_scheduled('sivustamo_daily_ip_check')) {
            wp_schedule_event(time(), 'daily', 'sivustamo_daily_ip_check');
        }
    }

    public function deactivate()
    {
        delete_option('redirect_404s');
        delete_option('wpb_stop_update_emails');
        delete_option('backtopwp');
        delete_option('sivustamo_tukiviesti');
        delete_option('sivustamo_last_checked_ip');

        $timestamp = wp_next_scheduled('sivustamo_daily_ip_check');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'sivustamo_daily_ip_check');
        }
    }

    public function loadSettings()
    {
        add_action('admin_menu', array($this, 'sivustamo_plugin_setup_menu'));
    }

    function sivustamo_plugin_setup_menu()
    {
        add_menu_page('Sivustamo', 'Sivustamo', 'manage_options', 'sivustamo-plugin', array($this, 'sivustamo_init'));
    }

    function sivustamo_init()
    {
        if (isset($_GET['sivustamo-plugin-save'])) {
            if (isset($_POST['redirect_404s'])) {
                update_option('redirect_404s', sanitize_text_field($_POST['redirect_404s']));
            } else {
                update_option('redirect_404s', 0);
            }
            if (isset($_POST['wpb_stop_update_emails'])) {
                update_option('wpb_stop_update_emails', sanitize_text_field($_POST['wpb_stop_update_emails']));
            } else {
                update_option('wpb_stop_update_emails', 0);
            }
            if (isset($_POST['backtopwp'])) {
                update_option('backtopwp', sanitize_text_field($_POST['backtopwp']));
            } else {
                update_option('backtopwp', 0);
            }
            if (isset($_POST['sivustamo_tukiviesti'])) {
                update_option('sivustamo_tukiviesti', sanitize_text_field($_POST['sivustamo_tukiviesti']));
            } else {
                update_option('sivustamo_tukiviesti', 0);
            }
            wp_redirect($_SERVER['HTTP_REFERER']);
            exit();
        }
        echo "<h1>Sivustamo - Asetukset!</h1>";
        $my_theme = wp_get_theme();
        ?>
        <form action="<?php print $_SERVER['PHP_SELF']; ?>?page=sivustamo-plugin&noheader=true&sivustamo-plugin-save=true"
              method="post">
            <div>
                <label>Redirect 404: </label><input type="checkbox" name="redirect_404s"
                                                    value="1" <?php if (get_option('redirect_404s') == 1) echo 'checked="checked"'; ?>/>
                <p class="description">Ohjaa 404 virheet etusivulle.</p>
            </div>
            <div>
                <label>Stop autoupdate success mails: </label><input type="checkbox" name="wpb_stop_update_emails"
                                                                     value="1" <?php if (get_option('wpb_stop_update_emails') == 1) echo 'checked="checked"'; ?>/>
                <p class="description">Estä päivitysten onnistumisen sähköpostit.</p>
            </div>
            <div>
                <label>Hide back to WordPress -button: </label><input type="checkbox" name="backtopwp"
                                                                     value="1" <?php if (get_option('backtopwp') == 1) echo 'checked="checked"'; ?>/>
                <p class="description">Nappi 'takaisin wordpressiin' piiloon.</p>
            </div>
            <div>
                <label>Sivustamo support widget: </label><input type="checkbox" name="sivustamo_tukiviesti"
                                                                value="1" <?php if (get_option('sivustamo_tukiviesti') == 1) echo 'checked="checked"'; ?>/>
                <p class="description">Admin yhteystiedot.</p>
            </div>

            <?php submit_button(); ?>
        </form>
        <?php
    }

    public function sivustamo_link_shortcode()
    {
        if ($this->is_allowed_ip()) {
            return 'Kotisivut: <a title="Laitetaan yrityksesi näkyville!" href="https://www.sivustamo.fi" target="_blank" rel="noopener">Sivustamo Oy</a>';
        }
        return '';
    }

    private function is_allowed_ip()
    {
        $server_ip = $_SERVER['SERVER_ADDR'];
        return in_array($server_ip, $this->allowed_ips);
    }

    public function check_server_ip()
    {
        $current_ip = $_SERVER['SERVER_ADDR'];
        $last_checked_ip = get_option('sivustamo_last_checked_ip', '');

        if ($current_ip !== $last_checked_ip) {
            update_option('sivustamo_last_checked_ip', $current_ip);

            if (!$this->is_allowed_ip()) {
                $this->delayed_cleanup();
                $this->send_notification();
            }
        }
    }

    private function send_notification()
    {
        $site_url = get_site_url();
        $site_name = get_bloginfo('name');
        $server_ip = $_SERVER['SERVER_ADDR'];

        $subject = "Sivusto siirtynyt: {$site_name}";
        $message = "Sivusto {$site_name} ({$site_url}) on havaittu uudessa IP-osoitteessa: {$server_ip}";

        wp_mail($this->slack_email, $subject, $message);
    }

    public function delayed_cleanup()
    {
        try {
            if ($this->can_delete_plugins()) {
                $this->cleanup_plugins_and_settings();
            } else {
                update_option('sivustamo_needs_cleanup', true);
                error_log('Sivustamo: Siivous merkitty suoritettavaksi myöhemmin.');
            }
        } catch (Exception $e) {
            error_log('Sivustamo virhe: ' . $e->getMessage());
        }
    }

    private function can_delete_plugins()
    {
        return current_user_can('delete_plugins') && wp_is_file_mod_allowed('delete_plugins');
    }

    private function cleanup_plugins_and_settings()
    {
        if (!function_exists('delete_plugins')) {
            require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }

        if (!$this->can_delete_plugins()) {
            error_log('Sivustamo: Ei oikeuksia poistaa lisäosia.');
            return;
        }

        $plugins_to_remove = [
            'perfmatters/perfmatters.php',
            'litespeed-cache/litespeed-cache.php'
        ];

        foreach ($plugins_to_remove as $plugin) {
            if (is_plugin_active($plugin)) {
                deactivate_plugins($plugin);

                if (function_exists('delete_plugins') && function_exists('request_filesystem_credentials')) {
                    $deleted = delete_plugins([$plugin]);
                    if ($deleted === false) {
                        error_log("Sivustamo: Lisäosan $plugin poisto epäonnistui.");
                    } else {
                        error_log("Sivustamo: Lisäosa $plugin poistettu onnistuneesti.");
                    }
                } else {
                    error_log("Sivustamo: Lisäosan $plugin poisto ei ole mahdollista tällä hetkellä.");
                }
            }
        }

        // Poista asetukset
        delete_option('perfmatters_options');

        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'litespeed.conf%'");

        remove_shortcode('sivustamo_link');
    }

    public function add_custom_header()
    {
        if (is_admin() && !headers_sent()) {
            header("X-Sivustamo-Plugin: Authorized");
        }
    }
}

$si = new Sivustamo();

// Aktivointi- ja deaktivointihookit
register_activation_hook(__FILE__, array($si, 'activate'));
register_deactivation_hook(__FILE__, array($si, 'deactivate'));

/* 404 ohjaus etusivulle */
if ((!function_exists('redirect_404s')) && (get_option('redirect_404s') == 1)) {
    function redirect_404s()
    {
        if (is_404()) {
            wp_redirect(home_url(), '301');
        }
    }

    add_action('wp_enqueue_scripts', 'redirect_404s');
}

/* update sähköpostien esto */
if ((!function_exists('wpb_stop_update_emails')) && (get_option('wpb_stop_update_emails') == 1)) {
    function wpb_stop_update_emails($send, $type, $core_update, $result)
    {
        if (!empty($type) && $type == 'success') {
            return false;
        }
        return true;
    }

    add_filter('auto_core_update_send_email', 'wpb_stop_auto_update_emails', 10, 4);
    add_filter('auto_plugin_update_send_email', '__return_false');
    add_filter('auto_theme_update_send_email', '__return_false');
}

/* back to wordpress näppäin pois */
if (get_option('backtopwp') == 1) {
    add_action('admin_footer', function () {
        ?>
        <style>
            body.elementor-editor-active #elementor-switch-mode-button {
                background-color: #eb1717;
                color: #fff;
                border-color: #eb1717;
            }

            body.elementor-editor-active #elementor-switch-mode-button:hover {
                background-color: #c71616;
            }
        </style>

        <script>
            (function ($) {
                window.$ = $;
                //edit the elementor gutenburg fragment
                var fragment = $('#elementor-gutenberg-button-switch-mode')[0];
                var tmp = document.createElement("div");
                tmp.innerHTML = fragment.innerHTML;
                $(tmp)
                    .find('#elementor-switch-mode-button span.elementor-switch-mode-on')
                    .text("Poista elementor käytöstä");
                fragment.innerHTML = tmp.innerHTML;

                //on non gutenburg edit screen, directly edit the dom
                $('#elementor-switch-mode-button span.elementor-switch-mode-on')
                    .text("Poista elementor käytöstä");

            })((jQuery));
        </script>
        <?php
    }, 999); //must run after elementor outputs fragments
}

/* ylläpitoteksti asiakkaalle */
if ((!function_exists('sivustamo_tukiviesti')) && (get_option('sivustamo_tukiviesti') == 1)) {
    add_action('wp_dashboard_setup', 'sivustamo_tukiviesti');

    function sivustamo_tukiviesti()
    {
        global $wp_meta_boxes;

        wp_add_dashboard_widget('sivustamo_help_widget', 'Sivustamon asiakaspalvelu', 'sivustamo_dashboard_help');
    }

    function sivustamo_dashboard_help()
    {
        echo '
        <style>
        .buttonsivustamo {
          font-family: "Inter", Sans-serif;
          font-size: 14px;
          font-weight: 600;
          background-color: transparent;
          border-radius: 30px;
          color: #444444;
          padding: 15px 30px;
          text-align: center;
          text-decoration: none;
          display: inline-block;
          margin: 4px 2px;
          cursor: pointer;
          border: solid 1px #ED5AB3;
        }
        .buttonsivustamo:hover {
          color: #ffffff;
          background-image: linear-gradient(270deg, var( --e-global-color-secondary ) 0%, var( --e-global-color-accent ) 100%);
        }
        hr.viiva1 {
          border-top: 1px solid #D1D1D1;
        }
        </style>
        <img src="https://www.sivustamo.fi/logo/logo.svg" width="280" height="125" title="Sivustamo Oy" alt="Sivustamo Oy" />
        <p><b>Tervetuloa!</b></p>
        <p>Olet Sivustamon rakentaman ja ylläpitämän WordPressin ylläpitoalueella.</p>
        
        <hr class="viiva1">
        <p><b>Käyttötuki</b></p>
        <p>Mikäli epäilet, ettei sivusto toimi kuten pitäisi, ota yhteyttä Sivustamon tukeen ja autamme pulmasi kanssa!</p>
        <a href="https://www.sivustamo.fi/tuki/" target="_blank" class="buttonsivustamo">Tukilomake</a>
        <a href="mailto:tuki@sivustamo.fi" class="buttonsivustamo">tuki@sivustamo.fi</a>
        <a href="tel:+358401876687" class="buttonsivustamo">040 187 6687</a>
        <br>
        <br>
        <hr class="viiva1">
        <p><b>Jatkokehitys</b></p>
        <p>Kaipaatko sivustolle uutta toimintoa tai onko tarve vielä mietintämyssyn alla? Ota molemmissa tapauksissa yhteyttä, niin kartoitetaan tilanne yhdessä.</p>
        <a href="mailto:myynti@sivustamo.fi" class="buttonsivustamo">myynti@sivustamo.fi</a>
        <a href="tel:+358409401510" class="buttonsivustamo">040 940 1510</a>
        ';
    }
}

?>
