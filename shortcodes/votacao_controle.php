<?php

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


?>