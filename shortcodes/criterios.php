<?php


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

?>