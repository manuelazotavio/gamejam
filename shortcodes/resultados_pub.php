<?php


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

?>