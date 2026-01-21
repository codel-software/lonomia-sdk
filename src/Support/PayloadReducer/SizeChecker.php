<?php

namespace CodelSoftware\LonomiaSdk\Support\PayloadReducer;

/**
 * Verifica o tamanho do payload em bytes.
 *
 * Calcula o tamanho do payload serializado em JSON para determinar
 * se excede os limites configurados.
 */
class SizeChecker
{
    /**
     * Calcula o tamanho do payload em bytes (JSON serializado).
     *
     * @param  array  $payload  Payload a ser verificado
     * @return int Tamanho em bytes
     */
    public function getSize(array $payload): int
    {
        try {
            $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($json === false) {
                // Se falhar ao serializar, retorna tamanho estimado baseado em count
                return strlen(serialize($payload));
            }

            return strlen($json);
        } catch (\Throwable $e) {
            // Em caso de erro, retorna tamanho estimado
            return strlen(serialize($payload));
        }
    }

    /**
     * Verifica se o payload excede o limite especificado.
     *
     * @param  array  $payload  Payload a ser verificado
     * @param  int  $limit  Limite em bytes
     * @return bool True se exceder o limite
     */
    public function exceedsLimit(array $payload, int $limit): bool
    {
        try {
            return $this->getSize($payload) > $limit;
        } catch (\Throwable $e) {
            // Em caso de erro, assume que excede para aplicar redução
            return true;
        }
    }
}
