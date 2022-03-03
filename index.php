<?php
/**
 * Plugin Name:       Sivustamo base
 * Plugin URI:        https://github.com/SivustamoOy/Sivustamo
 * Description:       Peruskoodit jokaiselle sivustolle
 * Version:           1.0.1
 * Author:            Matti Mieskonen
 * License:           Closed
 * GitHub Plugin URI: https://github.com/SivustamoOy/Sivustamo
 * GitHub Plugin URI: SivustamoOy/Sivustamo
 * Primary Branch:    main
 */

/* 404 ohjaus etusivulle */
function redirect_404s() {
if(is_404()) {
wp_redirect(home_url(), '301');
}
}
add_action('wp_enqueue_scripts', 'redirect_404s');

/* update sähköpostien esto */
function wpb_stop_update_emails( $send, $type, $core_update, $result ) {
    if ( ! empty( $type ) && $type == 'success' ) {
        return false;
    }
    return true;
}
add_filter( 'auto_core_update_send_email', 'wpb_stop_auto_update_emails', 10, 4 );
add_filter( 'auto_plugin_update_send_email', '__return_false' );
add_filter( 'auto_theme_update_send_email', '__return_false' );

/* back to wordpress näppäin pois */
//functions.php, or in plugin

//change "back to wordpress editor" button text

add_action('admin_footer', function(){

    ?>
    <style>

        body.elementor-editor-active #elementor-switch-mode-button{
            background-color: #eb1717;
            color: #fff;
            border-color: #eb1717;
        }
        body.elementor-editor-active #elementor-switch-mode-button:hover {
            background-color: #c71616;
        }

    </style>

    <script>

        ( function( $ ) {
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

        })(( jQuery ));
    </script>

    <?php

}, 999); //must run after elementor outputs fragments

/* ylläpitoteksti asiakkaalle */
add_action('wp_dashboard_setup', 'my_custom_dashboard_widgets');

function my_custom_dashboard_widgets() {
    global $wp_meta_boxes;

    wp_add_dashboard_widget('custom_help_widget', 'Sivustamon asiakaspalvelu', 'custom_dashboard_help');
}

function custom_dashboard_help() {
    echo '
	<style>
.buttonsivustamo {
  font-family: "Poppins", Sans-serif;
  font-size: 14px;
  font-weight: 700;
  background-color: #00a9c7;
  border-radius: 30px;
  color: white;
  padding: 15px 32px;
  text-align: center;
  text-decoration: none;
  display: inline-block;
  margin: 4px 2px;
  cursor: pointer;
}
hr.viiva1 {
  border-top: 1px solid #00a9c7;
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
<a href="tel:0401876687" class="buttonsivustamo">040 187 6687</a>
<br>
<br>
<hr class="viiva1">
<p><b>Jatkokehitys</b></p>
<p>Kaipaatko sivustolle uutta toimintoa tai onko tarve vielä mietintämyssyn alla? Ota molemmissa tapauksissa yhteyttä, niin kartoitetaan tilanne yhdessä.</p>
<a href="mailto:myynti@sivustamo.fi" class="buttonsivustamo">myynti@sivustamo.fi</a>
<a href="tel:+358401876612" class="buttonsivustamo">040 187 6612</a>
	
	';
}