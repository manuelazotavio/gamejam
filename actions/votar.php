<?php

  add_action("wp_ajax_ig_ajax_public_handler", "ig_ajax_public_handler_callback");
            add_action(
                "wp_ajax_nopriv_ig_ajax_public_handler",
                "ig_ajax_public_handler_callback"
            );
            function ig_ajax_public_handler_callback()
            {
                $status_votacao = get_option('ig_votacao_status', 'aberta');
                if ($status_votacao === 'encerrada') {
                    wp_send_json_error('A votação já foi encerrada.', 403);
                    return;
                }

                if (!isset($_POST["cpf"], $_POST["step"])) {
                    wp_send_json_error("Requisição inválida (parâmetros faltando).", 400);
                    return;
                }

                $cpf_com_mascara = sanitize_text_field($_POST["cpf"]);
                $step = sanitize_text_field($_POST["step"]);
                global $wpdb;

                $cpf = preg_replace("/[^0-9]/is", "", $cpf_com_mascara);

                if (!validaCPF($cpf)) {
                    wp_send_json_error("O CPF informado é inválido.");
                    return;
                }

                if ($step === "get_games") {
                    $is_finalized = $wpdb->get_var(
                        $wpdb->prepare(
                            "SELECT COUNT(*) FROM {$wpdb->prefix}ig_cpf_finalizados WHERE cpf = %s",
                            $cpf
                        )
                    );
                    if ($is_finalized > 0) {
                        wp_send_json_error(
                            "Este CPF já finalizou sua avaliação e não pode ser usado novamente."
                        );
                        return;
                    }

                    $sql = $wpdb->prepare(
                        "SELECT j.id, j.nome, CASE WHEN a.jogo_id IS NOT NULL THEN 1 ELSE 0 END AS avaliado
             FROM {$wpdb->prefix}ig_jogos j
             LEFT JOIN (SELECT DISTINCT jogo_id FROM {$wpdb->prefix}ig_avaliacoes_visitantes WHERE cpf = %s) a ON j.id = a.jogo_id
             ORDER BY j.nome ASC",
                        $cpf
                    );
                    $jogos = $wpdb->get_results($sql);

                    $html = "<h3>Olá! Escolha um jogo para avaliar:</h3>";
                    $html .=
                        "<p>CPF: <strong>" .
                        esc_html($cpf_com_mascara) .
                        "</strong>. (<a href='#' onclick='sessionStorage.removeItem(\"avaliador_cpf\"); window.location.reload(); return false;'>Trocar</a>)</p>";
                    $html .= '<ul class="lista-jogos-publica">';
                    foreach ($jogos as $jogo) {
                        if ($jogo->avaliado) {
                            $html .= sprintf(
                                '<li class="ja-avaliado"><a>%s <span>Avaliado!</span></a></li>',
                                esc_html($jogo->nome)
                            );
                        } else {
                            $html .= sprintf(
                                '<li style="cursor: pointer"><a class="jogo-link-public" data-jogo-id="%d">%s</a></li>',
                                $jogo->id,
                                esc_html($jogo->nome)
                            );
                        }
                    }
                    $html .= "</ul>";
                    $html .=
                        '<div style="margin-top: 25px; padding-top: 20px; border-top: 1px dashed #ccc; text-align: center;"><button class="init-button" id="finalize-evaluation-btn">Finalizar votação</button></div>';

                    wp_send_json_success(["html" => $html]);
                }

                if ($step === "get_rating_form") {

                    $jogo_id = isset($_POST["jogo_id"]) ? intval($_POST["jogo_id"]) : 0;
                    if ($jogo_id === 0) {
                        wp_send_json_error("ID do jogo não fornecido.", 400);
                    }

                    $jogo = $wpdb->get_row(
                        $wpdb->prepare(
                            "SELECT nome FROM {$wpdb->prefix}ig_jogos WHERE id = %d",
                            $jogo_id
                        )
                    );
                    $criterios = $wpdb->get_results(
                        "SELECT id, nome FROM {$wpdb->prefix}ig_criterios ORDER BY id ASC"
                    );

                    ob_start();
    ?>
        <h4>Avaliando o jogo: <strong><?php echo esc_html(
                                            $jogo->nome
                                        ); ?></strong></h4>
        <p>Dê sua nota de 0 a 5 para cada critério abaixo:</p>
        <div id="ig-star-form">
            <?php foreach ($criterios as $c): ?>
                <div class="ig-crit">
                    <strong><?php echo esc_html($c->nome); ?></strong>
                    <div class="ig-stars" data-crit-id="<?php echo esc_attr(
                                                            $c->id
                                                        ); ?>" data-rating="0">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <svg class="star" data-value="<?php echo $i; ?>" viewBox="0 0 24 24">
                                <path class="star-path-empty" d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z" />
                            </svg>
                        <?php endfor; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            <button id="submit-public-evaluation" class="init-button" data-jogo-id="<?php echo $jogo_id; ?>">Salvar avaliação</button>
            <div id="ig-submit-message" style="margin-top: 10px;"></div>
        </div>
    <?php
                    $html = ob_get_clean();
                    wp_send_json_success(["html" => $html]);
                }

                wp_send_json_error("Passo desconhecido.", 400);
            }

?>