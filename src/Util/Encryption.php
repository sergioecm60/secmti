<?php

namespace SecMTI\Util;

/**
 * Clase de utilidad para el cifrado y descifrado simétrico.
 * Utiliza AES-256-CBC.
 */
class Encryption
{
    private const CIPHER_METHOD = 'aes-256-cbc';
    private string $key;

    /**
     * Constructor.
     * @param string $key La clave de cifrado secreta. Debe ser de 32 bytes.
     */
    public function __construct(string $key)
    {
        if (mb_strlen($key, '8bit') !== 32) {
            throw new \InvalidArgumentException('La clave de cifrado debe tener exactamente 32 bytes.');
        }
        $this->key = $key;
    }

    /**
     * Cifra un texto plano.
     *
     * @param string $plaintext El texto a cifrar.
     * @return string|false El texto cifrado en base64 (incluyendo IV) o false en caso de error.
     */
    public function encrypt(string $plaintext): string|false
    {
        $iv_length = openssl_cipher_iv_length(self::CIPHER_METHOD);
        $iv = openssl_random_pseudo_bytes($iv_length);

        $ciphertext = openssl_encrypt($plaintext, self::CIPHER_METHOD, $this->key, OPENSSL_RAW_DATA, $iv);

        if ($ciphertext === false) {
            return false;
        }

        // Se antepone el IV al texto cifrado para usarlo al descifrar.
        return base64_encode($iv . $ciphertext);
    }

    /**
     * Descifra un texto cifrado.
     *
     * @param string $encrypted_text El texto cifrado en base64 (con IV antepuesto).
     * @return string|false El texto plano original o false en caso de error.
     */
    public function decrypt(string $encrypted_text): string|false
    {
        $data = base64_decode($encrypted_text, true);
        if ($data === false) {
            return false;
        }

        $iv_length = openssl_cipher_iv_length(self::CIPHER_METHOD);
        $iv = substr($data, 0, $iv_length);
        $ciphertext = substr($data, $iv_length);

        return openssl_decrypt($ciphertext, self::CIPHER_METHOD, $this->key, OPENSSL_RAW_DATA, $iv);
    }
}