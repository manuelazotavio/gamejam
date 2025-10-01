<?php


       
            add_shortcode("ig_avaliacao_ifsp", "ig_render_ifsp_evaluation_shortcode");
            function ig_render_ifsp_evaluation_shortcode()
            {
                if (!is_user_logged_in() || !current_user_can("avaliar_jogos_ifsp")) {
                    return '<p>Você precisa estar logado como um avaliador do IFSP para acessar esta página. <a href="' .
                        wp_login_url(get_permalink()) .
                        '">Fazer login</a></p>';
                }
                global $wpdb;
                $current_user = wp_get_current_user();
                $jogos = $wpdb->get_results(
                    "SELECT id, nome FROM {$wpdb->prefix}ig_jogos ORDER BY nome ASC"
                );
                if (empty($jogos)) {
                    return "<p>Nenhum jogo cadastrado para avaliação.</p>";
                }
                if (isset($_GET["jogo_id"])) {

                    $jogo_id = intval($_GET["jogo_id"]);
                    $jogo = $wpdb->get_row(
                        $wpdb->prepare(
                            "SELECT * FROM {$wpdb->prefix}ig_jogos WHERE id = %d",
                            $jogo_id
                        )
                    );
                    if (!$jogo) {
                        return "<p>Jogo não encontrado.</p>";
                    }
                    $criterios = $wpdb->get_results(
                        "SELECT id, nome FROM {$wpdb->prefix}ig_criterios ORDER BY id ASC"
                    );
                    ob_start();
    ?> <div class="ig-ifsp-eval-container">
            <h3>Avaliando o jogo: <strong><?php echo esc_html(
                                                $jogo->nome
                                            ); ?></strong></h3>
            <p>Avaliador: <?php echo esc_html(
                                $current_user->display_name
                            ); ?></p>
            <p>Dê sua nota de 0 a 5 para cada critério abaixo:</p>
            <div id="ig-star-form-ifsp"> <?php foreach (
                                                $criterios
                                                as $c
                                            ):

                                                $prev_note = $wpdb->get_var(
                                                    $wpdb->prepare(
                                                        "SELECT nota FROM {$wpdb->prefix}ig_avaliacoes_ifsp WHERE user_id = %d AND jogo_id = %d AND criterio_id = %d",
                                                        $current_user->ID,
                                                        $jogo_id,
                                                        $c->id
                                                    )
                                                );
                                                $rating = $prev_note ? floatval($prev_note) : 0;
                                            ?> <div class="ig-crit"> <strong><?php echo esc_html(
                                                    $c->nome
                                                ); ?></strong>
                        <div class="ig-stars" data-crit-id="<?php echo esc_attr(
                                                                $c->id
                                                            ); ?>" data-rating="<?php echo $rating; ?>"> <?php for ($i = 1; $i <= 5; $i++):
                                                    $cls =
                                                        $rating >= $i
                                                        ? "star-path-full"
                                                        : "star-path-empty"; ?> <svg class="star" data-value="<?php echo $i; ?>" viewBox="0 0 24 24">
                                    <path class="<?php echo $cls; ?>" d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z" />
                                </svg> <?php
                                                endfor; ?> </div>
                    </div> <?php
                                            endforeach; ?> <a href="<?php echo esc_url(
                            get_permalink()
                        ); ?>">Voltar para a lista de jogos</a> </div>
        </div>
        <style>
            .ig-crit {
                border: 1px solid #ccc;
                padding: 10px;
                margin-bottom: 10px;
                border-radius: 5px
            }

            .ig-stars {
                display: flex;
                cursor: pointer
            }

            .ig-stars svg {
                width: 30px;
                height: 30px
            }

            .star-path-full {
                fill: #ffd700
            }

            .star-path-empty {
                fill: #ccc
            }
        </style>
        <script>
            document.querySelectorAll('#ig-star-form-ifsp .ig-stars').forEach(container => {
                container.addEventListener('click', e => {
                    const star = e.target.closest('svg');
                    if (!star) return;
                    const value = parseInt(star.dataset.value, 10);
                    container.dataset.rating = value;
                    container.querySelectorAll('svg path').forEach((p, i) => {
                        p.className.baseVal = (i < value) ? 'star-path-full' : 'star-path-empty';
                    });
                    const critId = container.dataset.critId;
                    fetch('<?php echo admin_url(
                                "admin-ajax.php"
                            ); ?>', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: `action=ig_save_ifsp_eval&_ajax_nonce=<?php echo wp_create_nonce(
                                                                        "ig_save_ifsp_eval_nonce"
                                                                    ); ?>&jogo_id=<?php echo $jogo_id; ?>&criterio_id=${critId}&nota=${value}`
                    });
                });
            });
        </script> <?php return ob_get_clean();
                } else {
                    ob_start(); ?> <div class="ig-ifsp-eval-container">
            <h2>Área do Avaliador</h2>
            <p>Olá, <strong><?php echo esc_html(
                                $current_user->display_name
                            ); ?></strong>. Por favor, escolha um jogo para avaliar:</p>
            <ul> <?php foreach (
                        $jogos
                        as $jogo
                    ): ?> <li><a href="<?php echo esc_url(
                            add_query_arg("jogo_id", $jogo->id)
                        ); ?>"><?php echo esc_html(
                            $jogo->nome
                        ); ?></a></li> <?php endforeach; ?> </ul>
        </div> <?php return ob_get_clean();
                }
            }

?>