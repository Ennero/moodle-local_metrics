<?php
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Config de varias ventanas temporales (lista de días) para `inactive_users`.
 *
 * @package    local_metrics
 * @copyright  2026 UEDi - Facultad de Ingeniería USAC
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_metrics\config;

use tool_monitoring\simple_metric_config;

defined('MOODLE_INTERNAL') || die();

/**
 * Lista de ventanas en días, separadas por coma (p. ej. "7,15,30").
 *
 * Decisión de diseño (HU3-T3): las métricas `inactive_users_7d`,
 * `inactive_users_15d` e `inactive_users_30d` del catálogo son la misma
 * métrica con distinta ventana, así que se implementan **una sola vez** como
 * métrica configurable (`metric_with_config`) con las ventanas como parámetro,
 * igual que la métrica nativa `users_online`. Cada ventana produce una serie
 * etiquetada con `time_window` (p. ej. `time_window="30d"`).
 */
final class windows_config extends simple_metric_config {
    /**
     * @param string $windowdays Lista de días separada por comas, p. ej. "7,15,30".
     */
    public function __construct(
        /** @var string Lista de días separada por comas. */
        public string $windowdays = '7,15,30',
    ) {
    }

    /**
     * Devuelve las ventanas como enteros de días, ordenadas y sin duplicados.
     *
     * @return int[]
     */
    public function days(): array {
        $days = [];
        foreach (explode(',', $this->windowdays) as $chunk) {
            $chunk = trim($chunk);
            if ($chunk !== '' && is_numeric($chunk) && (int) $chunk > 0) {
                $days[(int) $chunk] = (int) $chunk;
            }
        }
        if (empty($days)) {
            $days = [7 => 7, 15 => 15, 30 => 30];
        }
        ksort($days);
        return array_values($days);
    }
}