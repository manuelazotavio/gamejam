<?php

        add_shortcode("ig_resultados", "ig_resultados_page");
        function ig_resultados_page()
        {
            if (!current_user_can("gerenciar_mostra")) {
                return "Você não tem permissão para acessar esta página.";
            }
            global $wpdb;
            $criterios = $wpdb->get_results(
                "SELECT * FROM {$wpdb->prefix}ig_criterios",
                OBJECT_K
            );
            $results = ig_calcular_resultados();
            if (empty($results)) {
                return '<div class="wrap"><h1>Resultados</h1><p>Não há dados suficientes para gerar os resultados.</p></div>';
            }
            ob_start();
            ?> <div class="wrap">
        <h1>Resultados da Mostra de Jogos</h1>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 50px;">Pos.</th>
                    <th>Jogo</th>
                    <th style="width: 150px;">Pontos (0-100)</th>
                    <th style="width: 100px;">Ações</th>
                </tr>
            </thead>
            <tbody> <?php foreach (
                        $results
                        as $index => $res
                    ): ?> <tr>
                        <td><?php echo $index + 1 . "º"; ?></td>
                        <td><?php echo esc_html(
                                $res["jogo_name"]
                            ); ?></td>
                        <td><?php echo number_format(
                                $res["total_score"],
                                2
                            ); ?></td>
                        <td><button class="button-link" onclick="toggleDetails(<?php echo $res["jogo_id"]; ?>)">Detalhes</button></td>
                    </tr>
                    <tr class="details-row" style="display:none;" id="details-<?php echo $res["jogo_id"]; ?>">
                        <td colspan="4" style="padding: 15px 30px; background-color: #f9f9f9;">
                            <p>
                                <strong>Votantes:</strong> <?php echo esc_html(
                                                                $res["count_ifsp"]
                                                            ); ?> avaliador(es) do IFSP e <?php echo esc_html(
                                                $res["count_visitantes"]
                                            ); ?> visitante(s).
                            </p>
                            <strong>Pontuação por Critério (de um máx. de <?php echo esc_html(
                                                                                $criterios[$crit_id]->peso ?? 20
                                                                            ); ?> pts):</strong>
                            <ul>
                                <?php foreach ($res["criteria_scores"] as $crit_id => $score): ?>
                                    <li><?php echo esc_html(
                                            $criterios[$crit_id]->nome
                                        ); ?>: <?php echo number_format($score, 2); ?> pontos</li>
                                <?php endforeach; ?>
                            </ul>
                        </td>
                    </tr> <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <script>
        function toggleDetails(jogoId) {
            const row = document.getElementById('details-' + jogoId);
            row.style.display = row.style.display === 'none' ? 'table-row' : 'none';
        }
    </script> <?php return ob_get_clean();
            }





?>