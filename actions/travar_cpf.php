<?php


     add_action("wp_ajax_ig_finalize_cpf", "ig_ajax_finalize_cpf_callback");
            add_action("wp_ajax_nopriv_ig_finalize_cpf", "ig_ajax_finalize_cpf_callback");
            function ig_ajax_finalize_cpf_callback()
            {

                $status_votacao = get_option('ig_votacao_status', 'aberta');
                if ($status_votacao === 'encerrada') {
                    wp_send_json_error('A votação já foi encerrada.', 403);
                    return;
                }
                if (!isset($_POST["cpf"])) {
                    wp_send_json_error("CPF não fornecido.", 400);
                }

                global $wpdb;

                $cpf = preg_replace("/[^0-9]/is", "", sanitize_text_field($_POST["cpf"]));

                if (empty($cpf)) {
                    wp_send_json_error("CPF inválido.", 400);
                }

                $result = $wpdb->insert(
                    "{$wpdb->prefix}ig_cpf_finalizados",
                    ["cpf" => $cpf],
                    ["%s"]
                );

                if ($result === false) {
                    wp_send_json_error("Ocorreu um erro no banco de dados.");
                } else {
                    wp_send_json_success("Avaliação finalizada.");
                }
            }

?>