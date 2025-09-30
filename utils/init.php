<?php
if (!defined("ABSPATH")) {
    exit();
}

add_action("after_setup_theme", function () {
    if (is_user_logged_in() && current_user_can("avaliar_jogos_ifsp")) {
        show_admin_bar(false);
    }
});

register_activation_hook(__FILE__, "ig_activate");
register_deactivation_hook(__FILE__, "ig_deactivate");

function ig_activate()
{
    ig_create_roles();
    ig_create_tables();
    add_option("ig_votacao_status", "aberta");
}

function ig_deactivate()
{
    ig_remove_roles();
    delete_option("ig_votacao_status");
}

function ig_create_roles()
{
    add_role("avaliador_ifsp", "Avaliador IFSP", [
        "read" => true,
        "avaliar_jogos_ifsp" => true,
    ]);
    $admin_role = get_role("administrator");
    if ($admin_role) {
        $admin_role->add_cap("avaliar_jogos_ifsp");
        $admin_role->add_cap("gerenciar_mostra");
    }
}

function ig_remove_roles()
{
    remove_role("avaliador_ifsp");
    $admin_role = get_role("administrator");
    if ($admin_role) {
        $admin_role->remove_cap("avaliar_jogos_ifsp");
        $admin_role->remove_cap("gerenciar_mostra");
    }
}

function ig_create_tables()
{
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    require_once ABSPATH . "wp-admin/includes/upgrade.php";

    $sql_jogos = "CREATE TABLE {$wpdb->prefix}ig_jogos ( id INT AUTO_INCREMENT PRIMARY KEY, nome VARCHAR(100) NOT NULL UNIQUE ) $charset_collate;";
    dbDelta($sql_jogos);
    $sql_criterios = "CREATE TABLE {$wpdb->prefix}ig_criterios ( id INT AUTO_INCREMENT PRIMARY KEY, nome VARCHAR(100) NOT NULL, peso DECIMAL(5,2) DEFAULT 20.00 ) $charset_collate;";
    dbDelta($sql_criterios);
    $sql_avaliacoes_ifsp = "CREATE TABLE {$wpdb->prefix}ig_avaliacoes_ifsp ( id INT AUTO_INCREMENT PRIMARY KEY, user_id BIGINT(20) UNSIGNED NOT NULL, jogo_id INT NOT NULL, criterio_id INT NOT NULL, nota DECIMAL(3,1) NOT NULL, atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, UNIQUE (user_id, jogo_id, criterio_id) ) $charset_collate;";
    dbDelta($sql_avaliacoes_ifsp);
    $sql_avaliacoes_visitantes = "CREATE TABLE {$wpdb->prefix}ig_avaliacoes_visitantes ( id INT AUTO_INCREMENT PRIMARY KEY, cpf VARCHAR(14) NOT NULL, jogo_id INT NOT NULL, criterio_id INT NOT NULL, nota DECIMAL(3,1) NOT NULL, atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, UNIQUE (cpf, jogo_id, criterio_id) ) $charset_collate;";
    dbDelta($sql_avaliacoes_visitantes);

    $sql_cpf_finalizados = "CREATE TABLE {$wpdb->prefix}ig_cpf_finalizados (
        id INT AUTO_INCREMENT PRIMARY KEY,
        cpf VARCHAR(14) NOT NULL UNIQUE,
        finalizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) $charset_collate;";
    dbDelta($sql_cpf_finalizados);
}

?>