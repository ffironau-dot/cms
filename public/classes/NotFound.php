<?php
/**
 * Исключение «страница не найдена» — приводит к ответу 404.
 */

declare(strict_types=1);

namespace Site;

final class NotFound extends \RuntimeException
{
}
