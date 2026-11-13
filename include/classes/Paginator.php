<?php
/**
 * Постраничная навигация.
 *
 * Страницы открываются через ?page=N, первая — по основному адресу
 * без параметра (п. 5.1). Номер за пределами диапазона — 404.
 */

declare(strict_types=1);

namespace Core;

final class Paginator
{
    public int $page;
    public int $perPage;
    public int $total;
    public int $pages;

    public function __construct(int $total, int $perPage, int $page)
    {
        $this->total = $total;
        $this->perPage = $perPage;
        $this->pages = max(1, (int) ceil($total / $perPage));
        $this->page = $page;
    }

    /** Номер страницы в допустимом диапазоне? */
    public function isValid(): bool
    {
        return $this->page >= 1 && $this->page <= $this->pages;
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }

    /**
     * Адрес страницы N: первая — без параметра; остальные параметры
     * запроса ($query) сохраняются.
     */
    public static function url(string $base, int $n, array $query = []): string
    {
        unset($query['page']);
        if ($n > 1) {
            $query['page'] = $n;
        }
        return $base . ($query ? '?' . http_build_query($query) : '');
    }

    /**
     * Номера страниц для вывода (с пропусками «…»): 1 … 4 5 6 … 20.
     * Пропуск обозначается значением 0.
     */
    public function numbers(int $around = 2): array
    {
        $out = [];
        $last = 0;
        for ($i = 1; $i <= $this->pages; $i++) {
            if ($i === 1 || $i === $this->pages || abs($i - $this->page) <= $around) {
                if ($last && $i - $last > 1) {
                    $out[] = 0;
                }
                $out[] = $i;
                $last = $i;
            }
        }
        return $out;
    }
}
