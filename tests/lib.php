<?php
/**
 * Минимальный набор средств для автоматических тестов CMS (без PHPUnit,
 * чтобы не тянуть зависимости на хостинг).
 *
 *  - t($name, fn)       — объявить тест;
 *  - ok/eq/contains     — проверки;
 *  - Http               — HTTP-клиент с cookie (для проверки сайта и админки);
 *  - Fixtures           — тестовые изображения.
 */

declare(strict_types=1);

final class TestFailure extends \RuntimeException
{
}

final class T
{
    public static array $tests = [];
    public static int $asserts = 0;
}

function t(string $name, callable $fn): void
{
    T::$tests[] = [$name, $fn];
}

function ok($cond, string $msg = 'условие не выполнено'): void
{
    T::$asserts++;
    if (!$cond) {
        throw new TestFailure($msg);
    }
}

function eq($expected, $actual, string $msg = ''): void
{
    T::$asserts++;
    if ($expected !== $actual) {
        throw new TestFailure(($msg !== '' ? $msg . ': ' : '') . 'ожидалось ' . var_export($expected, true) . ', получено ' . var_export($actual, true));
    }
}

function contains(string $needle, string $haystack, string $msg = ''): void
{
    T::$asserts++;
    if (!str_contains($haystack, $needle)) {
        throw new TestFailure(($msg !== '' ? $msg . ': ' : '') . 'не найдено «' . $needle . '»');
    }
}

function notContains(string $needle, string $haystack, string $msg = ''): void
{
    T::$asserts++;
    if (str_contains($haystack, $needle)) {
        throw new TestFailure(($msg !== '' ? $msg . ': ' : '') . 'найдено лишнее «' . $needle . '»');
    }
}

/** Ответ HTTP. */
final class Res
{
    public int $status = 0;
    public array $headers = [];
    public string $body = '';

    public function header(string $name): string
    {
        return $this->headers[strtolower($name)] ?? '';
    }

    /** Путь из заголовка Location (без домена). */
    public function location(): string
    {
        $loc = $this->header('location');
        $p = parse_url($loc);
        return ($p['path'] ?? '') . (isset($p['query']) ? '?' . $p['query'] : '');
    }
}

/** HTTP-клиент с собственным набором cookie. */
final class Http
{
    private string $jar;
    public string $base;
    private ?string $token = null;

    public function __construct(string $base)
    {
        $this->base = rtrim($base, '/');
        $this->jar = tempnam(sys_get_temp_dir(), 'jar');
    }

    public function get(string $path): Res
    {
        return $this->request('GET', $path);
    }

    /**
     * @param array $fields Поля формы
     * @param array $files  [поле => [путь к файлу, имя файла]]
     */
    public function post(string $path, array $fields = [], array $files = []): Res
    {
        foreach ($files as $name => [$file, $filename]) {
            $fields[$name] = new CURLFile($file, '', $filename);
        }
        return $this->request('POST', $path, $fields, (bool) $files);
    }

    private function request(string $method, string $path, array $fields = [], bool $multipart = false): Res
    {
        $ch = curl_init($this->base . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_COOKIEJAR      => $this->jar,
            CURLOPT_COOKIEFILE     => $this->jar,
            CURLOPT_TIMEOUT        => 60,
        ]);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $multipart ? self::flatten($fields) : http_build_query($fields));
        }
        $raw = (string) curl_exec($ch);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $res = new Res();
        $res->status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        foreach (explode("\r\n", substr($raw, 0, $headerSize)) as $line) {
            if (str_contains($line, ':')) {
                [$k, $v] = explode(':', $line, 2);
                $res->headers[strtolower(trim($k))] = trim($v);
            }
        }
        $res->body = substr($raw, $headerSize);
        return $res;
    }

    /** Вложенные массивы полей → плоские имена (для multipart). */
    private static function flatten(array $fields, string $prefix = ''): array
    {
        $out = [];
        foreach ($fields as $k => $v) {
            $key = $prefix === '' ? (string) $k : $prefix . '[' . $k . ']';
            if (is_array($v)) {
                $out += self::flatten($v, $key);
            } else {
                $out[$key] = $v;
            }
        }
        return $out;
    }

    /** Вход в админку. */
    public function login(string $login, string $password): Res
    {
        $page = $this->get('/manet/login');
        return $this->post('/manet/login', ['_token' => self::extractToken($page->body), 'login' => $login, 'password' => $password]);
    }

    /** CSRF-токен текущей сессии админки. */
    public function token(): string
    {
        if ($this->token === null) {
            $this->token = self::extractToken($this->get('/manet')->body);
        }
        return $this->token;
    }

    /** POST в админку с CSRF-токеном. */
    public function admin(string $path, array $fields = [], array $files = []): Res
    {
        return $this->post($path, ['_token' => $this->token()] + $fields, $files);
    }

    public static function extractToken(string $html): string
    {
        return preg_match('/name="_token" value="([^"]+)"/', $html, $m) ? $m[1] : '';
    }
}

/** Тестовые изображения 1×1 и поддельные файлы. */
final class Fixtures
{
    private const IMAGES = [
        'png'  => 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==',
        'gif'  => 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7',
        'webp' => 'UklGRhoAAABXRUJQVlA4TA0AAAAvAAAAEAcQERGIiP4HAA==',
        'jpg'  => '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=',
    ];

    public static function image(string $type): string
    {
        $file = sys_get_temp_dir() . '/cms-test-' . $type . '-' . bin2hex(random_bytes(4)) . '.' . $type;
        file_put_contents($file, base64_decode(self::IMAGES[$type]));
        return $file;
    }

    /** PHP-скрипт, замаскированный под картинку. */
    public static function fakeImage(): string
    {
        $file = sys_get_temp_dir() . '/cms-test-fake-' . bin2hex(random_bytes(4)) . '.png';
        file_put_contents($file, "<?php echo 'hacked'; ?>");
        return $file;
    }
}
