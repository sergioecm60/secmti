<?php

namespace SecMTI\Util;

/**
 * Clase de utilidad para el cifrado y descifrado simétrico.
 * Utiliza AES-256-GCM como método principal y soporta AES-256-CBC para descifrar datos antiguos.
 */
class Encryption
{
    private const GCM_METHOD = 'aes-256-gcm';
    private const CBC_METHOD = 'aes-256-cbc';
    private string $key;

    /**
     * Constructor.
     * @param string $key La clave de cifrado secreta. Debe ser de 32 bytes.
     */
    public function __construct(string $base64_key)
    {
        $decoded_key = base64_decode($base64_key, true);
        if ($decoded_key === false || mb_strlen($decoded_key, '8bit') !== 32) {
            throw new \InvalidArgumentException('La clave de cifrado debe ser una cadena base64 que decodifique a 32 bytes.');
        }
        $this->key = $decoded_key;
    }

    /**
     * Cifra un texto plano usando AES-256-GCM.
     *
     * @param string $plaintext El texto a cifrar.
     * @return string|false El texto cifrado en base64 (IV + Tag + Ciphertext) o false en caso de error.
     */
    public function encrypt(string $plaintext): string|false
    {
        $iv_length = openssl_cipher_iv_length(self::GCM_METHOD);
        $iv = openssl_random_pseudo_bytes($iv_length);
        $tag = ''; // El tag de autenticación será llenado por openssl_encrypt.

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::GCM_METHOD,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag // Se pasa por referencia.
        );

        if ($ciphertext === false) {
            return false;
        }

        // Devolver IV, tag y texto cifrado, todo junto y codificado en base64.
        return base64_encode($iv . $tag . $ciphertext);
    }

    /**
     * Descifra un texto cifrado.
     * Intenta descifrar con GCM primero, y si falla, intenta con el método CBC legado.
     *
     * @param string $encrypted_text El texto cifrado en base64.
     * @return string|false El texto plano original o false en caso de error.
     */
    public function decrypt(string $encrypted_text): string|false
    {
        // Intentar descifrar con el nuevo método GCM.
        $gcm_decrypted = $this->decryptGCM($encrypted_text);
        if ($gcm_decrypted !== false) {
            return $gcm_decrypted;
        }

        // Si GCM falla, intentar con el método legado CBC.
        // Esto permite una "lazy migration".
        $cbc_decrypted = $this->decryptCBC($encrypted_text);
        if ($cbc_decrypted !== false) {
            return $cbc_decrypted;
        }

        return false; // No se pudo descifrar con ninguno de los métodos.
    }

    /**
     * Descifra usando AES-256-GCM.
     */
    private function decryptGCM(string $encrypted_text): string|false
    {
        $data = base64_decode($encrypted_text, true);
        if ($data === false) {
            return false;
        }

        $iv_length = openssl_cipher_iv_length(self::GCM_METHOD);
        $tag_length = 16; // El tag de GCM es de 16 bytes.

        // Asegurarse de que el texto cifrado tiene la longitud mínima.
        if (mb_strlen($data, '8bit') < $iv_length + $tag_length) {
            return false;
        }

        $iv = substr($data, 0, $iv_length);
        $tag = substr($data, $iv_length, $tag_length);
        $ciphertext = substr($data, $iv_length + $tag_length);

        return openssl_decrypt(
            $ciphertext,
            self::GCM_METHOD,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );
    }

    /**
     * Descifra usando el método legado AES-256-CBC.
     */
    private function decryptCBC(string $encrypted_text): string|false
    {
        $data = base64_decode($encrypted_text, true);
        if ($data === false) {
            return false;
        }

        $iv_length = openssl_cipher_iv_length(self::CBC_METHOD);

        // Asegurarse de que el texto cifrado tiene la longitud mínima.
        if (mb_strlen($data, '8bit') < $iv_length) {
            return false;
        }

        $iv = substr($data, 0, $iv_length);
        $ciphertext = substr($data, $iv_length);

        // El @ suprime warnings de openssl_decrypt si el padding es incorrecto,
        // lo cual es esperado si se intenta descifrar un texto GCM con CBC.
        return @openssl_decrypt($ciphertext, self::CBC_METHOD, $this->key, OPENSSL_RAW_DATA, $iv);
    }
}
