<?php


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
?>