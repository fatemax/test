<?php
/**
 * Обработчик формы заявки
 * Отправляет письмо через API smtp.bz
 */

// Настройки
$apiKey = 'YOUR_API_KEY'; // Замените на ваш API ключ от smtp.bz
$fromEmail = 'info@mycompany.com'; // Замените на ваш email домен
$toEmails = ['fatemax@list.ru', 'fatemax2@list.ru'];

// Устанавливаем заголовки для JSON ответа
header('Content-Type: application/json');

// Проверяем метод запроса
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Неверный метод запроса']);
    exit;
}

// Получаем и очищаем данные из формы
$name = isset($_POST['name']) ? trim(htmlspecialchars($_POST['name'])) : '';
$apartment = isset($_POST['apartment']) ? trim(htmlspecialchars($_POST['apartment'])) : '';
$contact = isset($_POST['contact']) ? trim(htmlspecialchars($_POST['contact'])) : '';
$message = isset($_POST['message']) ? trim(htmlspecialchars($_POST['message'])) : '';

// Валидация
if (empty($name) || empty($apartment) || empty($contact) || empty($message)) {
    echo json_encode(['success' => false, 'message' => 'Все поля обязательны для заполнения']);
    exit;
}

// Формируем HTML тело письма
$htmlBody = "
<html>
<head>
    <meta charset='UTF-8'>
    <style>
        body { font-family: Arial, sans-serif; }
        .header { background-color: #4CAF50; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; }
        .field { margin-bottom: 15px; }
        .label { font-weight: bold; color: #555; }
        .value { color: #333; }
    </style>
</head>
<body>
    <div class='header'>
        <h2>Новая заявка с сайта</h2>
    </div>
    <div class='content'>
        <div class='field'>
            <div class='label'>Имя:</div>
            <div class='value'>" . $name . "</div>
        </div>
        <div class='field'>
            <div class='label'>Номер квартиры:</div>
            <div class='value'>" . $apartment . "</div>
        </div>
        <div class='field'>
            <div class='label'>Как связаться:</div>
            <div class='value'>" . $contact . "</div>
        </div>
        <div class='field'>
            <div class='label'>Проблема/Предложение:</div>
            <div class='value'>" . nl2br($message) . "</div>
        </div>
    </div>
</body>
</html>
";

// Формируем текстовую версию письма
$textBody = "Новая заявка с сайта\n\n" .
            "Имя: " . $name . "\n" .
            "Номер квартиры: " . $apartment . "\n" .
            "Как связаться: " . $contact . "\n" .
            "Проблема/Предложение:\n" . $message;

// Отправляем письмо каждому получателю
$successCount = 0;
$failures = [];

foreach ($toEmails as $toEmail) {
    // Инициализируем cURL
    $curl = curl_init();

    // Данные для отправки
    $postData = [
        'subject' => 'Заявка от ' . $name . ' (кв. ' . $apartment . ')',
        'name' => $name,
        'html' => $htmlBody,
        'text' => $textBody,
        'from' => $fromEmail,
        'to' => $toEmail,
        'to_name' => 'Администратор'
    ];

    curl_setopt_array($curl, [
        CURLOPT_URL => "https://api.smtp.bz/v1/smtp/send",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_HTTPHEADER => [
            "Authorization: " . $apiKey,
            "Content-Type: application/x-www-form-urlencoded"
        ],
        CURLOPT_POSTFIELDS => http_build_query($postData)
    ]);

    // Выполняем запрос
    $response = curl_exec($curl);
    $err = curl_error($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    curl_close($curl);

    // Обрабатываем результат
    if ($err) {
        $failures[] = $toEmail . ': cURL Error: ' . $err;
    } else {
        $result = json_decode($response, true);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            $successCount++;
        } else {
            $errorMsg = isset($result['message']) ? $result['message'] : 'Ошибка при отправке письма';
            $failures[] = $toEmail . ': ' . $errorMsg;
        }
    }
}

// Возвращаем результат
if ($successCount > 0 && empty($failures)) {
    echo json_encode(['success' => true, 'message' => 'Письмо успешно отправлено']);
} elseif ($successCount > 0) {
    echo json_encode(['success' => true, 'message' => 'Письмо отправлено частично. Ошибки: ' . implode(', ', $failures)]);
} else {
    echo json_encode(['success' => false, 'message' => 'Ошибка при отправке: ' . implode(', ', $failures)]);
}
?>
