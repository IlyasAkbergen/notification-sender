<?php

require_once __DIR__.'/vendor/autoload.php';

use Enqueue\SimpleClient\SimpleClient;
use Enqueue\SimpleClient\SimpleMessage;

// Функция для проверки емейла на валидность
function check_email($email) {
    // Заглушка, реализация зависит от требований
    // Можно реализовать вызов внешней функции check_email и возврат её результата
    return true;
}

// Функция для выбора пользователей с истекающей подпиской
function getExpiringUsers($pdo): array
{
    $query = "
        SELECT username, email, validts, confirmed, checked
        FROM users
        WHERE validts - UNIX_TIMESTAMP() BETWEEN 0 AND 3*24*60*60
    ";

    $stmt = $pdo->query($query);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function markCheckedEmails($pdo, $emails): void
{
    $query = "
        UPDATE users
        SET checked = 1
        WHERE email IN (:emails)
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute(['emails' => $emails]);
}

// Подключение к базе данных
$dsn = 'mysql:host=db;dbname=subscriptions_database';
$username = 'root';
$password = 'secret';

try {
    $pdo = new PDO(
        dsn: $dsn,
        username: $username,
        password: $password,
    );
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Создание клиента очереди
$client = new SimpleClient('enqueue');

// Получение пользователей с истекающей подпиской
$expiringUsers = getExpiringUsers($pdo);

// Добавление задач в очередь
foreach ($expiringUsers as $user) {
    $username = $user['username'];
    $email = $user['email'];
    $confirmed = $user['confirmed'];
    $checked = $user['checked'];

    // Если уже подтвеждён, то имейл точно валиден(проверка не нужна).
    // Если не подтверждён, то смотрим делалли ли проверку ранее
    // Если не проверяли, то проверяем на валидность
    // Если не валиден, то не отправляем

    $newCheckedEmails = [];
    if ($confirmed || $checked || $newlyChecked = check_email($email)) {
        // Если проверка прошла успешно, то отмечаем, что уже проверили
        if (isset($newlyChecked)) {
            $newCheckedEmails[] = $email;
        }
        $message = new SimpleMessage(json_encode([
            'username' => $username,
            'email' => $email,
        ]));

        $client->send($message);
    }

    markCheckedEmails($pdo, $newCheckedEmails);
}

// Закрытие соединения с базой данных
$pdo = null;
