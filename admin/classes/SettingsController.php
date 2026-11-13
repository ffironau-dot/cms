<?php
/**
 * Общие элементы сайта: адрес (п. 4.1), телефон (п. 4.3), логотип (п. 4.2),
 * Title/Description и OG-теги главной страницы (п. 4.6).
 */

declare(strict_types=1);

namespace Admin;

use Core\Html;
use Core\Request;
use Core\Settings;
use Core\Upload;

final class SettingsController extends Controller
{
    /** Адрес компании в шапке. */
    public function address(): void
    {
        if ($this->isValidPost()) {
            Settings::set('address', Html::filterPlain(Request::post('address')));
            $this->flash('success', 'Адрес сохранён.');
            $this->redirect('/address');
        }
        $this->render('setting-line', [
            'name'  => 'address',
            'label' => 'Адрес компании (одной строкой)',
            'value' => Settings::get('address'),
            'hint'  => 'Выводится в шапке сайта (с микроразметкой Schema.org PostalAddress), в блоке контактов у формы заявки и в блоке «Как нас найти». Оставьте пустым, если адреса нет.',
            'action' => '/address',
        ], 'Адрес в шапке', 'address');
    }

    /** Телефон в шапке. */
    public function phone(): void
    {
        if ($this->isValidPost()) {
            Settings::set('phone', Html::filterPlain(Request::post('phone')));
            $this->flash('success', 'Телефон сохранён.');
            $this->redirect('/phone');
        }
        $this->render('setting-line', [
            'name'  => 'phone',
            'label' => 'Телефон',
            'value' => Settings::get('phone'),
            'hint'  => 'Вводится в произвольном виде, например +7 (495) 123-45-67, и выводится так же. Для ссылки tel: автоматически остаются только цифры и «+».',
            'action' => '/phone',
        ], 'Телефон в шапке', 'phone');
    }

    /** Смена логотипа. */
    public function logo(): void
    {
        $errors = [];
        if ($this->isValidPost()) {
            try {
                $file = Upload::fromRequest('logo');
                $old = Settings::get('logo_file');
                if ($file) {
                    $name = Upload::storeImage($file, 'logo', Upload::ALL_IMAGES);
                    Settings::set('logo_file', $name);
                    // При замене логотипа старый файл удаляется с сервера.
                    Upload::delete('logo', $old);
                } elseif (Request::post('logo_remove') === '1') {
                    Settings::set('logo_file', '');
                    Upload::delete('logo', $old);
                }
                Settings::set('logo_alt', Html::filterPlain(Request::post('logo_alt')));
                $this->flash('success', 'Логотип сохранён.');
                $this->redirect('/logo');
            } catch (\RuntimeException $e) {
                $errors[] = $e->getMessage();
            }
        }
        $this->render('logo', [
            'errors' => $errors,
            'url'    => Upload::url('logo', Settings::get('logo_file')),
            'alt'    => Settings::get('logo_alt'),
        ], 'Смена логотипа', 'logo');
    }

    /** Главная страница: Title, Description, OG. */
    public function home(): void
    {
        $errors = [];
        if ($this->isValidPost()) {
            try {
                foreach (['home_title', 'home_description', 'home_og_title', 'home_og_description'] as $k) {
                    Settings::set($k, Html::filterPlain(Request::post($k)));
                }
                $old = Settings::get('home_og_image');
                if ($file = Upload::fromRequest('home_og_image')) {
                    Settings::set('home_og_image', Upload::storeImage($file, 'og', Upload::ALL_IMAGES));
                    Upload::delete('og', $old);
                } elseif (Request::post('home_og_image_remove') === '1') {
                    Settings::set('home_og_image', '');
                    Upload::delete('og', $old);
                }
                $this->flash('success', 'Настройки главной страницы сохранены.');
                $this->redirect('/home');
            } catch (\RuntimeException $e) {
                $errors[] = $e->getMessage();
            }
        }
        $this->render('home', [
            'errors' => $errors,
            's'      => Settings::all(),
            'ogUrl'  => Upload::url('og', Settings::get('home_og_image')),
        ], 'Главная страница: SEO', 'home');
    }
}
