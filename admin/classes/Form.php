<?php
/**
 * Помощники для форм админки: поля ввода с подписями и подсказками.
 * Все значения экранируются.
 */

declare(strict_types=1);

namespace Admin;

use Core\Html;

final class Form
{
    public static function e($v): string
    {
        return Html::e($v);
    }

    /** Скрытое поле с CSRF-токеном. */
    public static function token(): string
    {
        return '<input type="hidden" name="_token" value="' . Html::e(Auth::csrfToken()) . '">';
    }

    /** Однострочное поле. */
    public static function text(string $name, string $label, $value, array $opt = []): string
    {
        $id = 'f-' . $name;
        $type = $opt['type'] ?? 'text';
        $attrs = '';
        foreach (['placeholder', 'maxlength', 'pattern', 'min', 'max', 'step', 'inputmode'] as $a) {
            if (isset($opt[$a])) {
                $attrs .= ' ' . $a . '="' . Html::e($opt[$a]) . '"';
            }
        }
        if (!empty($opt['required'])) {
            $attrs .= ' required';
        }
        if (!empty($opt['readonly'])) {
            $attrs .= ' readonly';
        }
        $prefix = isset($opt['prefix']) ? '<span class="a-field__prefix">' . Html::e($opt['prefix']) . '</span>' : '';
        return '<div class="a-field"><label for="' . $id . '">' . Html::e($label) . (!empty($opt['required']) ? ' <span class="a-req">*</span>' : '') . '</label>'
            . '<div class="a-field__row">' . $prefix . '<input id="' . $id . '" type="' . Html::e($type) . '" name="' . Html::e($name) . '" value="' . Html::e((string) $value) . '"' . $attrs . '></div>'
            . self::hint($opt) . '</div>';
    }

    /** Многострочное поле. */
    public static function textarea(string $name, string $label, $value, array $opt = []): string
    {
        $id = 'f-' . $name;
        $rows = (int) ($opt['rows'] ?? 4);
        $class = !empty($opt['code']) ? ' class="a-code"' : '';
        return '<div class="a-field"><label for="' . $id . '">' . Html::e($label) . '</label>'
            . '<textarea id="' . $id . '" name="' . Html::e($name) . '" rows="' . $rows . '"' . $class . '>' . Html::e((string) $value) . '</textarea>'
            . self::hint($opt) . '</div>';
    }

    /** Чекбокс. */
    public static function checkbox(string $name, string $label, bool $checked, array $opt = []): string
    {
        return '<div class="a-field a-field--check"><label><input type="checkbox" name="' . Html::e($name) . '" value="1"' . ($checked ? ' checked' : '') . '> '
            . Html::e($label) . '</label>' . self::hint($opt) . '</div>';
    }

    /**
     * Выпадающий список.
     * @param array $options [значение => подпись]
     */
    public static function select(string $name, string $label, $value, array $options, array $opt = []): string
    {
        $id = 'f-' . $name;
        $html = '<div class="a-field"><label for="' . $id . '">' . Html::e($label) . '</label><select id="' . $id . '" name="' . Html::e($name) . '">';
        foreach ($options as $v => $text) {
            $html .= '<option value="' . Html::e((string) $v) . '"' . ((string) $v === (string) $value ? ' selected' : '') . '>' . Html::e($text) . '</option>';
        }
        return $html . '</select>' . self::hint($opt) . '</div>';
    }

    /** Поле загрузки файла с превью текущего изображения. */
    public static function image(string $name, string $label, string $currentUrl, array $opt = []): string
    {
        $id = 'f-' . $name;
        $html = '<div class="a-field"><label for="' . $id . '">' . Html::e($label) . '</label>';
        if ($currentUrl !== '') {
            $html .= '<div class="a-preview"><img src="' . Html::e($currentUrl) . '" alt=""></div>';
            if (!empty($opt['removable'])) {
                $html .= '<label class="a-inline"><input type="checkbox" name="' . Html::e($name) . '_remove" value="1"> удалить изображение</label>';
            }
        }
        $accept = $opt['accept'] ?? 'image/jpeg,image/png,image/gif,image/webp';
        $html .= '<input id="' . $id . '" type="file" name="' . Html::e($name) . '" accept="' . Html::e($accept) . '">';
        return $html . self::hint($opt) . '</div>';
    }

    private static function hint(array $opt): string
    {
        return isset($opt['hint']) ? '<p class="a-hint">' . $opt['hint'] . '</p>' : '';
    }

    /** Блок ошибок формы. */
    public static function errors(array $errors): string
    {
        if (!$errors) {
            return '';
        }
        $html = '<div class="a-alert a-alert--error"><ul>';
        foreach ($errors as $e) {
            $html .= '<li>' . Html::e($e) . '</li>';
        }
        return $html . '</ul></div>';
    }

    /** Ссылка «удалить» (ведёт на страницу подтверждения). */
    public static function deleteLink(string $sub, int $id): string
    {
        return '<a class="a-link-danger" href="' . Html::e(CMS_ADMIN_PREFIX . $sub . '?id=' . $id) . '">удалить</a>';
    }

    /** Отметка «да/нет» для списков. */
    public static function yesNo($v): string
    {
        return (int) $v === 1 ? '<span class="a-badge a-badge--on">вкл</span>' : '<span class="a-badge a-badge--off">выкл</span>';
    }
}
