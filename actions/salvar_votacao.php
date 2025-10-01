<?php


            add_action("wp_ajax_ig_save_public_eval", "ig_ajax_save_public_eval");
            add_action("wp_ajax_nopriv_ig_save_public_eval", "ig_ajax_save_public_eval");
            function ig_ajax_save_public_eval()
            {

                $status_votacao = get_option('ig_votacao_status', 'aberta');
                if ($status_votacao === 'encerrada') {
                    wp_send_json_error('A votação já foi encerrada.', 403);
                    return;
                }
                global $wpdb;
                if (!isset($_POST["cpf"], $_POST["jogo_id"], $_POST["notes"])) {
                    wp_send_json_error("Dados incompletos.");
                }

                $cpf = preg_replace("/[^0-9]/is", "", sanitize_text_field($_POST["cpf"]));

                $jogo_id = intval($_POST["jogo_id"]);
                $notes = json_decode(urldecode(stripslashes($_POST["notes"])), true);
                if (empty($cpf) || $jogo_id <= 0 || empty($notes) || !is_array($notes)) {
                    wp_send_json_error("Dados inválidos.");
                }
                $has_voted = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(*) FROM {$wpdb->prefix}ig_avaliacoes_visitantes WHERE cpf = %s AND jogo_id = %d",
                        $cpf,
                        $jogo_id
                    )
                );
                if ($has_voted > 0) {
                    wp_send_json_error("Este CPF já enviou uma avaliação para este jogo.");
                }
                foreach ($notes as $criterio_id => $nota) {
                    $wpdb->insert(
                        "{$wpdb->prefix}ig_avaliacoes_visitantes",
                        [
                            "cpf" => $cpf,
                            "jogo_id" => $jogo_id,
                            "criterio_id" => intval($criterio_id),
                            "nota" => floatval($nota),
                        ],
                        ["%s", "%d", "%d", "%f"]
                    );
                }
                wp_send_json_success();
            }

?>