<?php
/**
 * Работа с базой данных MySQL через PDO.
 *
 * Все запросы выполняются только подготовленными выражениями
 * (защита от SQL-инъекций, п. 8.3 ТЗ). Эмуляция подготовленных
 * выражений отключена, ошибки выбрасываются исключениями.
 */

declare(strict_types=1);

namespace Core;

use PDO;
use PDOStatement;

final class Db
{
    private static ?PDO $pdo = null;

    /** Ленивое подключение к БД с параметрами из конфигурации. */
    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            self::$pdo = self::connect(
                (string) Config::get('db_host'),
                (string) Config::get('db_port'),
                (string) Config::get('db_name'),
                (string) Config::get('db_user'),
                (string) Config::get('db_pass')
            );
        }
        return self::$pdo;
    }

    /** Создать подключение (используется также установщиком). */
    public static function connect(string $host, string $port, string $name, string $user, string $pass): PDO
    {
        $dsn = 'mysql:host=' . $host . ($port !== '' ? ';port=' . (int) $port : '')
            . ($name !== '' ? ';dbname=' . $name : '') . ';charset=utf8mb4';
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
        ]);
        $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        return $pdo;
    }

    /** Использовать готовое подключение (установщик, тесты). */
    public static function setPdo(PDO $pdo): void
    {
        self::$pdo = $pdo;
    }

    /** Выполнить запрос с параметрами. */
    public static function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        foreach ($params as $k => $v) {
            $name = is_int($k) ? $k + 1 : (str_starts_with($k, ':') ? $k : ':' . $k);
            $type = PDO::PARAM_STR;
            if (is_int($v)) {
                $type = PDO::PARAM_INT;
            } elseif ($v === null) {
                $type = PDO::PARAM_NULL;
            } elseif (is_bool($v)) {
                $type = PDO::PARAM_INT;
                $v = (int) $v;
            }
            $stmt->bindValue($name, $v, $type);
        }
        $stmt->execute();
        return $stmt;
    }

    /** Одна строка или null. */
    public static function row(string $sql, array $params = []): ?array
    {
        $row = self::query($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /** Все строки. */
    public static function all(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    /** Значение первой колонки первой строки или null. */
    public static function value(string $sql, array $params = [])
    {
        $v = self::query($sql, $params)->fetchColumn();
        return $v === false ? null : $v;
    }

    /** Значения первой колонки всех строк. */
    public static function column(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll(PDO::FETCH_COLUMN);
    }

    /** Вставка строки; возвращает id. Имена колонок — только из кода, не от пользователя. */
    public static function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $sql = 'INSERT INTO `' . $table . '` (`' . implode('`,`', $cols) . '`) VALUES (:' . implode(', :', $cols) . ')';
        self::query($sql, $data);
        return (int) self::pdo()->lastInsertId();
    }

    /** Обновление строки по id. */
    public static function update(string $table, int $id, array $data): void
    {
        if (!$data) {
            return;
        }
        $set = [];
        foreach (array_keys($data) as $col) {
            $set[] = '`' . $col . '` = :' . $col;
        }
        $data['__id'] = $id;
        self::query('UPDATE `' . $table . '` SET ' . implode(', ', $set) . ' WHERE id = :__id', $data);
    }

    /**
     * Выполнить функцию в транзакции. Вложенные вызовы выполняются
     * внутри внешней транзакции.
     */
    public static function transaction(callable $fn)
    {
        $pdo = self::pdo();
        if ($pdo->inTransaction()) {
            return $fn();
        }
        $pdo->beginTransaction();
        try {
            $result = $fn();
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** Текущие дата и время в формате MySQL. */
    public static function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    /** Список «?, ?, ?» для IN (...). */
    public static function placeholders(array $items): string
    {
        return implode(',', array_fill(0, max(1, count($items)), '?'));
    }
}
