<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: auth.php');
    exit;
}
$user_id = $_SESSION['user_id'];

$lazyMode = isset($_GET['lazy']) && $_GET['lazy'] == '1';
$words = [];
if (file_exists('words.json')) {
    $words = json_decode(file_get_contents('words.json'), true);
}
if (!$words || !is_array($words)) {
    $words = ["dom", "pies", "kot", "szkoła", "rower", "łóżko", "środa", "żaba", "źródło", "góra", "wąż", "ćma"];
}
function normalizePolish($str)
{
    $map = [
        'ą' => 'a',
        'ć' => 'c',
        'ę' => 'e',
        'ł' => 'l',
        'ń' => 'n',
        'ó' => 'o',
        'ś' => 's',
        'ź' => 'z',
        'ż' => 'z',
        'Ą' => 'A',
        'Ć' => 'C',
        'Ę' => 'E',
        'Ł' => 'L',
        'Ń' => 'N',
        'Ó' => 'O',
        'Ś' => 'S',
        'Ź' => 'Z',
        'Ż' => 'Z'
    ];
    return strtr($str, $map);
}
$availableTimes = [15, 30, 60];
$selectedTime = (isset($_GET['czas']) && in_array(intval($_GET['czas']), $availableTimes)) ? intval($_GET['czas']) : 30;
$wordsCount = [15 => 50, 30 => 100, 60 => 200];
$lineLength = 7;
function generateMonkeyText($words, $wordsTarget)
{
    $arr = [];
    for ($i = 0; $i < $wordsTarget; $i++) {
        $arr[] = $words[array_rand($words)];
    }
    return implode(' ', $arr);
}
function splitWords($txt, $lineLength)
{
    $arr = array_values(array_filter(explode(' ', $txt), fn($w) => strlen($w) > 0));
    $lines = [];
    for ($i = 0; $i < count($arr); $i += $lineLength) {
        $lines[] = array_slice($arr, $i, $lineLength);
    }
    return $lines;
}
$textRaw = generateMonkeyText($words, $wordsCount[$selectedTime]);
$textRaw = strtolower($textRaw);
$textLazy = normalizePolish($textRaw);
$linesRaw = splitWords($textRaw, $lineLength);
$linesLazy = splitWords($textLazy, $lineLength);
?>
<!DOCTYPE html>
<html lang="pl">

<head>
    <meta charset="UTF-8">
    <title>Typeracer - Tester Szybkości Pisania</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootswatch@5.3.3/dist/darkly/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="icon" href="favicon.ico">
    <style>
        body {
            background: #222;
            color: #e2e4e6;
        }

        .typeracer-card {
            max-width: 650px;
            margin: 40px auto;
            background: #23272b;
            box-shadow: 0 6px 24px #000a;
            border: 1px solid #313539;
        }

        .card-header {
            font-size: 1.17em;
            letter-spacing: 1px;
            font-weight: 500;
            background: #272b30;
            border-bottom: 1px solid #313539;
        }

        .mode-btn.active {
            background: #e2b714;
            color: #232427;
            border-color: #e2b714;
        }

        .mode-btn {
            margin-right: 10px;
            font-size: 1em;
            border-radius: 7px;
            padding: 0.35em 1.1em;
        }

        .lazy-btn {
            font-size: 1em;
            float: right;
            margin: 0 0 10px 0;
        }

        #typeracer-input {
            font-size: 1.13em;
            font-family: 'Roboto Mono', monospace;
            padding: 10px 12px;
            border-radius: 11px;
            border: none;
            outline: none;
            background: #181a1b;
            color: #e2b714;
            box-shadow: 0 2px 12px #0008;
            width: 99%;
            margin-bottom: 10px;
        }

        #typeracer-input:focus {
            background: #181a1b;
            color: #e2b714;
            border: none;
            outline: none;
            box-shadow: 0 0 0 2px #e2b71444;
        }

        #text-to-type {
            font-size: 1.14em;
            font-family: 'Roboto Mono', monospace;
            background: #181a1b;
            padding: 12px 13px;
            border-radius: 12px;
            min-height: 36px;
            margin-bottom: 12px;
            box-shadow: 0 2px 12px #0005;
            display: flex;
            flex-direction: column;
            gap: 7px 0;
            align-items: flex-start;
            word-break: break-word;
            width: 100%;
            max-width: 100%;
        }

        .typeracer-line {
            display: flex;
            flex-wrap: wrap;
            gap: 7px 4px;
            width: 100%;
        }

        .word {
            display: inline-flex;
            flex-wrap: nowrap;
            position: relative;
            margin: 0;
            padding: 0 3px;
            border-radius: 5px;
            min-height: 1em;
            transition: background .13s;
            background: none;
        }

        .active-word {
            background: #e2b71433;
        }

        .letter-right {
            color: #e2b714;
        }

        .letter-wrong {
            color: #ff5a36;
        }

        .letter-default {
            color: #646669;
        }

        .caret {
            display: inline-block;
            width: 2px;
            height: 1.2em;
            background: #e2b714;
            vertical-align: bottom;
            animation: blink 1s steps(1) infinite;
            margin-left: -2px;
            margin-right: -2px;
            position: relative;
        }

        @keyframes blink {
            50% {
                opacity: 0;
            }
        }

        .result {
            font-size: 1.09em;
            margin-top: 13px;
            color: #e2b714;
        }

        .timer {
            font-weight: bold;
            font-size: 1.1em;
            letter-spacing: 2px;
            margin-bottom: 8px;
            color: #e2b714;
        }

        .btn-success {
            font-size: 1em;
            margin-top: 7px;
            border-radius: 7px;
        }

        .btn-link {
            font-size: 1em;
        }

        @media (max-width: 800px) {
            .typeracer-card {
                max-width: 99vw;
            }

            #text-to-type {
                font-size: 1em;
                padding: 10px 4px;
            }

            #typeracer-input {
                font-size: 1em;
                padding: 8px;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="card typeracer-card bg-dark border-secondary mt-5">
            <div class="card-header">
                <i class="bi bi-controller"></i> Tester szybkości pisania
            </div>
            <div class="card-body">
                <div class="mb-2">
                    <span>Wybierz czas testu:</span>
                    <?php foreach ($availableTimes as $t): ?>
                        <a href="?czas=<?= $t ?><?= ($lazyMode ? '&lazy=1' : '') ?>"
                            class="btn mode-btn <?= ($selectedTime == $t ? 'active' : '') ?>">
                            <?= $t ?>s
                        </a>
                    <?php endforeach; ?>
                    <button id="lazy-btn" class="btn lazy-btn <?= ($lazyMode ? 'btn-warning' : 'btn-outline-warning') ?>">
                        <i class="bi bi-type"></i> Lazy <?= ($lazyMode ? 'ON' : 'OFF') ?>
                    </button>
                </div>
                <div class="mb-3">
                    <span class="timer" id="timer"><?= sprintf("%02d:00", $selectedTime) ?></span>
                </div>
                <div id="text-to-type" class="mb-3"></div>
                <input type="text" id="typeracer-input" class="form-control mb-2" placeholder="Pisz tutaj..."
                    autocomplete="off" autofocus spellcheck="false">
                <button class="btn btn-success" id="restart-btn" style="display:none;"><i class="fa fa-redo"></i>
                    Jeszcze raz</button>
                <div id="result" class="result"></div>
                <div class="mt-3">
                    <a href="index.php" class="btn btn-link text-light"><i class="fa fa-arrow-left"></i> Powrót</a>
                </div>
            </div>
        </div>
    </div>
    <script>
        const linesRaw = <?= json_encode($linesRaw) ?>;
        const linesLazy = <?= json_encode($linesLazy) ?>;
        let lazyMode = <?= $lazyMode ? 'true' : 'false' ?>;
        let selectedTime = <?= json_encode($selectedTime) ?>;
        let started = false, finished = false, timeLeft = selectedTime, timerInterval;
        let inputHistory = [];
        let activeLine = 0;
        const input = document.getElementById('typeracer-input');
        const timerSpan = document.getElementById('timer');
        const resultDiv = document.getElementById('result');
        const restartBtn = document.getElementById('restart-btn');
        const textToType = document.getElementById('text-to-type');
        const lazyBtn = document.getElementById('lazy-btn');
        function updateTimer() {
            if (!started) return;
            timeLeft -= 1;
            let min = String(Math.floor(timeLeft / 60)).padStart(2, '0');
            let sec = String(timeLeft % 60).padStart(2, '0');
            timerSpan.textContent = min + ':' + sec;
            if (timeLeft <= 0) {
                endTest();
            }
        }
        function normalizePolish(str) {
            const map = {
                'ą': 'a', 'ć': 'c', 'ę': 'e', 'ł': 'l', 'ń': 'n', 'ó': 'o', 'ś': 's', 'ź': 'z', 'ż': 'z',
                'Ą': 'A', 'Ć': 'C', 'Ę': 'E', 'Ł': 'L', 'Ń': 'N', 'Ó': 'O', 'Ś': 'S', 'Ź': 'Z', 'Ż': 'Z'
            };
            return str.replace(/[ąćęłńóśźżĄĆĘŁŃÓŚŹŻ]/g, m => map[m] || m);
        }
        function renderActiveLines(inputArr) {
            let lines = lazyMode ? linesLazy : linesRaw;
            let wordsLine1 = lines[activeLine] || [];
            let wordsLine2 = lines[activeLine + 1] || [];
            textToType.innerHTML = '';

            function renderLine(words, inputArrLine, isActive) {
                let lineDiv = document.createElement('div');
                lineDiv.className = 'typeracer-line';
                let inputPos = 0, caretSet = false;
                for (let i = 0; i < words.length; i++) {
                    let wordSpan = document.createElement('span');
                    wordSpan.className = "word";
                    if (isActive && i === inputArrLine.length - 1) wordSpan.classList.add('active-word');
                    let userWord = (inputArrLine[i] !== undefined) ? inputArrLine[i] : '';
                    for (let j = 0; j < words[i].length; j++) {
                        let letterSpan = document.createElement('span');
                        letterSpan.className = "letter letter-default";
                        letterSpan.textContent = words[i][j];
                        if (isActive && i < inputArrLine.length) {
                            let typedChar = userWord[j];
                            if (lazyMode && typedChar) typedChar = normalizePolish(typedChar);
                            if (typedChar === undefined) {
                                letterSpan.className = 'letter letter-default';
                            } else if (typedChar === words[i][j]) {
                                letterSpan.className = 'letter letter-right';
                            } else {
                                letterSpan.className = 'letter letter-wrong';
                            }
                        }
                        if (isActive && !caretSet && i === inputArrLine.length - 1 && inputPos === input.selectionStart) {
                            let caret = document.createElement('span');
                            caret.className = 'caret';
                            wordSpan.appendChild(caret);
                            caretSet = true;
                        }
                        inputPos++;
                        wordSpan.appendChild(letterSpan);
                    }
                    if (isActive && !caretSet && i === inputArrLine.length - 1 && (inputPos === input.selectionStart)) {
                        let caret = document.createElement('span');
                        caret.className = 'caret';
                        wordSpan.appendChild(caret);
                        caretSet = true;
                    }
                    inputPos++;
                    lineDiv.appendChild(wordSpan);
                }
                textToType.appendChild(lineDiv);
            }

            renderLine(wordsLine1, inputArr, true);
            renderLine(wordsLine2, [], false);
        }
        input.addEventListener('input', function (e) {
            if (finished) return;
            if (!started) {
                started = true;
                timerInterval = setInterval(updateTimer, 1000);
            }
            let val = input.value;
            let inputArr = val.split(' ');
            let lines = lazyMode ? linesLazy : linesRaw;
            if (inputArr.length > (lines[activeLine] || []).length) {
                inputHistory.push(inputArr.slice(0, (lines[activeLine] || []).length));
                input.value = '';
                activeLine++;
                if (activeLine < lines.length) {
                    renderActiveLines([]);
                } else {
                    endTest();
                }
            } else {
                renderActiveLines(inputArr);
            }
        });
        input.addEventListener('click', function (e) { renderActiveLines(input.value.split(' ')); });
        input.addEventListener('keyup', function (e) { renderActiveLines(input.value.split(' ')); });
        function endTest() {
            finished = true;
            clearInterval(timerInterval);
            input.disabled = true;
            let lines = lazyMode ? linesLazy : linesRaw;
            let correctWordChars = 0;
            let correctSpaces = 0;
            let errors = 0;
            let wordIndex = 0;
            for (let k = 0; k < inputHistory.length; k++) {
                let inputArr = inputHistory[k];
                let words = lines[k] || [];
                for (let i = 0; i < words.length; i++) {
                    let userWord = inputArr[i];
                    let targetWord = words[i];
                    if (lazyMode && userWord) userWord = normalizePolish(userWord);
                    if (userWord !== undefined && userWord === targetWord) {
                        correctWordChars += targetWord.length;
                        correctSpaces++;
                    }
                    if (userWord !== undefined && userWord !== targetWord) {
                        errors++;
                    }
                    wordIndex++;
                }
            }
            if (!finished) {
                let inputArr = input.value.split(' ');
                let words = lines[activeLine] || [];
                for (let i = 0; i < words.length; i++) {
                    let userWord = inputArr[i];
                    let targetWord = words[i];
                    if (lazyMode && userWord) userWord = normalizePolish(userWord);
                    if (userWord !== undefined && userWord === targetWord) {
                        correctWordChars += targetWord.length;
                        correctSpaces++;
                    }
                    if (userWord !== undefined && userWord !== targetWord) {
                        errors++;
                    }
                    wordIndex++;
                }
            }
            let timeUsed = selectedTime;
            let wpm = Math.round(((correctWordChars + correctSpaces) / 5) / (timeUsed / 60));
            resultDiv.innerHTML =
                '<b>Twój wynik:</b><br>' +
                '<span class="badge bg-success me-2"><i class="fa fa-tachometer-alt"></i> ' + wpm + ' WPM</span>' +
                '<span class="badge bg-info me-2"><i class="fa fa-clock"></i> ' + timeUsed + ' s</span>' +
                '<span class="badge bg-warning"><i class="fa fa-exclamation"></i> Błędy: ' + errors + '</span>' +
                '<br><span class="badge bg-secondary">Poprawnych słów: ' + (correctSpaces) + '</span>';
            restartBtn.style.display = '';
        }
        restartBtn.onclick = function () {
            location.href = '?czas=' + selectedTime + (lazyMode ? '&lazy=1' : '');
        };
        lazyBtn.onclick = function () {
            let url = '?czas=' + selectedTime + '&lazy=' + (lazyMode ? '0' : '1');
            window.location.href = url;
        };
        renderActiveLines([]);
    </script>
</body>

</html>