<?php


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

?>