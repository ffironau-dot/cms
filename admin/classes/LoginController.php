<?php
/**
 * Вход в административную панель и выход.
 */

declare(strict_types=1);

namespace Admin;

use Core\Request;
use Core\View;

final class LoginController extends Controller
{
    public function login(): void
    {
        if (Auth::check()) {
            $this->redirect('');
        }
        $error = null;
        if (Request::isPost()) {
            if (!Auth::checkCsrf()) {
                $error = 'Форма устарела, попробуйте ещё раз.';
            } else {
                $error = Auth::attempt(Request::post('login'), Request::post('password'));
                if ($error === null) {
                    $this->redirect('');
                }
            }
        }
        $flash = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
        header('Content-Type: text/html; charset=utf-8');
        echo View::render($this->tplDir() . '/login.php', [
            'error' => $error,
            'flash' => $flash,
            'login' => Request::post('login'),
        ]);
    }

    public function logout(): void
    {
        if ($this->isValidPost()) {
            Auth::logout();
            $this->flash('info', 'Вы вышли из панели управления.');
        }
        $this->redirect('/login');
    }
}
