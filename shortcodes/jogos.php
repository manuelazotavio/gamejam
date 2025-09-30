<?php

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

?>