<?php
/**
 * Автоматические тесты CMS (п. 8.5 ТЗ): маршрутизация, редиректы,
 * уникальность адресов, фильтрация HTML, доступ к админке, а также
 * сквозные сценарии (каталог, формы заявок, загрузка файлов, импорт CSV,
 * слайдер, меню, текстовые страницы).
 *
 * ВНИМАНИЕ: тесты ПЕРЕСОЗДАЮТ ВСЕ ТАБЛИЦЫ базы данных из include/connect.php.
 * Запускайте только на тестовой установке.
 *
 * Запуск (тестовое окружение docker compose):
 *   docker compose exec -e CMS_TEST_ALLOW_RESET=1 -e CMS_TEST_ADMIN_PASS=secret123 web php tests/run.php
 *
 * Переменные окружения:
 *   CMS_TEST_ALLOW_RESET=1   — подтверждение, что БД можно очистить (обязательно)
 *   CMS_TEST_ADMIN_PASS      — пароль администратора этой установки (обязательно)
 *   CMS_TEST_URL             — адрес сайта для HTTP-запросов (по умолчанию http://localhost)
 *   CMS_TEST_MAILPIT         — API тестового SMTP Mailpit (по умолчанию http://mailpit:8025);
 *                              пусто — проверка писем пропускается
 *   CMS_TEST_FILTER          — выполнить только тесты, в названии которых есть эта строка
 */

declare(strict_types=1);

require __DIR__ . '/../include/bootstrap.php';
require __DIR__ . '/lib.php';

use Core\Config;
use Core\Db;
use Core\Format;
use Core\Html;
use Core\Installer;
use Core\Models\Category;
use Core\Models\MenuItem;
use Core\Models\Page;
use Core\Models\Product;
use Core\Models\Section;
use Core\Models\Slide;
use Core\Paginator;
use Core\Settings;
use Core\Upload;
use Core\Urls;
use Site\Menu;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
if (getenv('CMS_TEST_ALLOW_RESET') !== '1' || getenv('CMS_TEST_ADMIN_PASS') === false) {
    fwrite(STDERR, "Тесты очищают базу данных. Укажите CMS_TEST_ALLOW_RESET=1 и CMS_TEST_ADMIN_PASS=<пароль администратора>.\n");
    exit(2);
}

$BASE = getenv('CMS_TEST_URL') ?: 'http://localhost';
$MAILPIT = getenv('CMS_TEST_MAILPIT') === false ? 'http://mailpit:8025' : (string) getenv('CMS_TEST_MAILPIT');
$LOGIN = (string) Config::get('admin_login');
$PASS = (string) getenv('CMS_TEST_ADMIN_PASS');

/** Пересоздать БД и очистить загрузки. */
function reset_db(): void
{
    Installer::dropAll(Db::pdo());
    Installer::install(Db::pdo());
    Category::reset();
    foreach (['logo', 'slides', 'og', 'categories', 'products', 'pages', 'files'] as $dir) {
        foreach (glob(CMS_UPLOADS . '/' . $dir . '/*') ?: [] as $f) {
            if (basename($f) !== '.gitkeep') {
                @unlink($f);
            }
        }
    }
}

/** Файл отсутствует (со сбросом кеша stat: файлы удаляет веб-сервер). */
function gone(string $file): bool
{
    clearstatcache();
    return !is_file($file);
}

/** Авторизованный клиент админки. */
function admin(): Http
{
    global $BASE, $LOGIN, $PASS;
    static $client = null;
    if ($client === null) {
        $client = new Http($BASE);
        $r = $client->login($LOGIN, $PASS);
        if ($r->status !== 303) {
            throw new TestFailure('не удалось войти в админку (проверьте CMS_TEST_ADMIN_PASS)');
        }
    }
    return $client;
}

function site(): Http
{
    global $BASE;
    return new Http($BASE);
}

/** Создать категорию через модель. */
function mkcat(string $name, string $slug, ?int $parent = null, bool $active = true): int
{
    $r = Category::save(['name' => $name, 'slug' => $slug, 'parent_id' => $parent, 'is_active' => $active ? '1' : ''], null);
    if ($r['errors']) {
        throw new TestFailure('категория: ' . implode(' ', $r['errors']));
    }
    Category::reset();
    return (int) $r['id'];
}

function mkprod(string $sku, string $slug, int $cat, array $extra = []): int
{
    $r = Product::save($extra + ['sku' => $sku, 'name' => 'Товар ' . $sku, 'slug' => $slug, 'category_id' => (string) $cat, 'is_active' => '1'], null);
    if ($r['errors']) {
        throw new TestFailure('товар: ' . implode(' ', $r['errors']));
    }
    return (int) $r['id'];
}

/** Письма в Mailpit. */
function mails(): array
{
    global $MAILPIT;
    $json = @file_get_contents($MAILPIT . '/api/v1/messages');
    return $json ? (json_decode($json, true)['messages'] ?? []) : [];
}

function clear_mails(): void
{
    global $MAILPIT;
    if ($MAILPIT === '') {
        return;
    }
    $ctx = stream_context_create(['http' => ['method' => 'DELETE']]);
    @file_get_contents($MAILPIT . '/api/v1/messages', false, $ctx);
}

/* =====================================================================
   1. Модульные тесты: фильтрация HTML, форматирование, адреса, меню
   ===================================================================== */

t('Фильтр HTML: малый текст — только u, i, b, strong, em, br без атрибутов', function () {
    eq('<b>жирный</b> и <i>курсив</i><br>строка', Html::filterSmall('<b onclick="alert(1)">жирный</b> и <i style="x">курсив</i><br/>строка'));
    eq('alert(1) ссылка', Html::filterSmall('<script>evil()</script>alert(1) <a href="javascript:x">ссылка</a>'));
    eq('<strong><em>т</em></strong><u>п</u>', Html::filterSmall('<strong><em>т</em></strong><u>п</u><!-- комментарий -->'));
    eq('&lt;b&gt; как текст', Html::filterSmall('&lt;b&gt; как текст'));
    notContains('onerror', Html::filterSmall('<img src=x onerror=alert(1)><b title="a>b" onmouseover=x>t</b>'));
});

t('Фильтр HTML: остальные поля — теги вырезаются', function () {
    eq('Заголовок жирный', Html::filterPlain('<h1>Заголовок</h1> <b>жирный</b>'));
    eq('Tom & Jerry', Html::filterPlain('Tom & Jerry'));
    eq('a < b', Html::filterPlain('a < b'));
    eq('', Html::filterPlain('<script>alert(1)</script>'));
    eq('x"onmouseover', Html::filterPlain('x"onmouseover'));
});

t('Экранирование вывода', function () {
    eq('&lt;script&gt;&quot;&apos;&amp;', Html::e('<script>"\'&'));
});

t('Цены: округление до рубля по арифметическим правилам и разбор ввода', function () {
    eq("1\u{00A0}251", Format::price('1250.50'));
    eq("1\u{00A0}250", Format::price('1250.49'));
    eq('100', Format::price('99.50'));
    eq('1250.50', Format::parsePrice('1250,50'));
    eq('1250.50', Format::parsePrice('1 250,5'));
    eq(null, Format::parsePrice(''));
    eq(false, Format::parsePrice('12 руб'));
    eq(false, Format::parsePrice('1,2,3'));
});

t('Телефон: в href="tel:" только цифры и +', function () {
    eq('tel:+74951234567', Format::telHref('+7 (495) 123-45-67'));
    eq('tel:84951234567', Format::telHref('8 495 123 45 67 доб.'));
});

t('Адреса: нормализация, сегменты, зарезервированные', function () {
    eq('/o-kompanii/komanda/', Urls::normalizePath('o-kompanii/komanda'));
    eq('/o-kompanii/', Urls::normalizePath('/o-kompanii/'));
    eq(null, Urls::normalizePath('/О компании/'));
    eq(null, Urls::normalizePath('/a/../b/'));
    ok(Urls::isValidSegment('neon-2'));
    ok(!Urls::isValidSegment('Neon'));
    ok(!Urls::isValidSegment('-neon'));
    ok(!Urls::isValidSegment('neon--x'));
    ok(Urls::isReserved('/manet/'));
    ok(Urls::isReserved('/manetka/'), 'всё, что начинается с /manet');
    ok(Urls::isReserved('/uploads/x/'));
    ok(Urls::isReserved('/assets/'));
    ok(Urls::isReserved('/send/'));
    ok(Urls::isReserved('/'));
    ok(!Urls::isReserved('/neon/'));
});

t('Меню: совпадение по префиксу целых сегментов, главная — только точно', function () {
    ok(Menu::matches('/neon/', '/neon/'));
    ok(Menu::matches('/neon/', '/neon/bar/'));
    ok(!Menu::matches('/neon/', '/neonovye/'));
    ok(Menu::matches('/neon', '/neon/bar/'));
    ok(Menu::matches('/', '/'));
    ok(!Menu::matches('/', '/neon/'));
    eq('/neon/', Menu::linkPath('/neon/'));
    eq(null, Menu::linkPath('#quote'));
    eq(null, Menu::linkPath('https://other.example/neon/'));
    eq('/neon/', Menu::linkPath(Config::siteUrl() . '/neon/'));
    eq(null, Menu::linkPath('tel:+7495'));
});

t('Меню: подсвечивается самая длинная совпавшая ссылка и родитель подпункта', function () {
    $tree = [
        ['id' => 1, 'parent_id' => null, 'url' => '', 'children' => [
            ['id' => 2, 'parent_id' => 1, 'url' => '/neon/', 'children' => []],
            ['id' => 3, 'parent_id' => 1, 'url' => '/neon/bar/', 'children' => []],
        ]],
        ['id' => 4, 'parent_id' => null, 'url' => '/', 'children' => []],
        ['id' => 5, 'parent_id' => null, 'url' => '/neon/', 'children' => []],
    ];
    eq([3, 1], Menu::findActive($tree, '/neon/bar/x/'));
    eq([2, 1], Menu::findActive($tree, '/neon/other/'), 'первый из равных по длине');
    eq([4, 0], Menu::findActive($tree, '/'));
    eq([0, 0], Menu::findActive($tree, '/o-kompanii/'));
});

t('Пагинация: адреса страниц и диапазон', function () {
    $p = new Paginator(73, 36, 2);
    eq(3, $p->pages);
    ok($p->isValid());
    eq(36, $p->offset());
    eq('/neon/', Paginator::url('/neon/', 1));
    eq('/neon/?page=3', Paginator::url('/neon/', 3));
    ok(!(new Paginator(73, 36, 4))->isValid());
    eq(1, (new Paginator(0, 36, 1))->pages);
});

t('Загрузка: безопасное имя файла', function () {
    eq(['foto', 'jpg'], Upload::splitName('foto.jpg'));
    eq(['etcpasswd', 'jpg'], Upload::splitName('../../etc/passwd.jpg'));
    eq(['фото отпуска', 'png'], Upload::splitName('фото отпуска.png'));
    eq(['htaccess', ''], Upload::splitName('.htaccess'));
    eq(['ab', 'jpg'], Upload::splitName("a?#%b.jpg"));
});

/* =====================================================================
   2. Доступ к админке и безопасность
   ===================================================================== */

t('Админка: без авторизации — переход на вход, noindex', function () {
    reset_db();
    $s = site();
    foreach (['/manet', '/manet/', '/manet/products', '/manet/files', '/manet/categories/edit?id=1'] as $p) {
        $r = $s->get($p);
        eq(302, $r->status, $p);
        eq('/manet/login', $r->location(), $p);
    }
    $r = $s->get('/manet/login');
    eq(200, $r->status);
    contains('noindex', $r->header('x-robots-tag'));
    // POST без авторизации ничего не меняет.
    $r = $s->post('/manet/address', ['address' => 'взлом']);
    eq(302, $r->status);
    Settings::reset();
    eq('', Settings::get('address'));
});

t('Админка: неверный пароль, успешный вход, защищённая cookie', function () {
    global $LOGIN, $PASS;
    $s = site();
    $r = $s->login($LOGIN, 'wrong-password');
    eq(200, $r->status);
    contains('Неверный логин или пароль', $r->body);
    $r = $s->login($LOGIN, $PASS);
    eq(303, $r->status);
    $r = $s->get('/manet');
    eq(200, $r->status);
    contains('Панель управления', $r->body);
    Db::query('DELETE FROM login_attempts');
});

t('Админка: cookie сессии HttpOnly и SameSite', function () {
    $s = site();
    $r = $s->get('/manet/login');
    $cookie = $r->header('set-cookie');
    contains('HttpOnly', $cookie);
    contains('SameSite=Lax', $cookie);
    contains('path=/manet', $cookie);
});

t('Админка: действие без CSRF-токена отклоняется', function () {
    $a = admin();
    $r = $a->post('/manet/address', ['address' => 'Без токена']);
    Settings::reset();
    eq('', Settings::get('address'));
    $r = $a->admin('/manet/address', ['address' => 'Москва, ул. Тестовая, 1']);
    eq(303, $r->status);
    Settings::reset();
    eq('Москва, ул. Тестовая, 1', Settings::get('address'));
});

t('Служебные каталоги закрыты, установщик заблокирован после установки', function () {
    $s = site();
    foreach (['/include/connect.php', '/include/schema.sql', '/include/classes/Db.php', '/public/classes/App.php',
              '/public/templates/home.php', '/admin/classes/Auth.php', '/admin/templates/layout.php', '/tests/run.php',
              '/tools/passhash.php', '/.htaccess', '/uploads/.htaccess'] as $p) {
        eq(403, $s->get($p)->status, $p);
    }
    if (is_file(CMS_ROOT . '/start.php')) {
        eq(403, $s->get('/start.php')->status, 'повторный запуск start.php');
        contains('start.php', admin()->get('/manet')->body, 'предупреждение в админке');
    }
});

/* =====================================================================
   3. Общие элементы: адрес, телефон, логотип, меню
   ===================================================================== */

t('Адрес и телефон выводятся в шапке (Schema.org) и кликабельно', function () {
    $a = admin();
    $a->admin('/manet/address', ['address' => 'Москва, ул. <b>Ленина</b>, 1']);
    $a->admin('/manet/phone', ['phone' => '+7 (495) 123-45-67']);
    $html = site()->get('/')->body;
    contains('itemtype="https://schema.org/LocalBusiness"', $html);
    contains('<span itemprop="streetAddress">Москва, ул. Ленина, 1</span>', $html);
    contains('href="tel:+74951234567" class="header__phone">+7 (495) 123-45-67</a>', $html);
    contains('class="address__main">Москва, ул. Ленина, 1<', $html, 'блок «Как нас найти»');
});

t('Логотип: заглушка, проверка типа по содержимому, замена удаляет старый файл', function () {
    $a = admin();
    contains('class="logo">Лого</a>', site()->get('/')->body);
    $r = $a->admin('/manet/logo', ['logo_alt' => 'Компания'], ['logo' => [Fixtures::fakeImage(), 'logo.png']]);
    contains('не является изображением', $r->body);
    eq(0, count(glob(CMS_UPLOADS . '/logo/*.*') ?: []), 'поддельный файл не сохранён');

    $a->admin('/manet/logo', ['logo_alt' => 'Компания'], ['logo' => [Fixtures::image('png'), 'logo.png']]);
    Settings::reset();
    $first = Settings::get('logo_file');
    ok(str_ends_with($first, '.png'));
    ok(is_file(CMS_UPLOADS . '/logo/' . $first));
    $html = site()->get('/')->body;
    contains('alt="Компания"', $html);
    contains('<span class="footer__logo"><img', $html, 'логотип в подвале');

    // WebP, загруженный с расширением .jpg, сохраняется как .webp.
    $a->admin('/manet/logo', ['logo_alt' => 'Компания'], ['logo' => [Fixtures::image('webp'), 'logo.jpg']]);
    Settings::reset();
    $second = Settings::get('logo_file');
    ok(str_ends_with($second, '.webp'), 'расширение по реальному типу');
    ok(gone(CMS_UPLOADS . '/logo/' . $first), 'старый файл удалён');
});

t('Меню: вывод, неактивные пункты скрыты, удаление родителя запрещено', function () {
    $a = admin();
    $a->admin('/manet/menu/edit', ['anchor' => 'Виды вывесок', 'url' => '', 'level' => '1', 'sort' => '1', 'is_active' => '1']);
    $parent = (int) Db::value("SELECT id FROM menu_items WHERE anchor = 'Виды вывесок'");
    $a->admin('/manet/menu/edit', ['anchor' => 'Неон', 'url' => '/neon/', 'level' => '2', 'parent_id' => (string) $parent, 'sort' => '2', 'is_active' => '1']);
    $a->admin('/manet/menu/edit', ['anchor' => 'Буквы', 'url' => '/bukvy/', 'level' => '2', 'parent_id' => (string) $parent, 'sort' => '1', 'is_active' => '1']);
    $a->admin('/manet/menu/edit', ['anchor' => 'Скрытый', 'url' => '/hidden/', 'level' => '1', 'sort' => '5']);
    $a->admin('/manet/menu/edit', ['anchor' => 'О компании', 'url' => '/o-kompanii/', 'level' => '1', 'sort' => '3', 'link_title' => 'Про нас', 'is_active' => '1']);
    // Подпункт без ссылки — ошибка.
    $r = $a->admin('/manet/menu/edit', ['anchor' => 'Без ссылки', 'url' => '', 'level' => '2', 'parent_id' => (string) $parent, 'is_active' => '1']);
    contains('Укажите ссылку', $r->body);

    $html = site()->get('/')->body;
    contains('nav__trigger" aria-expanded="false" aria-controls="submenu-' . $parent . '">Виды вывесок', $html);
    ok(strpos($html, '>Буквы<') < strpos($html, '>Неон<'), 'порядок подпунктов');
    contains('title="Про нас">О компании</a>', $html);
    notContains('Скрытый', $html);

    $r = $a->get('/manet/menu/delete?id=' . $parent);
    eq(303, $r->status, 'пункт с подпунктами — сразу сообщение');
    $a->admin('/manet/menu/delete?id=' . $parent);
    eq(1, (int) Db::value('SELECT COUNT(*) FROM menu_items WHERE id = ?', [$parent]), 'не удалён');

    // Неактивный родитель скрывает подпункты.
    Db::query('UPDATE menu_items SET is_active = 0 WHERE id = ?', [$parent]);
    $html = site()->get('/')->body;
    notContains('>Неон<', $html);
    Db::query('UPDATE menu_items SET is_active = 1 WHERE id = ?', [$parent]);
});

/* =====================================================================
   4. Каталог: адреса, уникальность, редиректы, активность, пагинация
   ===================================================================== */

t('Категории: полный путь подкатегории, 301 на адрес со слешем', function () {
    $a = admin();
    $r = $a->admin('/manet/categories/edit', ['name' => 'Неоновые вывески', 'slug' => 'neonovye-vyveski', 'is_active' => '1', 'text_small' => 'Мал<b>ый</b><script>x</script>']);
    eq(303, $r->status);
    Category::reset();
    $parent = (int) Db::value("SELECT id FROM categories WHERE slug = 'neonovye-vyveski'");
    $a->admin('/manet/categories/edit', ['name' => 'Для бара', 'slug' => 'dlya-bara', 'parent_id' => (string) $parent, 'is_active' => '1']);
    Category::reset();
    $child = (int) Db::value("SELECT id FROM categories WHERE slug = 'dlya-bara'");
    eq('/neonovye-vyveski/dlya-bara/', Urls::pathOf('category', $child));
    eq('Мал<b>ый</b>', Category::find($parent)['text_small']);

    $s = site();
    eq(200, $s->get('/neonovye-vyveski/')->status);
    $r = $s->get('/neonovye-vyveski/dlya-bara/');
    eq(200, $r->status);
    contains('itemtype="https://schema.org/BreadcrumbList"', $r->body);
    contains('<span itemprop="name">Неоновые вывески</span>', $r->body);
    $r = $s->get('/neonovye-vyveski/dlya-bara');
    eq(301, $r->status);
    eq('/neonovye-vyveski/dlya-bara/', $r->location());

    // Третий уровень невозможен.
    $r = $a->admin('/manet/categories/edit', ['name' => 'Третий', 'slug' => 'tretiy', 'parent_id' => (string) $child, 'is_active' => '1']);
    contains('не более двух уровней', $r->body);
});

t('Уникальность адресов: категории, товары, страницы, разделы; зарезервированные адреса', function () {
    $a = admin();
    $r = $a->admin('/manet/products/edit', ['sku' => 'U-1', 'name' => 'Конфликт', 'slug' => 'neonovye-vyveski', 'category_id' => '1', 'is_active' => '1']);
    contains('уже занят категорией', $r->body);
    $r = $a->admin('/manet/pages/edit', ['section_id' => '', 'slug_path' => '/neonovye-vyveski/dlya-bara/', 'name' => 'Стр', 'is_active' => '1']);
    contains('уже занят категорией', $r->body);
    $r = $a->admin('/manet/sections/edit', ['slug' => 'neonovye-vyveski', 'name' => 'Раздел', 'is_active' => '1']);
    contains('уже занят', $r->body);
    $r = $a->admin('/manet/sections/edit', ['slug' => 'manetka', 'name' => 'Раздел', 'is_active' => '1']);
    contains('зарезервирован', $r->body);
    $r = $a->admin('/manet/categories/edit', ['name' => 'Ассеты', 'slug' => 'uploads', 'is_active' => '1']);
    contains('зарезервирован', $r->body);
    $r = $a->admin('/manet/pages/edit', ['section_id' => '', 'slug_path' => 'manet/x', 'name' => 'Стр', 'is_active' => '1']);
    contains('зарезервирован', $r->body);
    $r = $a->admin('/manet/products/edit', ['sku' => 'U-2', 'name' => 'Плохой адрес', 'slug' => 'Плохой адрес', 'category_id' => '1', 'is_active' => '1']);
    contains('только строчные латинские буквы', $r->body);
    eq(0, (int) Db::value("SELECT COUNT(*) FROM products"));
    // Уникальный индекс в БД не даст создать дубль даже в обход проверок.
    $threw = false;
    try {
        Db::query("INSERT INTO routes (path, entity_type, entity_id) VALUES ('/neonovye-vyveski/', 'page', 999)");
    } catch (\PDOException $e) {
        $threw = true;
    }
    ok($threw, 'уникальный индекс routes.path');
});

t('Редиректы 301: смена адреса, каскад на подкатегории, схлопывание цепочек', function () {
    $a = admin();
    $parent = (int) Db::value("SELECT id FROM categories WHERE slug = 'neonovye-vyveski'");
    $edit = static function (string $slug) use ($a, $parent) {
        $r = $a->admin('/manet/categories/edit?id=' . $parent, ['name' => 'Неоновые вывески', 'slug' => $slug, 'is_active' => '1']);
        eq(303, $r->status, 'сохранение ' . $slug);
        Category::reset();
    };
    $edit('neon');
    $s = site();
    $r = $s->get('/neonovye-vyveski/');
    eq(301, $r->status);
    eq('/neon/', $r->location());
    $r = $s->get('/neonovye-vyveski/dlya-bara/');
    eq(301, $r->status, 'каскадный редирект подкатегории');
    eq('/neon/dlya-bara/', $r->location());
    eq(200, $s->get('/neon/dlya-bara/')->status);
    // Параметры запроса сохраняются.
    eq('/neon/?page=2', $s->get('/neonovye-vyveski/?page=2')->location());

    $edit('neon-signs');
    eq('/neon-signs/', $s->get('/neonovye-vyveski/')->location(), 'A→C напрямую');
    eq('/neon-signs/', $s->get('/neon/')->location());
    eq(0, (int) Db::value("SELECT COUNT(*) FROM redirects WHERE new_path IN ('/neon/', '/neon/dlya-bara/')"), 'нет цепочек');

    $edit('neon'); // возврат к прежнему адресу
    eq(200, $s->get('/neon/')->status, 'редирект с живого адреса удалён');
    eq('/neon/', $s->get('/neon-signs/')->location());
    eq('/neon/', $s->get('/neonovye-vyveski/')->location());
    eq(0, (int) Db::value("SELECT COUNT(*) FROM redirects WHERE old_path = new_path"));
});

t('Товары: короткий адрес, цены, свойства категории с наследованием', function () {
    $a = admin();
    Category::reset();
    $parent = (int) Db::value("SELECT id FROM categories WHERE slug = 'neon'");
    $child = (int) Db::value("SELECT id FROM categories WHERE slug = 'dlya-bara'");
    $a->admin('/manet/categories/prop-edit?category_id=' . $parent, ['name' => 'Цвет', 'type' => 'list', 'options' => "Красный\nСиний", 'sort' => '1']);
    $a->admin('/manet/categories/prop-edit?category_id=' . $child, ['name' => 'Длина, см', 'type' => 'number', 'sort' => '2']);
    $a->admin('/manet/categories/prop-edit?category_id=' . $child, ['name' => 'Пульт', 'type' => 'bool', 'sort' => '3']);

    $r = $a->admin('/manet/products/edit', [
        'sku' => 'N-1', 'name' => 'Неон «Бар»', 'slug' => '', 'category_id' => (string) $child,
        'price' => '1250,50', 'price_old' => '1500', 'text_small' => '<i>Ярко</i><img src=x onerror=1>', 'text_big' => '<p>Описание <script>ok()</script></p>', 'is_active' => '1',
    ], ['image' => [Fixtures::image('jpg'), 'main.jpg']]);
    eq(303, $r->status);
    $p = Product::findBySku('N-1');
    eq('/neon-bar/', $p['path'], 'адрес из названия');
    eq('1250.50', (string) $p['price']);
    eq('<i>Ярко</i>', $p['text_small']);

    Category::reset();
    $props = Category::properties($child);
    eq(['Цвет', 'Длина, см', 'Пульт'], array_column($props, 'name'), 'свойства родителя + свои');
    $red = (string) $props[0]['options'][0]['id'];
    $a->admin('/manet/products/edit?id=' . $p['id'], [
        'sku' => 'N-1', 'name' => 'Неон «Бар»', 'slug' => 'neon-bar', 'category_id' => (string) $child, 'price' => '1250,50', 'price_old' => '1500',
        'text_small' => '<i>Ярко</i>', 'text_big' => '<p>Описание <script>ok()</script></p>', 'is_active' => '1',
        'props' => [$props[0]['id'] => $red, $props[1]['id'] => '120,5', $props[2]['id'] => '1'],
    ]);
    $html = site()->get('/neon-bar/')->body;
    contains("1\u{00A0}251\u{00A0}₽", $html, 'цена округлена');
    contains("<s>1\u{00A0}500\u{00A0}₽</s>", $html, 'старая цена');
    contains('<th scope="row">Цвет</th><td>Красный</td>', $html);
    contains('<th scope="row">Длина, см</th><td>120,5</td>', $html);
    contains('<th scope="row">Пульт</th><td>Да</td>', $html);
    contains('<p>Описание <script>ok()</script></p>', $html, 'большой текст без фильтрации');
    contains('арт. N-1', $html);
    contains('<meta property="og:image" content="' . Config::siteUrl() . '/uploads/products/', $html);
    contains('id="order-dialog"', $html, 'всплывающая форма заказа');
    notContains('Добавить в корзину', $html);
    // Меню: на карточке товара подсвечивается пункт категории.
    contains('class="submenu__link nav__link--active"', $html);
    contains('nav__link nav__trigger nav__link--active', $html, 'родитель подпункта');
});

t('Товары: перенос в другую категорию очищает свойства; удаление удаляет фото', function () {
    $a = admin();
    $p = Product::findBySku('N-1');
    eq(3, count(Product::propertyValues((int) $p['id'])));
    $a->admin('/manet/products/image-add?id=' . $p['id'], ['alt' => 'Доп', 'sort' => '1'], ['photo' => [Fixtures::image('gif'), 'x.gif']]);
    $images = Product::images((int) $p['id']);
    eq(1, count($images));
    $html = site()->get('/neon-bar/')->body;
    contains('data-alt="Доп"', $html);

    $a->admin('/manet/products/edit?id=' . $p['id'], ['sku' => 'N-1', 'name' => 'Неон «Бар»', 'slug' => 'neon-bar', 'category_id' => '1', 'is_active' => '1']);
    eq(0, count(Product::propertyValues((int) $p['id'])), 'свойства прежней категории удалены');
    eq('/neon-bar/', Urls::pathOf('product', (int) $p['id']), 'адрес не изменился');

    $files = [CMS_UPLOADS . '/products/' . Product::find((int) $p['id'])['image'], CMS_UPLOADS . '/products/' . $images[0]['file']];
    foreach ($files as $f) {
        ok(is_file($f));
    }
    $r = $a->get('/manet/products/delete?id=' . $p['id']);
    contains('Да, удалить', $r->body, 'запрос подтверждения');
    $a->admin('/manet/products/delete?id=' . $p['id']);
    eq(null, Product::find((int) $p['id']));
    foreach ($files as $f) {
        ok(gone($f), 'файл удалён: ' . basename($f));
    }
    eq(404, site()->get('/neon-bar/')->status, 'адрес удалённого товара — 404');
});

t('Свойство: удаление с предупреждением о количестве товаров', function () {
    $a = admin();
    Category::reset();
    $child = (int) Db::value("SELECT id FROM categories WHERE slug = 'dlya-bara'");
    $pid = mkprod('P-1', 'p-1', $child);
    $prop = (int) Db::value("SELECT id FROM category_properties WHERE name = 'Длина, см'");
    Product::saveProperties($pid, [$prop => '10']);
    $r = $a->get('/manet/categories/prop-delete?category_id=' . $child . '&id=' . $prop);
    contains('у товаров: 1 шт.', $r->body);
    $a->admin('/manet/categories/prop-delete?category_id=' . $child . '&id=' . $prop);
    eq(0, (int) Db::value('SELECT COUNT(*) FROM product_property_values WHERE property_id = ?', [$prop]));
    Product::delete($pid);
});

t('Страница категории: товары подкатегорий, новые первыми, 36 на страницу, canonical, 404 вне диапазона', function () {
    Category::reset();
    $parent = (int) Db::value("SELECT id FROM categories WHERE slug = 'neon'");
    $child = (int) Db::value("SELECT id FROM categories WHERE slug = 'dlya-bara'");
    for ($i = 1; $i <= 37; $i++) {
        $id = mkprod(sprintf('S-%02d', $i), sprintf('tovar-%02d', $i), $i % 2 ? $parent : $child);
        Db::query('UPDATE products SET created_at = ? WHERE id = ?', [date('Y-m-d H:i:s', time() - 1000 + $i), $id]);
    }
    eq(37, Category::productCount($parent), 'количество с учётом подкатегорий');
    $s = site();
    $r = $s->get('/neon/');
    eq(200, $r->status);
    ok(strpos($r->body, 'Товар S-37') < strpos($r->body, 'Товар S-36'), 'новые в начале');
    notContains('Товар S-01<', $r->body, 'старейший товар — на второй странице');
    contains('<link rel="canonical" href="' . Config::siteUrl() . '/neon/">', $r->body);
    contains('href="/neon/?page=2"', $r->body);
    contains('id="subcategories"', $r->body, 'блок подкатегорий');

    $r = $s->get('/neon/?page=2');
    eq(200, $r->status);
    contains('Товар S-01<', $r->body);
    contains('<link rel="canonical" href="' . Config::siteUrl() . '/neon/?page=2">', $r->body);
    eq(404, $s->get('/neon/?page=3')->status);
    eq(404, $s->get('/neon/?page=0')->status);
    eq(404, $s->get('/neon/?page=abc')->status);
    eq(404, $s->get('/neon/?page=02')->status);
    $r = $s->get('/neon/?page=1');
    eq(301, $r->status);
    eq('/neon/', $r->location());
    // В подкатегории — только её товары (18 шт.), одна страница.
    eq(404, $s->get('/neon/dlya-bara/?page=2')->status);
});

t('Активность: неактивная категория скрывает подкатегории и товары (404, sitemap)', function () {
    $s = site();
    Category::reset();
    $parent = (int) Db::value("SELECT id FROM categories WHERE slug = 'neon'");
    Db::query('UPDATE categories SET is_active = 0 WHERE id = ?', [$parent]);
    eq(404, $s->get('/neon/')->status);
    eq(404, $s->get('/neon/dlya-bara/')->status);
    eq(404, $s->get('/tovar-01/')->status);
    eq(404, $s->get('/tovar-02/')->status, 'товар подкатегории');
    $map = $s->get('/sitemap.xml')->body;
    notContains('/neon/', $map);
    notContains('/tovar-01/', $map);
    Db::query('UPDATE categories SET is_active = 1 WHERE id = ?', [$parent]);
    $map = $s->get('/sitemap.xml')->body;
    contains('<loc>' . Config::siteUrl() . '/neon/dlya-bara/</loc><lastmod>', $map);
    contains('/tovar-01/</loc>', $map);
    Db::query("UPDATE products SET is_active = 0 WHERE sku = 'S-01'");
    eq(404, $s->get('/tovar-01/')->status, 'неактивный товар');
});

t('Удаление категорий: запреты и «Без категории»', function () {
    $a = admin();
    Category::reset();
    $parent = (int) Db::value("SELECT id FROM categories WHERE slug = 'neon'");
    $child = (int) Db::value("SELECT id FROM categories WHERE slug = 'dlya-bara'");
    $system = (int) Category::system()['id'];
    $a->admin('/manet/categories/delete?id=' . $parent);
    $a->admin('/manet/categories/delete?id=' . $child);
    $a->admin('/manet/categories/delete?id=' . $system);
    Category::reset();
    ok(Category::find($parent) && Category::find($child) && Category::find($system), 'ничего не удалено');
    // «Без категории»: название не меняется, URL меняется.
    $a->admin('/manet/categories/edit?id=' . $system, ['name' => 'Другое имя', 'slug' => 'raznoe', 'is_active' => '1']);
    Category::reset();
    eq('Без категории', Category::find($system)['name']);
    eq('/raznoe/', Category::find($system)['path']);
    // Пустую категорию удалить можно; её адрес — 404 без редиректа.
    $empty = mkcat('Пустая', 'pustaya');
    $a->admin('/manet/categories/delete?id=' . $empty);
    Category::reset();
    eq(null, Category::find($empty));
    eq(404, site()->get('/pustaya/')->status);
});

/* =====================================================================
   5. Текстовые страницы и разделы
   ===================================================================== */

t('Страницы: произвольный адрес, разделы, лента, оглавление, крошки', function () {
    $a = admin();
    $a->admin('/manet/pages/edit', ['section_id' => '', 'slug_path' => 'o-kompanii', 'name' => 'О компании', 'text_big' => '<h2>История</h2><p>Текст</p><h2 id="team">Команда</h2>', 'is_active' => '1']);
    $a->admin('/manet/pages/edit', ['section_id' => '', 'slug_path' => '/o-kompanii/komanda/', 'name' => 'Команда', 'is_active' => '1']);
    $a->admin('/manet/sections/edit', ['slug' => 'blog', 'name' => 'Блог', 'text_big' => '<p>О блоге</p>', 'is_active' => '1']);
    $sec = (int) Db::value("SELECT id FROM sections WHERE slug = 'blog'");
    $a->admin('/manet/pages/edit', ['section_id' => (string) $sec, 'slug_segment' => 'statya', 'name' => 'Статья 1', 'published_at' => '2026-01-10', 'text_small' => 'Анонс <b>1</b>', 'is_active' => '1']);
    $a->admin('/manet/pages/edit', ['section_id' => (string) $sec, 'slug_segment' => 'statya-2', 'name' => 'Статья 2', 'published_at' => '2026-03-01', 'is_active' => '1']);
    $a->admin('/manet/pages/edit', ['section_id' => (string) $sec, 'slug_segment' => 'chernovik', 'name' => 'Черновик']);

    $s = site();
    $r = $s->get('/o-kompanii/');
    eq(200, $r->status);
    contains('<a href="#s1">История</a>', $r->body);
    contains('<a href="#team">Команда</a>', $r->body);
    contains('<h2 id="s1">История</h2>', $r->body);
    $r = $s->get('/o-kompanii/komanda/');
    eq(200, $r->status);
    contains('<span itemprop="name">О компании</span>', $r->body, 'крошки по адресам-предкам');
    notContains('class="toc"', $r->body, 'без h2 — без оглавления');

    $r = $s->get('/blog/statya/');
    eq(200, $r->status);
    contains('10 января 2026', $r->body);
    $r = $s->get('/blog/');
    eq(200, $r->status);
    ok(strpos($r->body, 'Статья 2') < strpos($r->body, 'Статья 1'), 'новые первыми');
    contains('Анонс <b>1</b>', $r->body);
    notContains('Черновик', $r->body);
    eq(404, $s->get('/blog/chernovik/')->status);
    eq(404, $s->get('/blog/?page=2')->status);

    // Смена адреса раздела — каскадные редиректы страниц.
    $a->admin('/manet/sections/edit?id=' . $sec, ['slug' => 'stati', 'name' => 'Блог', 'is_active' => '1']);
    eq('/stati/statya/', $s->get('/blog/statya/')->location());
    // Удаление раздела со страницами запрещено.
    $a->admin('/manet/sections/delete?id=' . $sec);
    ok(Section::find($sec) !== null);
    // Неактивный раздел скрывает страницы.
    Db::query('UPDATE sections SET is_active = 0 WHERE id = ?', [$sec]);
    eq(404, $s->get('/stati/statya/')->status);
    eq(404, $s->get('/stati/')->status);
    Db::query('UPDATE sections SET is_active = 1 WHERE id = ?', [$sec]);
    contains('/stati/statya/</loc>', $s->get('/sitemap.xml')->body);
});

t('Лента раздела: 15 на страницу, сортировка по дате создания без даты публикации', function () {
    $sec = (int) Db::value("SELECT id FROM sections WHERE slug = 'stati'");
    for ($i = 1; $i <= 15; $i++) {
        $r = Page::save(['section_id' => (string) $sec, 'slug' => 'n-' . $i, 'name' => 'Новость ' . $i, 'is_active' => '1']);
        Db::query('UPDATE pages SET created_at = ? WHERE id = ?', [date('Y-m-d H:i:s', time() - 100 + $i), $r['id']]);
    }
    $s = site();
    $p1 = $s->get('/stati/')->body;
    ok(strpos($p1, 'Новость 15<') < strpos($p1, 'Новость 14<'));
    notContains('Статья 1<', $p1, 'старые статьи на второй странице');
    $p2 = $s->get('/stati/?page=2');
    eq(200, $p2->status);
    contains('Статья 1<', $p2->body);
});

/* =====================================================================
   6. Слайдер
   ===================================================================== */

t('Слайдер: один h1, кнопки по заполненности, нельзя убрать последний активный', function () {
    $a = admin();
    $only = (int) Db::value('SELECT id FROM slides');
    $html = site()->get('/')->body;
    notContains('slider__controls', $html, 'один слайд — без стрелок и точек');
    eq(1, substr_count($html, '<h1'));

    $r = $a->admin('/manet/slides/edit?id=' . $only, ['title' => 'Слайд', 'sort' => '1']);
    contains('последний активный слайд', $r->body);
    $a->admin('/manet/slides/delete?id=' . $only);
    eq(1, Slide::activeCount());

    $r = $a->admin('/manet/slides/edit', ['title' => 'Второй <b>слайд</b>', 'btn1_text' => 'Кнопка', 'btn1_url' => '', 'btn2_text' => 'Вторая', 'btn2_url' => '/x/', 'sort' => '0', 'is_active' => '1'],
        ['image' => [Fixtures::image('png'), 's.png']]);
    eq(303, $r->status);
    $r = $a->admin('/manet/slides/edit', ['title' => 'GIF', 'sort' => '5', 'is_active' => '1'], ['image' => [Fixtures::image('gif'), 's.gif']]);
    contains('JPG, PNG, WebP', $r->body, 'GIF для слайдов недопустим');
    $a->admin('/manet/slides/settings', ['slider_autoplay' => '1', 'slider_interval' => '5']);

    $html = site()->get('/')->body;
    eq(1, substr_count($html, '<h1'), 'ровно один h1');
    contains('<h1 class="hero__title">Второй слайд</h1>', $html, 'h1 у первого по порядку');
    contains('<h2 class="hero__title">Вывески', $html);
    contains('slider__controls', $html);
    contains('01 / 02', $html);
    contains('data-autoplay="5000"', $html);
    notContains('>Кнопка<', $html, 'кнопка без ссылки не выводится');
    contains('>Вторая<', $html);

    $second = (int) Db::value("SELECT id FROM slides WHERE title = 'Второй слайд'");
    $file = CMS_UPLOADS . '/slides/' . Slide::find($second)['image'];
    ok(is_file($file));
    $a->admin('/manet/slides/edit?id=' . $second, ['title' => 'Второй слайд', 'sort' => '0', 'is_active' => '1'], ['image' => [Fixtures::image('webp'), 's.webp']]);
    ok(gone($file), 'старое фото удалено при замене');
    $file = CMS_UPLOADS . '/slides/' . Slide::find($second)['image'];
    $a->admin('/manet/slides/delete?id=' . $second);
    ok(Slide::find($second) === null && gone($file), 'слайд и фото удалены');
    $a->admin('/manet/slides/settings', ['slider_interval' => '7']);
    contains('data-autoplay="0"', site()->get('/')->body, 'автопрокрутка выключена');
});

/* =====================================================================
   7. Формы заявок
   ===================================================================== */

t('Заявка: отправка письма, страница «Ваше сообщение отправлено», возврат через 3 с', function () {
    global $MAILPIT;
    Db::query('DELETE FROM form_submissions_log');
    clear_mails();
    $r = site()->post('/send/', ['form' => 'quote', 'back' => '/o-kompanii/', 'name' => 'Иван', 'phone' => '+7 (999) 123-45-67', 'type' => 'Световой короб', 'website' => '']);
    eq(200, $r->status);
    contains('Ваше сообщение отправлено', $r->body);
    contains('<meta http-equiv="refresh" content="3;url=/o-kompanii/">', $r->body);
    if ($MAILPIT !== '') {
        usleep(300000);
        $m = mails();
        eq(1, count($m), 'одно письмо');
        eq('Новая заявка с сайта', $m[0]['Subject']);
        $full = json_decode((string) file_get_contents($MAILPIT . '/api/v1/message/' . $m[0]['ID']), true);
        contains('Имя: Иван', $full['Text']);
        contains('Телефон: +7 (999) 123-45-67', $full['Text']);
        contains('Что нужно изготовить: Световой короб', $full['Text']);
        contains('Страница отправки: ' . Config::siteUrl() . '/o-kompanii/', $full['Text']);
        eq((string) Config::get('mail_to'), $m[0]['To'][0]['Address']);
    }
});

t('Заявка «Заказать» с карточки товара: данные товара в письме', function () {
    global $MAILPIT;
    Db::query('DELETE FROM form_submissions_log');
    clear_mails();
    $p = Product::findBySku('S-02');
    $r = site()->post('/send/', ['form' => 'order', 'product_id' => (string) $p['id'], 'back' => $p['path'], 'name' => 'Анна', 'phone' => '89991234567', 'comment' => 'Нужно к пятнице']);
    contains('Ваше сообщение отправлено', $r->body);
    if ($MAILPIT !== '') {
        usleep(300000);
        $m = mails();
        $full = json_decode((string) file_get_contents($MAILPIT . '/api/v1/message/' . $m[0]['ID']), true);
        contains('Товар: Товар S-02', $full['Text']);
        contains('Артикул: S-02', $full['Text']);
        contains('Комментарий: Нужно к пятнице', $full['Text']);
        contains('Ссылка на товар: ' . Config::siteUrl() . '/tovar-02/', $full['Text']);
    }
});

t('Заявка: ловушка, проверка данных, защита возврата, ограничение частоты', function () {
    global $MAILPIT;
    Db::query('DELETE FROM form_submissions_log');
    clear_mails();
    $s = site();
    $r = $s->post('/send/', ['form' => 'quote', 'back' => '/', 'name' => 'Бот', 'phone' => '+79991234567', 'website' => 'http://spam']);
    contains('Ваше сообщение отправлено', $r->body, 'бот не узнаёт об отказе');
    $r = $s->post('/send/', ['form' => 'quote', 'back' => '/', 'name' => 'И', 'phone' => '123']);
    eq(422, $r->status);
    contains('Введите имя', $r->body);
    contains('Введите телефон полностью', $r->body);
    notContains('http-equiv="refresh"', $r->body);
    $r = $s->post('/send/', ['form' => 'quote', 'back' => 'https://evil.example/', 'name' => 'Иван', 'phone' => '+79991234567']);
    contains('content="3;url=/"', $r->body, 'чужой адрес возврата заменён на /');
    $r = $s->post('/send/', ['form' => 'quote', 'back' => '//evil.example/', 'name' => 'Иван', 'phone' => '+79991234567']);
    contains('content="3;url=/"', $r->body);
    eq(404, $s->get('/send/')->status, 'GET — 404');
    for ($i = 0; $i < 3; $i++) {
        $s->post('/send/', ['form' => 'quote', 'back' => '/', 'name' => 'Иван', 'phone' => '+79991234567']);
    }
    $r = $s->post('/send/', ['form' => 'quote', 'back' => '/', 'name' => 'Иван', 'phone' => '+79991234567']);
    eq(429, $r->status, 'шестая заявка за 10 минут');
    if ($MAILPIT !== '') {
        usleep(300000);
        eq(5, count(mails()), 'ловушка и ошибки писем не отправили');
    }
    Db::query('DELETE FROM form_submissions_log');
});

/* =====================================================================
   8. Раздел «Файлы»
   ===================================================================== */

t('Файлы: имя сохраняется, номер при совпадении, тип по содержимому', function () {
    $a = admin();
    $a->admin('/manet/files', [], ['files[0]' => [Fixtures::image('jpg'), 'foto.jpg']]);
    $a->admin('/manet/files', [], ['files[0]' => [Fixtures::image('jpg'), 'foto.jpg']]);
    $a->admin('/manet/files', [], ['files[0]' => [Fixtures::image('png'), 'shell.php']]);
    $a->admin('/manet/files', [], ['files[0]' => [Fixtures::image('png'), 'Фото склада.jpeg']]);
    $r = $a->admin('/manet/files', [], ['files[0]' => [Fixtures::fakeImage(), 'evil.jpg']]);
    $names = Db::column('SELECT filename FROM files ORDER BY id');
    eq(['foto.jpg', 'foto-2.jpg', 'shell.png', 'Фото склада.png'], $names);
    foreach ($names as $n) {
        ok(is_file(CMS_UPLOADS . '/files/' . $n), $n);
    }
    ok(gone(CMS_UPLOADS . '/files/evil.jpg'));
    $list = $a->get('/manet/files?q=' . rawurlencode('склада'))->body;
    contains('/uploads/files/' . rawurlencode('Фото склада.png'), $list);
    contains('копировать адрес', $list);
    eq(200, site()->get('/uploads/files/' . rawurlencode('Фото склада.png'))->status);
    $id = (int) Db::value("SELECT id FROM files WHERE filename = 'foto.jpg'");
    $a->admin('/manet/files/delete?id=' . $id);
    ok(gone(CMS_UPLOADS . '/files/foto.jpg'));
});

t('Загрузки: PHP в папке uploads не выполняется', function () {
    file_put_contents(CMS_UPLOADS . '/files/x.php', '<?php echo "EXECUTED";');
    $r = site()->get('/uploads/files/x.php');
    @unlink(CMS_UPLOADS . '/files/x.php');
    notContains('EXECUTED', $r->body);
    ok($r->status === 403 || $r->status === 404, 'код ' . $r->status);
});

/* =====================================================================
   9. Импорт CSV
   ===================================================================== */

t('Импорт CSV: добавление, обновление без стирания, пропуск ошибочных строк', function () {
    global $BASE;
    $a = admin();
    $csv = "\xEF\xBB\xBFартикул;URL;название;категория;title;description;og:title;og:description;малый текст;большой текст;старая цена;новая цена;активность;ссылка на основное фото\n"
        . "C-1;csv-1;CSV товар 1;/neon/;T1;D1;;;<b>мал</b>;<p>big</p>;;1250,50;1;\n"
        . "C-2;;CSV товар 2;;;;;;;;;;0;\n"
        . "S-03;;;;Новый title;;;;;;;;;\n"
        . "C-3;csv-3;Неизвестная;/net-takoy/;;;;;;;;;;\n"
        . "C-4;tovar-05;Занятый URL;;;;;;;;;;;\n"
        . "C-5;csv-5;Плохая активность;;;;;;;;;;да;\n"
        . "C-6;csv-6;Плохая цена;;;;;;;;;12 руб;1;\n"
        . ";csv-7;Без артикула;;;;;;;;;;;\n"
        . "C-8;;;;;;;;;;;;;\n"
        . "C-9;csv-9;С фото;;;;;;;;;100;1;" . $BASE . "/uploads/files/foto-2.jpg\n"
        . "C-10;csv-10;Битое фото;;;;;;;;;100;1;" . $BASE . "/assets/styles.css\n";
    $file = sys_get_temp_dir() . '/import.csv';
    file_put_contents($file, $csv);
    $before = Product::findBySku('S-03');
    $r = $a->admin('/manet/products/import', [], ['csv' => [$file, 'import.csv']]);
    $html = $r->body;
    contains('Добавлено товаров: <strong>4</strong>', $html);
    contains('Обновлено: <strong>1</strong>', $html);
    contains('Пропущено строк: <strong>6</strong>', $html);
    contains('неизвестная категория', $html);
    contains('уже занят товаром', $html);
    contains('активность должна быть 1 или 0', $html);
    contains('Новая цена', $html);
    contains('не указан артикул', $html);
    contains('пустое название', $html);
    contains('фото не загружено', $html);

    $c1 = Product::findBySku('C-1');
    eq('/csv-1/', $c1['path']);
    eq((int) Db::value("SELECT id FROM categories WHERE slug = 'neon'"), (int) $c1['category_id']);
    eq('<b>мал</b>', $c1['text_small']);
    eq('1250.50', (string) $c1['price']);
    $c2 = Product::findBySku('C-2');
    eq((int) Category::system()['id'], (int) $c2['category_id'], 'без категории');
    eq('/csv-tovar-2/', $c2['path']);
    eq(0, (int) $c2['is_active']);
    $after = Product::findBySku('S-03');
    eq('Новый title', $after['meta_title']);
    eq($before['name'], $after['name'], 'пустая ячейка не стёрла название');
    eq($before['path'], $after['path']);
    $c9 = Product::findBySku('C-9');
    ok($c9['image'] && is_file(CMS_UPLOADS . '/products/' . $c9['image']), 'фото скачано');

    // Смена URL через импорт — редирект 301.
    file_put_contents($file, "артикул;URL;название\nC-1;csv-1-new;\n");
    $a->admin('/manet/products/import', [], ['csv' => [$file, 'import.csv']]);
    eq('/csv-1-new/', site()->get('/csv-1/')->location());
});

/* =====================================================================
   10. Блокировка входа (в конце: блокирует IP тестов)
   ===================================================================== */

t('Вход: после 5 неверных попыток блокировка на 15 минут', function () {
    global $LOGIN, $PASS;
    Db::query('DELETE FROM login_attempts');
    $s = site();
    for ($i = 1; $i <= 4; $i++) {
        contains('Осталось попыток: ' . (5 - $i), $s->login($LOGIN, 'bad')->body);
    }
    contains('заблокирован на 15 минут', $s->login($LOGIN, 'bad')->body);
    $r = $s->login($LOGIN, $PASS);
    eq(200, $r->status, 'верный пароль во время блокировки не пускает');
    contains('попробуйте через 15 мин', $r->body);
    Db::query('UPDATE login_attempts SET locked_until = ?', [date('Y-m-d H:i:s', time() - 1)]);
    eq(303, $s->login($LOGIN, $PASS)->status, 'после окончания блокировки вход возможен');
    Db::query('DELETE FROM login_attempts');
});

/* =====================================================================
   Запуск
   ===================================================================== */

$filter = (string) getenv('CMS_TEST_FILTER');
$failed = 0;
$passed = 0;
$start = microtime(true);
foreach (T::$tests as [$name, $fn]) {
    if ($filter !== '' && !str_contains($name, $filter)) {
        continue;
    }
    try {
        $fn();
        $passed++;
        echo "  \033[32m✓\033[0m $name\n";
    } catch (\Throwable $e) {
        $failed++;
        echo "  \033[31m✗ $name\033[0m\n      " . $e->getMessage()
            . ($e instanceof TestFailure ? '' : ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')') . "\n";
        if ($e instanceof TestFailure) {
            foreach ($e->getTrace() as $fr) {
                if (($fr['file'] ?? '') === __FILE__) {
                    echo '      строка ' . $fr['line'] . "\n";
                    break;
                }
            }
        }
    }
}
printf("\nТестов: %d, успешно: %d, ошибок: %d, проверок: %d, время: %.1f с\n", $passed + $failed, $passed, $failed, T::$asserts, microtime(true) - $start);
exit($failed ? 1 : 0);
