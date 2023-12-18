<?php

require_once __DIR__.'/vendor/autoload.php';

use Enqueue\SimpleClient\SimpleClient;
use Enqueue\SimpleClient\SimpleMessage;

// Функция для отправки емейла
function send_email($to, $subject, $message): void
{
    // Заглушка, реализация зависит от требований
    // Можно реализовать вызов внешней функции send_email
    // Пример с использованием mail():
    // mail($to, $subject, $message);
}

// Создание клиента очереди
$client = new SimpleClient('enqueue');

// Обработка сообщений из очереди
$client->bind('notifications_queue', function (SimpleMessage $message) {
    $data = json_decode($message->getBody(), true);

    $username = $data['username'];
    $email = $data['email'];

    $subject = "$username, your subscription is expiring soon";
    $message = "Dear $username,\nYour subscription is expiring soon.";

    send_email($email, $subject, $message);
});

// Запуск обработчика
$client->consume('notifications_queue');
