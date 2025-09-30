<?php

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

?>