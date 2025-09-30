<?php

function ig_handle_controle_votacao_post()
{

    if (isset($_POST['action']) && $_POST['action'] === 'ig_change_status' && isset($_POST['ig_change_status_nonce']) && wp_verify_nonce($_POST['ig_change_status_nonce'], 'ig_change_status_action')) {


        if (!current_user_can('gerenciar_mostra')) {
            wp_die('Permissão negada.');
        }


        $status_atual = get_option('ig_votacao_status');
        $novo_status = ($status_atual === 'aberta') ? 'encerrada' : 'aberta';


        update_option('ig_votacao_status', $novo_status);


        $redirect_url = wp_get_referer();
        $redirect_url = add_query_arg('status_changed', '1', $redirect_url);

        wp_safe_redirect($redirect_url);
        exit();
    }
}

add_action('init', 'ig_handle_controle_votacao_post');

?>