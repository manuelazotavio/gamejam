<?php


    add_action("wp_ajax_ig_save_ifsp_eval", "ig_save_ifsp_eval_ajax");
            function ig_save_ifsp_eval_ajax()
            {
                check_ajax_referer("ig_save_ifsp_eval_nonce");
                if (!current_user_can("avaliar_jogos_ifsp")) {
                    wp_send_json_error(["message" => "Permissão negada."]);
                    return;
                }
                global $wpdb;
                $user_id = get_current_user_id();
                $jogo_id = intval($_POST["jogo_id"]);
                $criterio_id = intval($_POST["criterio_id"]);
                $nota = floatval($_POST["nota"]);
                if ($jogo_id <= 0 || $criterio_id <= 0 || $nota < 0 || $nota > 5) {
                    wp_send_json_error(["message" => "Dados inválidos."]);
                    return;
                }
                $result = $wpdb->replace(
                    "{$wpdb->prefix}ig_avaliacoes_ifsp",
                    [
                        "user_id" => $user_id,
                        "jogo_id" => $jogo_id,
                        "criterio_id" => $criterio_id,
                        "nota" => $nota,
                    ],
                    ["%d", "%d", "%d", "%f"]
                );
                if ($result === false) {
                    wp_send_json_error(["message" => "Erro ao salvar no banco de dados."]);
                } else {
                    wp_send_json_success(["message" => "Avaliação salva com sucesso."]);
                }
            }

?>