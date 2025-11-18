<?php

namespace SecMTI\Util;

class Validator
{
    /**
     * Valida que una cadena tenga una longitud permitida.
     *
     * @param string $value El valor a validar.
     * @param int $min La longitud mínima permitida.
     * @param int $max La longitud máxima permitida.
     * @param string $field_name El nombre del campo para mensajes de error.
     * @return string|null Un mensaje de error si la validación falla, de lo contrario null.
     */
    public static function validateStringLength(string $value, int $min, int $max, string $field_name): ?string
    {
        $len = mb_strlen($value);
        if ($len < $min || $len > $max) {
            return "El campo '{$field_name}' debe tener entre {$min} y {$max} caracteres (actual: {$len})";
        }
        return null;
    }

    /**
     * Valida una URL.
     *
     * @param string $url La URL a validar.
     * @param string $field_name El nombre del campo para mensajes de error.
     * @param bool $allow_relative Si se permiten URLs relativas.
     * @return string|null Un mensaje de error si la validación falla, de lo contrario null.
     */
    public static function validateUrl(string $url, string $field_name, bool $allow_relative = false): ?string
    {
        if (empty($url)) return null;

        // Bloquear esquemas peligrosos
        $dangerous_schemes = ['javascript:', 'data:', 'vbscript:', 'file:'];
        foreach ($dangerous_schemes as $scheme) {
            if (stripos($url, $scheme) === 0) {
                return "El campo '{$field_name}' contiene un esquema de URL no permitido";
            }
        }

        // Si es relativa y está permitido, aceptar
        if ($allow_relative && !preg_match('/^https?:\/\//', $url)) {
            return null;
        }

        // Validar URL completa
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return "El campo '{$field_name}' no es una URL válida";
        }

        return null;
    }

    /**
     * Valida un número de teléfono.
     *
     * @param string $phone El número de teléfono a validar.
     * @param string $field_name El nombre del campo para mensajes de error.
     * @return string|null Un mensaje de error si la validación falla, de lo contrario null.
     */
    public static function validatePhone(string $phone, string $field_name): ?string
    {
        // Remover espacios, guiones, paréntesis
        $clean = preg_replace('/[\s\-\(\)]/', '', $phone);

        // Debe tener entre 8 y 15 dígitos (puede incluir +)
        if (!preg_match('/^\+?\d{8,15}$/', $clean)) {
            return "El campo '{$field_name}' no es un teléfono válido";
        }

        return null;
    }

    /**
     * Valida un SVG path.
     *
     * @param string $path El path SVG a validar.
     * @param string $field_name El nombre del campo para mensajes de error.
     * @return string|null Un mensaje de error si la validación falla, de lo contrario null.
     */
    public static function validateSvgPath(string $path, string $field_name): ?string
    {
        if (empty($path)) return null;

        // Bloquear eventos JS
        $dangerous_patterns = [
            '/on\w+\s*=/i',           // onclick, onload, etc.
            '/<script/i',             // <script>
            '/javascript:/i',         // javascript:
            '/data:text\/html/i',     // data URLs
        ];

        foreach ($dangerous_patterns as $pattern) {
            if (preg_match($pattern, $path)) {
                return "El campo '{$field_name}' contiene código potencialmente peligroso";
            }
        }

        // Validar que parezca un path SVG válido
        if (!preg_match('/^[MmLlHhVvCcSsQqTtAaZz0-9\s,\.\-]+$/', $path)) {
            return "El campo '{$field_name}' no parece un path SVG válido";
        }

        return null;
    }
}
