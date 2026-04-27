<?php

namespace Pepeiborra\CI4TrafficReader;

/**
 * CI4 Package Registrar
 *
 * CodeIgniter 4 descubre automáticamente este archivo cuando el paquete
 * está instalado vía Composer. Registra namespaces, filtros y rutas.
 */
class TrafficReaderRegistrar
{
    /**
     * Registra el namespace del paquete para que CI4 pueda
     * localizar Filters, Controllers y Views automáticamente.
     */
    public static function register(): array
    {
        return [
            'psr4' => [
                'Pepeiborra\\CI4TrafficReader' => __DIR__,
            ],
        ];
    }
}
