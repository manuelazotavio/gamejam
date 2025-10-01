<?php


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

?>