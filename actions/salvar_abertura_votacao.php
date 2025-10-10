<?php
function ig_handle_dashboard_post() {
    if (isset($_POST['action']) && $_POST['action'] === 'ig_save_status' && isset($_POST['ig_save_status_nonce']) && wp_verify_nonce($_POST['ig_save_status_nonce'], 'ig_save_status_action')) {
        
        if (!current_user_can('gerenciar_mostra')) {
            wp_die('Permissão negada.');
        }

        $novo_status = sanitize_text_field($_POST['ig_votacao_status']);
    
        if (in_array($novo_status, ['aberta', 'encerrada'])) {
            update_option('ig_votacao_status', $novo_status);
        }

        $redirect_url = wp_get_referer();
        $redirect_url = add_query_arg('status_changed', '1', $redirect_url);
        
        wp_safe_redirect($redirect_url);
        exit();
    }
}

add_action('init', 'ig_handle_dashboard_post');

?>