<?php

namespace SecMTI\Core;

class Registry
{
    private static array $services = [];

    /**
     * Establece un servicio en el registro.
     *
     * @param string $key La clave única para el servicio.
     * @param object $service La instancia del servicio a registrar.
     * @return void
     */
    public static function set(string $key, object $service): void
    {
        self::$services[$key] = $service;
    }

    /**
     * Obtiene un servicio del registro.
     *
     * @param string $key La clave del servicio a obtener.
     * @return object La instancia del servicio.
     * @throws \Exception Si el servicio no está registrado.
     */
    public static function get(string $key): object
    {
        if (!self::has($key)) {
            throw new \Exception("El servicio '{$key}' no está registrado en el Registry.");
        }
        return self::$services[$key];
    }

    /**
     * Verifica si un servicio está registrado.
     *
     * @param string $key La clave del servicio a verificar.
     * @return bool True si el servicio está registrado, false en caso contrario.
     */
    public static function has(string $key): bool
    {
        return isset(self::$services[$key]);
    }

    /**
     * Elimina un servicio del registro.
     *
     * @param string $key La clave del servicio a eliminar.
     * @return void
     */
    public static function remove(string $key): void
    {
        unset(self::$services[$key]);
    }
}