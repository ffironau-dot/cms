<?php
/**
 * Авторизация администратора (п. 3.2, 3.3, 8.3 ТЗ).
 *
 *  - логин и хеш пароля — в include/connect.php (password_hash / password_verify);
 *  - сессия: cookie HttpOnly, SameSite=Lax, Secure при HTTPS, строгий режим,
 *    новый идентификатор сессии после входа;
 *  - время жизни: 2 часа неактивности, затем нужен повторный вход;
 *  - после 5 неверных попыток с одного IP вход с него блокируется на 15 минут.
 */

declare(strict_types=1);

namespace Admin;

use Core\Config;
use Core\Db;
use Core\Request;

final class Auth
{
    /** Время жизни сессии при бездействии, секунд (2 часа). */
    public const IDLE_TIMEOUT = 7200;
    /** Число неверных попыток до блокировки. */
    public const MAX_FAILS = 5;
    /** Длительность блокировки, секунд (15 минут). */
    public const LOCK_SECONDS = 900;

    /** Запустить сессию администратора с безопасными параметрами. */
    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.gc_maxlifetime', (string) self::IDLE_TIMEOUT);
        session_name('cms_adm');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => CMS_ADMIN_PREFIX,
            'secure'   => Request::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    /** Авторизован ли администратор (с проверкой времени бездействия). */
    public static function check(): bool
    {
        if (empty($_SESSION['admin'])) {
            return false;
        }
        $last = (int) ($_SESSION['last_activity'] ?? 0);
        if (time() - $last > self::IDLE_TIMEOUT) {
            self::logout();
            $_SESSION['flash'] = [['info', 'Сессия завершена после 2 часов бездействия. Войдите снова.']];
            return false;
        }
        $_SESSION['last_activity'] = time();
        return true;
    }

    /**
     * Попытка входа. Возвращает null при успехе или текст ошибки.
     */
    public static function attempt(string $login, string $password): ?string
    {
        $ip = Request::ipBinary();
        $row = Db::row('SELECT * FROM login_attempts WHERE ip = ?', [$ip]);
        if ($row && $row['locked_until'] !== null && strtotime($row['locked_until']) > time()) {
            $minutes = (int) ceil((strtotime($row['locked_until']) - time()) / 60);
            return 'Слишком много неверных попыток. Вход заблокирован, попробуйте через ' . $minutes . ' мин.';
        }

        $okLogin = hash_equals((string) Config::get('admin_login', ''), $login);
        $okPass = password_verify($password, (string) Config::get('admin_hash', ''));
        if ($okLogin && $okPass && $login !== '') {
            Db::query('DELETE FROM login_attempts WHERE ip = ?', [$ip]);
            session_regenerate_id(true);
            $_SESSION = [
                'admin'         => true,
                'last_activity' => time(),
                'csrf'          => bin2hex(random_bytes(32)),
            ];
            return null;
        }

        // Неверная попытка. Счётчик начинается заново после истёкшей блокировки
        // и если с прошлой неудачи прошло больше 15 минут.
        if (!$row || $row['locked_until'] !== null || strtotime($row['last_fail_at']) < time() - self::LOCK_SECONDS) {
            $fails = 1;
        } else {
            $fails = (int) $row['fails'] + 1;
        }
        $locked = $fails >= self::MAX_FAILS ? date('Y-m-d H:i:s', time() + self::LOCK_SECONDS) : null;
        Db::query(
            'INSERT INTO login_attempts (ip, fails, last_fail_at, locked_until) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE fails = VALUES(fails), last_fail_at = VALUES(last_fail_at), locked_until = VALUES(locked_until)',
            [$ip, $locked ? 0 : $fails, Db::now(), $locked]
        );
        if ($locked) {
            return 'Слишком много неверных попыток. Вход заблокирован на 15 минут.';
        }
        return 'Неверный логин или пароль. Осталось попыток: ' . (self::MAX_FAILS - $fails) . '.';
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    /** CSRF-токен текущей сессии. */
    public static function csrfToken(): string
    {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return (string) $_SESSION['csrf'];
    }

    /** Проверка CSRF-токена из POST. */
    public static function checkCsrf(): bool
    {
        $sent = Request::post('_token');
        return $sent !== '' && hash_equals(self::csrfToken(), $sent);
    }
}
