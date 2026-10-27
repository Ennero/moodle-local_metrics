<?php
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Config de una sola ventana temporal (en días) para métricas de actividad.
 *
 * @package    local_metrics
 * @copyright  2026 UEDi - Facultad de Ingeniería USAC
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_metrics\config;

use tool_monitoring\simple_metric_config;

defined('MOODLE_INTERNAL') || die();

/**
 * Ventana única, expresada en días.
 *
 * `simple_metric_config` infiere el campo de formulario y la (de)serialización
 * JSON a partir del parámetro del constructor.
 */
final class window_config extends simple_metric_config {
    /**
     * @param int $windowdays Tamaño de la ventana en días.
     */
    public function __construct(
        /** @var int Tamaño de la ventana en días. */
        public int $windowdays = 30,
    ) {
    }

    /**
     * Ventana en segundos.
     *
     * @return int
     */
    public function seconds(): int {
        return max(1, $this->windowdays) * DAYSECS;
    }
}