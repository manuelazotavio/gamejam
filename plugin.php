<?php

/**
 * Plugin Name: Avaliador de Jogos IFSP
 * Description: Sistema de avaliação de jogos para a GameJam - IFSP 2025.
 * Version:     3.2
 * Author:      Manuela Otavio da Silva, 2 semestre de ADS
 */


function validaCPF($cpf)
{
    $cpf = preg_replace("/[^0-9]/is", "", $cpf);

    if (strlen($cpf) != 11) {
        return false;
    }

    if (preg_match('/(\d)\1{10}/', $cpf)) {
        return false;
    }

    for ($t = 9; $t < 11; $t++) {
        for ($d = 0, $c = 0; $c < $t; $c++) {
            $d += $cpf[$c] * ($t + 1 - $c);
        }
        $d = ((10 * $d) % 11) % 10;
        if ($cpf[$c] != $d) {
            return false;
        }
    }
    return true;
}


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

function ig_calcular_resultados()
{
    global $wpdb;
    $jogos = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}ig_jogos");
    $criterios = $wpdb->get_results(
        "SELECT * FROM {$wpdb->prefix}ig_criterios ORDER BY id ASC",
        OBJECT_K
    );

    if (empty($jogos) || empty($criterios)) {
        return [];
    }

    $results = [];
    foreach ($jogos as $jogo) {
        $total_score = 0;
        $criteria_scores = [];

        $count_ifsp = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->prefix}ig_avaliacoes_ifsp WHERE jogo_id = %d",
                $jogo->id
            )
        );
        $count_visitantes = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT cpf) FROM {$wpdb->prefix}ig_avaliacoes_visitantes WHERE jogo_id = %d",
                $jogo->id
            )
        );

        foreach ($criterios as $criterio) {
            $ifsp_scores = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT nota FROM {$wpdb->prefix}ig_avaliacoes_ifsp WHERE jogo_id = %d AND criterio_id = %d",
                    $jogo->id,
                    $criterio->id
                )
            );
            $visitor_avg_score = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT AVG(nota) FROM {$wpdb->prefix}ig_avaliacoes_visitantes WHERE jogo_id = %d AND criterio_id = %d",
                    $jogo->id,
                    $criterio->id
                )
            );

            $all_evaluations = $ifsp_scores;
            if ($visitor_avg_score !== null) {
                $all_evaluations[] = floatval($visitor_avg_score);
            }

            $final_avg_note = !empty($all_evaluations)
                ? array_sum($all_evaluations) / count($all_evaluations)
                : 0;

            $criterion_points = ($final_avg_note / 5) * $criterio->peso;

            $criteria_scores[$criterio->id] = $criterion_points;
            $total_score += $criterion_points;
        }

        $results[] = [
            "jogo_id" => $jogo->id,
            "jogo_name" => $jogo->nome,
            "total_score" => $total_score,
            "criteria_scores" => $criteria_scores,
            "count_ifsp" => $count_ifsp,
            "count_visitantes" => $count_visitantes,
        ];
    }

    usort($results, function ($a, $b) use ($criterios) {
        if ($a["total_score"] != $b["total_score"]) {
            return $a["total_score"] < $b["total_score"] ? 1 : -1;
        }
        $tie_break_criteria_ids = array_keys($criterios);
        foreach ($tie_break_criteria_ids as $crit_id) {
            $score_a = $a["criteria_scores"][$crit_id] ?? 0;
            $score_b = $b["criteria_scores"][$crit_id] ?? 0;
            if ($score_a != $score_b) {
                return $score_a < $score_b ? 1 : -1;
            }
        }
        return 0;
    });

    return $results;
}

add_action("admin_menu", "ig_admin_menu");

function ig_admin_menu()
{
    add_menu_page(
        "Avaliador de Jogos",
        "Avaliador Jogos",
        "gerenciar_mostra",
        "ig_dashboard",
        "ig_dashboard_page",
        "dashicons-games",
        20
    );
}
function ig_dashboard_page()
{

    if (isset($_POST['ig_save_status_nonce']) && wp_verify_nonce($_POST['ig_save_status_nonce'], 'ig_save_status_action')) {

        if (current_user_can('gerenciar_mostra')) {
            $novo_status = sanitize_text_field($_POST['ig_votacao_status']);
            if (in_array($novo_status, ['aberta', 'encerrada'])) {
                update_option('ig_votacao_status', $novo_status);
                echo '<div class="notice notice-success is-dismissible"><p>Status da votação atualizado com sucesso!</p></div>';
            }
        }
    }

    $status_atual = get_option('ig_votacao_status', 'aberta');

?> <div class="wrap">
        <h1>Painel do Avaliador de Jogos</h1>

        <div style="border:1px solid #ccc; padding: 10px 20px; margin-top: 20px; background-color: #fff;">
            <h2>Controle da Votação Pública</h2>
            <p>Use esta opção para encerrar a votação dos visitantes (via CPF) e exibir os resultados na página pública.</p>
            <form method="post">
                <?php wp_nonce_field('ig_save_status_action', 'ig_save_status_nonce'); ?>
                <label for="ig_votacao_status"><strong>Status da Votação:</strong></label>
                <select name="ig_votacao_status" id="ig_votacao_status">
                    <option value="aberta" <?php selected($status_atual, 'aberta'); ?>>Aberta</option>
                    <option value="encerrada" <?php selected($status_atual, 'encerrada'); ?>>Encerrada</option>
                </select>
                <button type="submit" class="button button-primary">Salvar Status</button>
            </form>
            <p><strong>Status Atual:</strong>
                <span style="font-weight: bold; color: <?php echo $status_atual === 'aberta' ? 'green' : 'red'; ?>;">
                    <?php echo $status_atual === 'aberta' ? 'VOTAÇÃO ABERTA' : 'VOTAÇÃO ENCERRADA'; ?>
                </span>
            </p>
        </div>

        <div style="margin-top: 20px;">
            <h2>Shortcodes</h2>
            <p>Use os shortcodes abaixo nas suas páginas para configurar o sistema:</p>
            <ul>
                <li><code>[ig_manage_jogos]</code> - Para criar uma página de gerenciamento de jogos.</li>
                <li><code>[ig_manage_criterios]</code> - Para criar uma página de gerenciamento de critérios.</li>
                <li><code>[ig_resultados]</code> - Para exibir a tabela de resultados (apenas para administradores).</li>
                <li><code>[ig_resultados_publicos]</code> - Para exibir o pódio dos vencedores (só aparece se a votação estiver encerrada).</li>
                <li><code>[ig_avaliacao_publica]</code> - Para a página de avaliação dos visitantes (via CPF).</li>
                <li><code>[ig_avaliacao_ifsp]</code> - Para a página de avaliação dos representantes do IFSP (requer login).</li>
            </ul>
        </div>
    </div> <?php
        }
        function ig_get_management_styles()
        {
            return "<style>.ig-manage-container{font-family:sans-serif;max-width:700px;margin:auto}.ig-form-section,.ig-list-section{border:1px solid #ccc;padding:20px;border-radius:8px;margin-bottom:20px}.ig-list{list-style-type:none;padding:0}.ig-list li{display:flex;align-items:center;justify-content:space-between;padding:10px;border-bottom:1px solid #eee}.ig-list .item-name{font-weight:bold}.ig-edit-form{display:none;margin-top:10px}.ig-notice{padding:10px 15px;border-radius:5px;margin-bottom:15px}.ig-notice-success{background-color:#d4edda;color:#155724}.ig-notice-error{background-color:#f8d7da;color:#721c24}</style>";
        }


        function ig_get_management_script()
        {
            return "<script>document.addEventListener('DOMContentLoaded',function(){document.querySelectorAll('.edit-btn').forEach(t=>{t.addEventListener('click',e=>{const t=e.target.getAttribute('data-id'),o=e.target.getAttribute('data-type');document.getElementById(o+'-details-'+t).style.display='none',document.getElementById('edit-form-'+o+'-'+t).style.display='block'})}),document.querySelectorAll('.cancel-edit-btn').forEach(t=>{t.addEventListener('click',e=>{const t=e.target.getAttribute('data-id'),o=e.target.getAttribute('data-type');document.getElementById(o+'-details-'+t).style.display='block',document.getElementById('edit-form-'+o+'-'+t).style.display='none'})})});</script>";
        }


        add_shortcode("ig_manage_jogos", "ig_manage_jogos_shortcode");
        function ig_manage_jogos_shortcode()
        {
            if (!current_user_can("gerenciar_mostra")) {
                return "Acesso negado.";
            }
            global $wpdb;
            $output = "";
            if (
                isset($_POST["ig_manage_nonce"]) &&
                wp_verify_nonce($_POST["ig_manage_nonce"], "ig_manage_action")
            ) {
                $action = sanitize_text_field($_POST["action"]);
                if ($action === "add_jogo") {
                    $nome = sanitize_text_field($_POST["nome"]);
                    if (!empty($nome)) {
                        $wpdb->insert(
                            "{$wpdb->prefix}ig_jogos",
                            ["nome" => $nome],
                            ["%s"]
                        );
                        $output .=
                            '<div class="ig-notice ig-notice-success">Jogo cadastrado!</div>';
                    }
                } elseif ($action === "update_jogo") {
                    $jogo_id = intval($_POST["jogo_id"]);
                    $nome = sanitize_text_field($_POST["nome"]);
                    if (!empty($nome) && $jogo_id > 0) {
                        $wpdb->update(
                            "{$wpdb->prefix}ig_jogos",
                            ["nome" => $nome],
                            ["id" => $jogo_id]
                        );
                        $output .=
                            '<div class="ig-notice ig-notice-success">Jogo atualizado!</div>';
                    }
                } elseif ($action === "delete_jogo") {
                    $jogo_id = intval($_POST["jogo_id"]);
                    if ($jogo_id > 0) {
                        $wpdb->delete("{$wpdb->prefix}ig_jogos", ["id" => $jogo_id]);
                        $wpdb->delete("{$wpdb->prefix}ig_avaliacoes_ifsp", [
                            "jogo_id" => $jogo_id,
                        ]);
                        $wpdb->delete("{$wpdb->prefix}ig_avaliacoes_visitantes", [
                            "jogo_id" => $jogo_id,
                        ]);
                        $output .=
                            '<div class="ig-notice ig-notice-success">Jogo excluído!</div>';
                    }
                }
            }
            $jogos = $wpdb->get_results(
                "SELECT id, nome FROM {$wpdb->prefix}ig_jogos ORDER BY nome"
            );
            ob_start();
            echo ig_get_management_styles();
            ?> <div class="ig-manage-container"> <?php echo $output; ?> <div class="ig-form-section">
            <h3>Adicionar Novo Jogo</h3>
            <form method="post"> <input type="hidden" name="action" value="add_jogo"> <?php wp_nonce_field(
                                                                                            "ig_manage_action",
                                                                                            "ig_manage_nonce"
                                                                                        ); ?> <p>

                    <label for="ig_nome_jogo">Nome da Equipe</label>

                    <br>

                    <input name="nome-eq" id="ig_nome_equipe" type="text" required style="width: 100%;">
                </p>

                <p>

                    <label for="ig_nome_jogo">Nome do Jogo</label>

                    <br>

                    <input name="nome" id="ig_nome_jogo" type="text" required style="width: 100%;">
                </p>
                <p>

                    <button type="submit">Cadastrar Jogo</button>
                </p>
            </form>
        </div>



        <div class="ig-list-section">



            <h3>Jogos Cadastrados</h3> <?php if (
                                            $jogos
                                        ): ?> <ul class="ig-list"> <?php foreach (
                                                $jogos
                                                as $j
                                            ): ?> <li>
                            <div id="jogo-details-<?php echo $j->id; ?>"><span class="item-name"><?php echo esc_html(
                                                                                                        $j->nome
                                                                                                    ); ?></span></div>
                            <div class="ig-edit-form" id="edit-form-jogo-<?php echo $j->id; ?>">
                                <form method="post" style="display:inline-flex; gap: 5px;"> <input type="hidden" name="action" value="update_jogo"><input type="hidden" name="jogo_id" value="<?php echo $j->id; ?>"> <?php wp_nonce_field(
                                                                                                                                                                                                                            "ig_manage_action",
                                                                                                                                                                                                                            "ig_manage_nonce"
                                                                                                                                                                                                                        ); ?> <input type="text" name="nome" value="<?php echo esc_attr(
                                                    $j->nome
                                                ); ?>" required> <button type="submit">Salvar</button><button type="button" class="cancel-edit-btn" data-id="<?php echo $j->id; ?>" data-type="jogo">Cancelar</button> </form>
                            </div>
                            <div> <button type="button" class="edit-btn" data-id="<?php echo $j->id; ?>" data-type="jogo">Editar</button>
                                <form method="post" style="display: inline;" onsubmit="return confirm('Tem certeza?');"> <input type="hidden" name="action" value="delete_jogo"><input type="hidden" name="jogo_id" value="<?php echo $j->id; ?>"> <?php wp_nonce_field(
                                                                                                                                                                                                                                                        "ig_manage_action",
                                                                                                                                                                                                                                                        "ig_manage_nonce"
                                                                                                                                                                                                                                                    ); ?> <button type="submit">Excluir</button> </form>
                            </div>
                        </li> <?php endforeach; ?> </ul> <?php else: echo "<p>Nenhum jogo cadastrado.</p>";
                                                        endif; ?>
        </div>
    </div> <?php
            echo ig_get_management_script();
            return ob_get_clean();
        }


        add_shortcode("ig_manage_criterios", "ig_manage_criterios_shortcode");
        function ig_manage_criterios_shortcode()
        {
            if (!current_user_can("gerenciar_mostra")) {
                return "Acesso negado.";
            }
            global $wpdb;
            $output = "";
            if (
                isset($_POST["ig_manage_nonce"]) &&
                wp_verify_nonce($_POST["ig_manage_nonce"], "ig_manage_action")
            ) {
                $action = sanitize_text_field($_POST["action"]);
                if ($action === "add_criterio") {
                    $nome = sanitize_text_field($_POST["nome"]);
                    $peso = floatval($_POST["peso"]);
                    if (!empty($nome)) {
                        $wpdb->insert(
                            "{$wpdb->prefix}ig_criterios",
                            ["nome" => $nome, "peso" => $peso],
                            ["%s", "%f"]
                        );
                        $output .=
                            '<div class="ig-notice ig-notice-success">Critério cadastrado!</div>';
                    }
                } elseif ($action === "delete_criterio") {
                    $criterio_id = intval($_POST["criterio_id"]);
                    if ($criterio_id > 0) {
                        $wpdb->delete("{$wpdb->prefix}ig_criterios", [
                            "id" => $criterio_id,
                        ]);
                        $wpdb->delete("{$wpdb->prefix}ig_avaliacoes_ifsp", [
                            "criterio_id" => $criterio_id,
                        ]);
                        $wpdb->delete("{$wpdb->prefix}ig_avaliacoes_visitantes", [
                            "criterio_id" => $criterio_id,
                        ]);
                        $output .=
                            '<div class="ig-notice ig-notice-success">Critério excluído!</div>';
                    }
                }
            }
            $criterios = $wpdb->get_results(
                "SELECT id, nome, peso FROM {$wpdb->prefix}ig_criterios ORDER BY id"
            );
            ob_start();
            echo ig_get_management_styles();
            ?> <div class="ig-manage-container"> <?php echo $output; ?> <div class="ig-form-section">
            <h3>Adicionar Novo Critério</h3>
            <p><strong>Importante:</strong> Cadastre os critérios na ordem exata que será usada para o desempate.</p>
            <form method="post"> <input type="hidden" name="action" value="add_criterio"> <?php wp_nonce_field(
                                                                                                "ig_manage_action",
                                                                                                "ig_manage_nonce"
                                                                                            ); ?> <p><label>Nome do Critério</label><br><input name="nome" type="text" required style="width: 100%;"></p>
                <p><label>Pontuação Máxima (Peso)</label><br><input name="peso" type="number" step="1" value="20" required style="width: 100%;"></p>
                <p><button type="submit">Cadastrar Critério</button></p>
            </form>
        </div>
        <div class="ig-list-section">
            <h3>Critérios Cadastrados (Ordem de Desempate)</h3> <?php if (
                                                                    $criterios
                                                                ): ?> <ol class="ig-list" style="list-style-type: decimal; padding-left: 20px;"> <?php foreach (
                                                                                        $criterios
                                                                                        as $c
                                                                                    ): ?> <li> <span class="item-name"><?php echo esc_html(
                                                                                            $c->nome
                                                                                        ); ?></span> (Max: <?php echo esc_html(
                                                                                            $c->peso
                                                                                        ); ?> pts) <form method="post" style="display: inline;" onsubmit="return confirm('Tem certeza?');"> <input type="hidden" name="action" value="delete_criterio"><input type="hidden" name="criterio_id" value="<?php echo $c->id; ?>"> <?php wp_nonce_field(
                                                                                                                                                                                                                                            "ig_manage_action",
                                                                                                                                                                                                                                            "ig_manage_nonce"
                                                                                                                                                                                                                                        ); ?> <button type="submit">Excluir</button> </form>
                        </li> <?php endforeach; ?> </ol> <?php else: echo "<p>Nenhum critério cadastrado.</p>";
                                                                endif; ?>
        </div>
    </div> <?php return ob_get_clean();
        }



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





            add_shortcode("ig_resultados_publicos", "ig_resultados_publicos_shortcode");
            function ig_resultados_publicos_shortcode()
            {

                $status_votacao = get_option('ig_votacao_status', 'aberta');
                if ($status_votacao !== 'encerrada') {
                    return '<div class="ig-public-results-wrapper"><h2 style="text-align:center;">A votação ainda está aberta. Os resultados serão divulgados em breve!</h2></div>';
                }
                $results = ig_calcular_resultados();

                if (empty($results)) {
                    return '<div class="ig-public-results-wrapper"><h2 style="text-align:center;">Os resultados da GameJam serão divulgados em breve!</h2></div>';
                }


                ob_start();
                ?>
    <style>
        .ig-public-results-wrapper {
            font-family: sans-serif;
            max-width: 900px;
            margin: 40px auto;
            text-align: center;
        }

        .ig-podium {
            display: flex;
            align-items: flex-end;
            justify-content: center;
            gap: 15px;
            margin-top: 50px;
            padding: 20px 0;
            border-bottom: 2px solid #eee;
        }

        .podium-place {
            width: 30%;
            max-width: 250px;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            background: #f9f9f9;
        }

        .podium-place h3 {
            margin: 0 0 10px;
            font-size: 1.8em;
            color: #333;
        }

        .podium-place .game-name {
            font-size: 1.4em;
            font-weight: bold;
            margin-bottom: 10px;
            min-height: 60px;
        }

        .podium-place .score {
            font-size: 2.2em;
            font-weight: bold;
            color: #111;
        }

        .podium-place.first {
            order: 2;
            height: 280px;
            background: linear-gradient(145deg, #ffd700, #f0c400);
            border: 3px solid #ffc107;
        }

        .podium-place.second {
            order: 1;
            height: 240px;
            background: linear-gradient(145deg, #e0e0e0, #cccccc);
            border: 3px solid #c0c0c0;
        }

        .podium-place.third {
            order: 3;
            height: 200px;
            background: linear-gradient(145deg, #d2a679, #c4915c);
            border: 3px solid #cd7f32;
        }

        .ig-other-ranks {
            margin-top: 40px;
            text-align: left;
        }

        .ig-other-ranks h2 {
            text-align: center;
            color: #444;
        }

        .ig-other-ranks ol {
            list-style-type: none;
            counter-reset: rank-counter <?php echo count(
                                            $results
                                        ) > 3
                                            ? 3
                                            : 0; ?>;
            padding: 0;
        }

        .ig-other-ranks li {
            background: #fff;
            border: 1px solid #ddd;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 5px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 1.1em;
        }

        .ig-other-ranks li::before {
            content: counter(rank-counter) "º";
            counter-increment: rank-counter;
            font-weight: bold;
            color: #4f1299;
            margin-right: 15px;
        }

        .ig-other-ranks .game-name {
            font-weight: bold;
        }

        .ig-other-ranks .score {
            font-weight: bold;
            color: #333;
        }

        @media (max-width: 768px) {
            .ig-podium {
                flex-direction: column;
                align-items: center;
            }

            .podium-place {
                width: 80%;
                order: 0 !important;
                height: auto !important;
                margin-bottom: 15px;
            }

            .podium-place.first {
                order: -2 !important;
            }

            .podium-place.second {
                order: -1 !important;
            }
        }
    </style>

    <div class="ig-public-results-wrapper">

        <?php if (!empty($results)): ?>
            <div class="ig-podium">
                <?php if (isset($results[1])): ?>
                    <div class="podium-place second">
                        <h3>2º Lugar</h3>
                        <div class="game-name"><?php echo esc_html(
                                                    $results[1]["jogo_name"]
                                                ); ?></div>
                        <div class="score"><?php echo number_format(
                                                $results[1]["total_score"],
                                                2
                                            ); ?></div>

                    </div>
                <?php endif; ?>

                <?php if (isset($results[0])): ?>
                    <div class="podium-place first">
                        <h3>1º Lugar</h3>
                        <div class="game-name"><?php echo esc_html(
                                                    $results[0]["jogo_name"]
                                                ); ?></div>
                        <div class="score"><?php echo number_format(
                                                $results[0]["total_score"],
                                                2
                                            ); ?></div>

                    </div>
                <?php endif; ?>

                <?php if (isset($results[2])): ?>
                    <div class="podium-place third">
                        <h3>3º Lugar</h3>
                        <div class="game-name"><?php echo esc_html(
                                                    $results[2]["jogo_name"]
                                                ); ?></div>
                        <div class="score"><?php echo number_format(
                                                $results[2]["total_score"],
                                                2
                                            ); ?></div>

                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php
                $other_ranks = array_slice($results, 3);
                if (!empty($other_ranks)): ?>
            <div class="ig-other-ranks">
                <h2>Demais Colocados</h2>
                <ol>
                    <?php foreach ($other_ranks as $res): ?>
                        <li>
                            <span class="game-name"><?php echo esc_html(
                                                        $res["jogo_name"]
                                                    ); ?></span>
                            <span class="score"><?php echo number_format(
                                                    $res["total_score"],
                                                    2
                                                ); ?> pts</span>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </div>
        <?php endif;
        ?>
    </div>
<?php return ob_get_clean();
            }



            add_shortcode("ig_avaliacao_publica", "ig_render_public_evaluation_shortcode");
            function ig_render_public_evaluation_shortcode()
            {

                $status_votacao = get_option('ig_votacao_status', 'aberta');
                if ($status_votacao === 'encerrada') {
                    return '<div id="ig-public-eval-wrapper" style="text-align:center;"><h2>Votação Encerrada</h2><p>Agradecemos a sua participação! Os resultados serão divulgados em breve.</p></div>';
                }

                ob_start(); ?>
    <div id="ig-public-eval-wrapper">
                <div id="ig-location-check-container">
                        <h2>Verificação de Proximidade</h2>
                        <p>Para participar da votação, precisamos confirmar que você está no local do evento. Por favor, permita o acesso à sua localização quando o navegador solicitar.</p>
                        <p id="location-status" style="font-weight: bold; font-size: 1.1em;">Aguardando permissão...</p>
                    </div>
                <div id="ig-cpf-form-container" style="display: none;">
                        <p id="location-success-message" style="color: green; font-weight: bold;"></p>
                        <p>Por favor, informe seu CPF para iniciar a avaliação.</p>
                        <form id="ig-cpf-form"><label for="ig_cpf">CPF:</label><input type="text" id="ig_cpf" name="ig_cpf" required placeholder="000.000.000-00" maxlength="14"><button type="submit" class="init-button">Votar</button>
                <div id="ig-message-area" style="margin-top: 10px; font-weight: bold;"></div>
            </form>
                    </div>
                <div id="ig-evaluation-content" style="display: none;"></div>
            </div>
    <style>
        #ig-cpf-form-container,
        #ig-location-check-container {
            text-align: center;
            max-width: 400px;
            margin: 0 auto;
            padding: 20px;
        }

        #ig-cpf-form {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 15px;
        }

        #ig_cpf {
            width: 100%;
            padding: 15px;
            font-size: 1.2em;
            box-sizing: border-box;
            text-align: center;
        }

        .init-button {
            width: 40%;
            padding: 15px;
            font-size: 1em;
            font-weight: bold;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            background-color: #4f1299;
            color: white;
        }

        .lista-jogos-publica {
            list-style: none;
            padding: 0;
        }

        .lista-jogos-publica li {
            padding: 10px;
            border: 1px solid #ccc;
            margin-bottom: 5px;
            border-radius: 4px;
        }

        .lista-jogos-publica li.ja-avaliado {
            background-color: #f0f0f0;
            color: #888;
        }

        .lista-jogos-publica li.ja-avaliado a {
            text-decoration: none;
            pointer-events: none;
        }

        .lista-jogos-publica li a {
            text-decoration: none;
            color: #000;
            font-weight: bold;
            cursor: pointer;
        }

        .ig-crit {
            border: 1px solid #ccc;
            padding: 10px;
            margin-bottom: 10px;
            border-radius: 5px;
        }

        .ig-stars {
            display: flex;
            cursor: pointer;
        }

        .ig-stars svg {
            width: 30px;
            height: 30px;
        }

        .star-path-full {
            fill: #ffd700;
        }

        .star-path-empty {
            fill: #ccc;
        }

        #finalize-evaluation-btn {
            background: linear-gradient(90deg, #9c27be 0%, #ff6cc7 100%);
            color: white;
            border: none;
            font-size: 16px;
            cursor: pointer;
            border-radius: 5px;
        }

        #finalize-evaluation-btn:hover {
            background-color: #b71c1c;
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function() {

            const TARGET_LAT = -23.6368;
            const TARGET_LON = -45.4259;
            const MAX_DISTANCE_KM = 0.2;


            const locationContainer = document.getElementById('ig-location-check-container');
            const locationStatus = document.getElementById('location-status');
            const locationSuccessMessage = document.getElementById('location-success-message');
            const cpfContainer = document.getElementById('ig-cpf-form-container');
            const contentContainer = document.getElementById('ig-evaluation-content');
            const messageArea = document.getElementById('ig-message-area');
            const cpfInput = document.getElementById('ig_cpf');
            const cpfForm = document.getElementById('ig-cpf-form');

            let avaliadorCPF;

            function calculateDistance(lat1, lon1, lat2, lon2) {
                const R = 6371;
                const dLat = (lat2 - lat1) * Math.PI / 180;
                const dLon = (lon2 - lon1) * Math.PI / 180;
                const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) + Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * Math.sin(dLon / 2) * Math.sin(dLon / 2);
                const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
                return R * c;
            }

            function handleLocationSuccess(position) {
                const userLat = position.coords.latitude;
                const userLon = position.coords.longitude;
                const distance = calculateDistance(TARGET_LAT, TARGET_LON, userLat, userLon);

                if (distance <= MAX_DISTANCE_KM) {
                    locationStatus.textContent = "Localização confirmada!";
                    locationStatus.style.color = 'green';
                    setTimeout(() => {
                        locationContainer.style.display = 'none';
                        cpfContainer.style.display = 'block';
                    }, 1000);
                } else {
                    locationStatus.innerHTML = `<br>A votação é permitida apenas no local do evento. Ainda dá tempo, corre pro IFSP!!!`;
                    locationStatus.style.color = 'red';
                }
            }

            function handleLocationError(error) {
                let message = '';
                switch (error.code) {
                    case error.PERMISSION_DENIED:
                        message = "Você precisa permitir o acesso à localização para poder votar.";
                        break;
                    case error.POSITION_UNAVAILABLE:
                        message = "Não foi possível obter sua localização atual.";
                        break;
                    case error.TIMEOUT:
                        message = "A verificação de localização demorou muito. Tente novamente.";
                        break;
                    default:
                        message = "Ocorreu um erro desconhecido ao verificar a localização.";
                        break;
                }
                locationStatus.textContent = message;
                locationStatus.style.color = 'red';
            }


            if (navigator.geolocation) {
                locationStatus.textContent = "Verificando localização...";
                navigator.geolocation.getCurrentPosition(handleLocationSuccess, handleLocationError, {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 0
                });
            } else {
                locationStatus.textContent = "Geolocalização não é suportada neste navegador.";
                locationStatus.style.color = 'red';
            }


            function mascaraCPF(event) {
                let value = event.target.value.replace(/\D/g, "");
                value = value.replace(/(\d{3})(\d)/, "$1.$2");
                value = value.replace(/(\d{3})(\d)/, "$1.$2");
                value = value.replace(/(\d{3})(\d{1,2})$/, "$1-$2");
                event.target.value = value;
            }

            function validaCPF(cpf) {
                cpf = cpf.replace(/[^\d]+/g, '');
                if (cpf.length !== 11 || /^(\d)\1+$/.test(cpf)) return false;
                let soma = 0,
                    resto;
                for (let i = 1; i <= 9; i++) soma += parseInt(cpf.substring(i - 1, i)) * (11 - i);
                resto = (soma * 10) % 11;
                if (resto === 10 || resto === 11) resto = 0;
                if (resto !== parseInt(cpf.substring(9, 10))) return false;
                soma = 0;
                for (let i = 1; i <= 10; i++) soma += parseInt(cpf.substring(i - 1, i)) * (12 - i);
                resto = (soma * 10) % 11;
                if (resto === 10 || resto === 11) resto = 0;
                if (resto !== parseInt(cpf.substring(10, 11))) return false;
                return true;
            }

            function loadGameList() {
                messageArea.textContent = "Carregando lista de jogos...";
                messageArea.style.color = "black";
                contentContainer.style.display = "none";

                const body = new URLSearchParams({
                    action: 'ig_ajax_public_handler',
                    step: 'get_games',
                    cpf: avaliadorCPF
                });

                fetch("<?php echo admin_url("admin-ajax.php"); ?>", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/x-www-form-urlencoded"
                    },
                    body: body.toString()
                }).then(res => res.json()).then(response => {
                    messageArea.textContent = "";
                    if (response.success) {
                        contentContainer.innerHTML = response.data.html;
                        contentContainer.style.display = "block";
                    } else {
                        cpfContainer.style.display = "block";
                        messageArea.style.color = "red";
                        messageArea.textContent = response.data;
                        sessionStorage.removeItem("avaliador_cpf");
                    }
                });
            }

            cpfInput.addEventListener("input", mascaraCPF);

            const storedCPF = sessionStorage.getItem("avaliador_cpf");
            if (storedCPF) {
                avaliadorCPF = storedCPF;
                cpfContainer.style.display = "none";
                locationContainer.style.display = 'none';
                loadGameList();
            }

            cpfForm.addEventListener("submit", function(e) {
                e.preventDefault();
                const cpfValue = cpfInput.value;
                messageArea.style.color = "red";
                if (!validaCPF(cpfValue)) {
                    messageArea.textContent = "CPF inválido. Por favor, verifique.";
                    return;
                }
                avaliadorCPF = cpfValue;
                sessionStorage.setItem("avaliador_cpf", avaliadorCPF);
                cpfContainer.style.display = "none";
                loadGameList();
            });

            contentContainer.addEventListener("click", function(e) {
                const jogoLink = e.target.closest(".jogo-link-public");
                const star = e.target.closest(".star");
                const submitBtn = e.target.closest("#submit-public-evaluation");
                const finalizeBtn = e.target.closest("#finalize-evaluation-btn");

                if (jogoLink) {
                    e.preventDefault();
                    const jogoId = jogoLink.getAttribute("data-jogo-id");
                    contentContainer.innerHTML = "Carregando avaliação...";
                    const body = new URLSearchParams({
                        action: 'ig_ajax_public_handler',
                        step: 'get_rating_form',
                        cpf: avaliadorCPF,
                        jogo_id: jogoId
                    });
                    fetch("<?php echo admin_url(
                                "admin-ajax.php"
                            ); ?>", {
                            method: "POST",
                            headers: {
                                "Content-Type": "application/x-www-form-urlencoded"
                            },
                            body: body.toString()
                        })
                        .then(res => res.json())
                        .then(response => {
                            contentContainer.innerHTML = response.success ? response.data.html : `<p style="color:red;">${response.data}</p>`;
                        });

                } else if (star) {
                    const container = star.closest(".ig-stars");
                    const value = parseInt(star.dataset.value, 10);
                    container.dataset.rating = value;
                    container.querySelectorAll("svg path").forEach((p, i) => {
                        p.className.baseVal = i < value ? "star-path-full" : "star-path-empty";
                    });

                } else if (submitBtn) {
                    e.preventDefault();
                    const submitMessage = document.getElementById("ig-submit-message");
                    const notes = {};
                    let allRated = true;

                    document.querySelectorAll(".ig-stars").forEach(container => {
                        const critId = container.dataset.critId;
                        const rating = parseFloat(container.dataset.rating) || 0;
                        if (rating === 0) allRated = false;
                        notes[critId] = rating;
                    });

                    if (!allRated) {
                        submitMessage.textContent = "Por favor, avalie todos os critérios.";
                        submitMessage.style.color = "red";
                        return;
                    }

                    submitBtn.disabled = true;
                    submitMessage.textContent = "Salvando...";
                    submitMessage.style.color = "black";

                    const jogoId = submitBtn.dataset.jogoId;
                    const body = new URLSearchParams({
                        action: 'ig_save_public_eval',
                        cpf: avaliadorCPF,
                        jogo_id: jogoId,
                        notes: JSON.stringify(notes)
                    });

                    fetch("<?php echo admin_url(
                                "admin-ajax.php"
                            ); ?>", {
                            method: "POST",
                            headers: {
                                "Content-Type": "application/x-www-form-urlencoded"
                            },
                            body: body.toString()
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                contentContainer.innerHTML = '<p style="color:green; text-align:center;">Voto computado com sucesso!</p>';
                                setTimeout(loadGameList, 1500);
                            } else {
                                submitMessage.textContent = "Erro: " + data.data;
                                submitMessage.style.color = "red";
                                submitBtn.disabled = false;
                            }
                        });

                } else if (finalizeBtn) {
                    e.preventDefault();
                    if (confirm("Deseja encerrar a sua avaliação? Após confirmar, você não poderá mais usar este CPF para avaliar ou alterar seus votos.")) {
                        finalizeBtn.disabled = true;
                        finalizeBtn.textContent = "Finalizando...";
                        const body = new URLSearchParams({
                            action: 'ig_finalize_cpf',
                            cpf: avaliadorCPF
                        });
                        fetch("<?php echo admin_url(
                                    "admin-ajax.php"
                                ); ?>", {
                                method: "POST",
                                headers: {
                                    "Content-Type": "application/x-www-form-urlencoded"
                                },
                                body: body.toString()
                            })
                            .then(res => res.json())
                            .then(response => {
                                if (response.success) {
                                    contentContainer.innerHTML = '<p style="color:green; text-align:center; font-size: 1.2em;">Avaliação finalizada com sucesso. Obrigado por participar!</p>';
                                    sessionStorage.removeItem("avaliador_cpf");
                                    setTimeout(() => window.location.reload(), 2500);
                                } else {
                                    alert("Ocorreu um erro ao finalizar: " + response.data);
                                    finalizeBtn.disabled = false;
                                    finalizeBtn.textContent = "Finalizar votação";
                                }
                            });
                    }
                }
            });
        });
    </script>
<?php return ob_get_clean();
            }


            add_shortcode('ig_controle_votacao', 'ig_controle_votacao_shortcode');
            function ig_controle_votacao_shortcode()
            {
                if (!current_user_can('gerenciar_mostra')) {
                    return '<p>Você não tem permissão para acessar esta página. Por favor, faça login como administrador.</p>';
                }

                $message = '';

                if (isset($_GET['status_changed']) && $_GET['status_changed'] === '1') {
                    $message = '<div class="ig-notice ig-notice-success">Status da votação alterado com sucesso!</div>';
                }

                $status_atual = get_option('ig_votacao_status');

                ob_start();
?>
    <style>
        .ig-control-wrapper {
            max-width: 600px;
            margin: 40px auto;
            padding: 30px;
            border: 1px solid #ccc;
            border-radius: 8px;
            text-align: center;
            font-family: sans-serif;
        }

        .ig-control-wrapper h2 {
            margin-top: 0;
        }

        .ig-status {
            margin: 20px 0;
            font-size: 1.2em;
        }

        .ig-status-aberta {
            font-weight: bold;
            color: #28a745;
        }

        .ig-status-encerrada {
            font-weight: bold;
            color: #dc3545;
        }

        .ig-control-button {
            font-size: 1.3em;
            padding: 15px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            color: white;
        }

        .ig-button-encerrar {
            background-color: #dc3545;
        }

        .ig-button-reabrir {
            background-color: #28a745;
        }

        .ig-notice {
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 15px;
            text-align: left;
        }

        .ig-notice-success {
            background-color: #d4edda;
            color: #155724
        }
    </style>
    <div class="ig-control-wrapper">
        <?php echo $message;  ?>

        <h2>Controle da Votação Pública</h2>
        <div class="ig-status">
            Status Atual:
            <?php if ($status_atual === 'aberta'): ?>
                <span class="ig-status-aberta">VOTAÇÃO ABERTA</span>
            <?php else: ?>
                <span class="ig-status-encerrada">VOTAÇÃO ENCERRADA</span>
            <?php endif; ?>
        </div>

        <form method="post" action="">
            <input type="hidden" name="action" value="ig_change_status">
            <?php wp_nonce_field('ig_change_status_action', 'ig_change_status_nonce'); ?>

            <?php if ($status_atual === 'aberta'): ?>
                <p>Clique no botão abaixo para encerrar a votação para o público imediatamente.</p>
                <button type="submit" class="ig-control-button ig-button-encerrar">Encerrar Votação</button>
            <?php else: ?>
                <p>A votação está encerrada. Se precisar, clique abaixo para reabri-la.</p>
                <button type="submit" class="ig-control-button ig-button-reabrir">Reabrir Votação</button>
            <?php endif; ?>
        </form>
    </div>
    <?php
                return ob_get_clean();
            }


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