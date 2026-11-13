<?php
/**
 * Приём заявок с форм сайта (п. 4.7 ТЗ) — адрес /send/, метод POST.
 *
 * Формы:
 *   quote — «Получить расчёт» на главной (имя, телефон, тип вывески);
 *   order — «Заказать» во всплывающем окне карточки товара
 *           (имя, телефон, комментарий; товар подставляется автоматически).
 *
 * Защита от спама (без сторонней капчи):
 *   1) скрытое поле-ловушка website: люди его не видят и не заполняют;
 *   2) не более RATE_LIMIT отправок с одного IP за RATE_WINDOW минут;
 *   3) проверка данных на сервере.
 *
 * После отправки показывается страница «Ваше сообщение отправлено»,
 * которая через 3 секунды возвращает на страницу, откуда пришла заявка.
 */

declare(strict_types=1);

namespace Site;

use Core\Db;
use Core\Html;
use Core\Mailer;
use Core\Models\Product;
use Core\Request;

final class FormController extends Controller
{
    /** Максимум заявок с одного IP за окно времени. */
    public const RATE_LIMIT = 5;
    /** Окно ограничения частоты, минут. */
    public const RATE_WINDOW = 10;
    /** Тема письма. */
    public const SUBJECT = 'Новая заявка с сайта';

    public function submit(): void
    {
        if (!Request::isPost()) {
            throw new NotFound();
        }
        $back = self::safeBack(Request::post('back'));

        // 1. Поле-ловушка заполнено — это бот: делаем вид, что всё хорошо.
        if (trim(Request::post('website')) !== '') {
            $this->sent($back);
            return;
        }

        // 2. Ограничение частоты отправки с одного IP.
        $ip = Request::ipBinary();
        Db::query('DELETE FROM form_submissions_log WHERE created_at < ?', [date('Y-m-d H:i:s', time() - 86400)]);
        $recent = (int) Db::value(
            'SELECT COUNT(*) FROM form_submissions_log WHERE ip = ? AND created_at > ?',
            [$ip, date('Y-m-d H:i:s', time() - self::RATE_WINDOW * 60)]
        );
        if ($recent >= self::RATE_LIMIT) {
            $this->failed($back, ['Слишком много заявок подряд. Пожалуйста, попробуйте через несколько минут или позвоните нам.'], 429);
            return;
        }

        // 3. Проверка данных.
        $form = Request::post('form') === 'order' ? 'order' : 'quote';
        $name = Html::filterPlain(Request::post('name'));
        $phone = Html::filterPlain(Request::post('phone'));
        $type = Html::filterPlain(Request::post('type'));
        $comment = Html::filterPlain(Request::post('comment'));
        $errors = [];
        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            $errors[] = 'Введите имя (от 2 до 100 символов).';
        }
        $digits = preg_replace('/\D/', '', $phone);
        if (strlen((string) $digits) < 10 || strlen((string) $digits) > 15 || mb_strlen($phone) > 40) {
            $errors[] = 'Введите телефон полностью.';
        }
        if (mb_strlen($type) > 100 || mb_strlen($comment) > 3000) {
            $errors[] = 'Слишком длинный текст.';
        }
        $product = null;
        if ($form === 'order') {
            $product = Product::find((int) Request::post('product_id'));
            if (!$product || !Product::isVisible($product)) {
                $errors[] = 'Товар не найден.';
            }
        }
        if ($errors) {
            $this->failed($back, $errors, 422);
            return;
        }

        Db::insert('form_submissions_log', ['ip' => $ip, 'created_at' => Db::now()]);

        // Письмо: значения всех полей формы и адрес страницы-источника.
        $lines = [];
        $lines[] = 'Форма: ' . ($form === 'order' ? 'Заказ товара' : 'Получить расчёт');
        $lines[] = 'Имя: ' . $name;
        $lines[] = 'Телефон: ' . $phone;
        if ($form === 'quote') {
            $lines[] = 'Что нужно изготовить: ' . ($type !== '' ? $type : '—');
        } else {
            $lines[] = 'Товар: ' . $product['name'];
            $lines[] = 'Артикул: ' . $product['sku'];
            $lines[] = 'Ссылка на товар: ' . Seo::absolute((string) $product['path']);
            $lines[] = 'Комментарий: ' . ($comment !== '' ? $comment : '—');
        }
        $lines[] = '';
        $lines[] = 'Страница отправки: ' . Seo::absolute($back);
        $lines[] = 'Дата: ' . date('d.m.Y H:i');
        $lines[] = 'IP: ' . Request::ip();

        try {
            Mailer::send(self::SUBJECT, implode("\n", $lines));
        } catch (\Throwable $e) {
            error_log('CMS: ошибка отправки заявки: ' . $e->getMessage());
            $this->failed($back, ['Не удалось отправить заявку из-за технической ошибки. Пожалуйста, позвоните нам.'], 500);
            return;
        }
        $this->sent($back);
    }

    /**
     * Адрес возврата: только путь на этом же сайте (защита от открытого
     * редиректа на чужие сайты).
     */
    public static function safeBack(string $back): string
    {
        $back = trim($back);
        if ($back === '' || $back[0] !== '/' || str_starts_with($back, '//') || str_contains($back, '\\')
            || preg_match('/[\x00-\x1F\x7F]/', $back)) {
            return '/';
        }
        return $back;
    }

    private function sent(string $back): void
    {
        $seo = new Seo();
        $seo->title = 'Ваше сообщение отправлено';
        Layout::render('sent', ['seo' => $seo, 'back' => $back, 'errors' => []], $back);
    }

    private function failed(string $back, array $errors, int $status): void
    {
        $seo = new Seo();
        $seo->title = 'Заявка не отправлена';
        Layout::render('sent', ['seo' => $seo, 'back' => $back, 'errors' => $errors], $back, $status);
    }
}
